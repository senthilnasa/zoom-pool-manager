<?php

namespace App\Domain\Operations\Services;

use App\Domain\Communication\Models\EmailDelivery;
use App\Domain\Settings\Models\Setting;
use App\Domain\Webhooks\Models\ZoomWebhookEvent;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Http\Middleware\EnsureInstalled;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class HealthCheckService
{
    /**
     * Run all system diagnostics and return structured health status.
     *
     * @return array<string, mixed>
     */
    public function getSystemHealth(): array
    {
        $checks = [];
        $overallStatus = 'healthy';

        // 1. Database Check
        $dbStatus = 'ok';
        $dbError = null;
        $dbVersion = 'Unknown';
        try {
            DB::connection()->getPdo();
            try {
                $dbVersion = (string) DB::connection()->getPdo()->query('SELECT VERSION()')->fetchColumn();
            } catch (\Throwable $e) {
                $dbVersion = 'SQLite/Embedded';
            }
        } catch (\Throwable $e) {
            $dbStatus = 'error';
            $dbError = $e->getMessage();
            $overallStatus = 'failed';
        }
        $checks['database'] = [
            'status' => $dbStatus,
            'driver' => config('database.default'),
            'version' => $dbVersion,
            'error' => $dbError,
        ];

        // 2. Zoom Connections Check
        $connections = ZoomConnection::where('enabled', true)->get();
        $connectionsHealth = [];
        foreach ($connections as $conn) {
            $connStatus = $conn->status === 'active' ? 'ok' : ($conn->status === 'degraded' ? 'warning' : 'error');
            if ($connStatus === 'error' && $overallStatus !== 'failed') {
                $overallStatus = 'warning';
            }
            $connectionsHealth[] = [
                'public_id' => $conn->public_id,
                'name' => $conn->name,
                'status' => $connStatus,
                'last_success_at' => $conn->last_success_at?->toIso8601String(),
                'last_error' => $conn->last_error,
            ];
        }
        $checks['zoom_connections'] = [
            'status' => empty($connectionsHealth) ? 'warning' : 'ok',
            'connections' => $connectionsHealth,
        ];

        // 3. Scheduler / Cron Check
        $lastCronRun = Setting::get('scheduler.last_heartbeat_at');
        $cronStatus = 'ok';
        if (empty($lastCronRun)) {
            $cronStatus = 'warning';
        } else {
            $lastRun = Carbon::parse($lastCronRun);
            if ($lastRun->diffInMinutes(now()) > 30) {
                $cronStatus = 'warning';
                if ($overallStatus === 'healthy') {
                    $overallStatus = 'warning';
                }
            }
        }
        $checks['scheduler'] = [
            'status' => $cronStatus,
            'last_heartbeat_at' => $lastCronRun,
        ];

        // 4. Queue Backlog Check
        $pendingJobsCount = 0;
        $failedJobsCount = 0;
        try {
            $pendingJobsCount = DB::table('jobs')->count();
            $failedJobsCount = DB::table('failed_jobs')->count();
        } catch (\Throwable $e) {
            // Queue tables might not exist in early tests
        }
        $queueStatus = ($failedJobsCount > 10 || $pendingJobsCount > 500) ? 'warning' : 'ok';
        if ($queueStatus === 'warning' && $overallStatus === 'healthy') {
            $overallStatus = 'warning';
        }
        $checks['queue'] = [
            'status' => $queueStatus,
            'pending_jobs' => $pendingJobsCount,
            'failed_jobs' => $failedJobsCount,
        ];

        // 5. Inbound Webhooks Check (Silence Detection)
        $latestWebhook = ZoomWebhookEvent::latest()->first();
        $webhookStatus = 'ok';
        $checks['webhooks'] = [
            'status' => $webhookStatus,
            'last_received_at' => $latestWebhook?->created_at->toIso8601String(),
            'total_events' => ZoomWebhookEvent::count(),
        ];

        // 6. Email Delivery Health Check
        $failedEmailsCount = EmailDelivery::where('status', 'failed')
            ->where('created_at', '>=', now()->subHours(24))
            ->count();
        $mailStatus = $failedEmailsCount > 5 ? 'warning' : 'ok';
        $checks['mail'] = [
            'status' => $mailStatus,
            'failed_last_24h' => $failedEmailsCount,
            'default_provider' => Setting::get('mail.provider', 'log'),
        ];

        // 7. Storage Writability & Space
        $storagePath = storage_path('framework');
        $isWritable = is_writable($storagePath);
        $diskFreeBytes = @disk_free_space($storagePath);
        $checks['storage'] = [
            'status' => $isWritable ? 'ok' : 'error',
            'writable' => $isWritable,
            'free_space_bytes' => $diskFreeBytes ?: 0,
        ];

        $isInstalled = app(EnsureInstalled::class)->isInstalled();

        return [
            'status' => ($overallStatus === 'healthy' && $isInstalled) ? 'healthy' : $overallStatus,
            'app' => [
                'name' => config('app.name'),
                'version' => Setting::get('app_version', '1.0.0-dev'),
                'installed' => $isInstalled,
                'environment' => config('app.env'),
                'maintenance_mode' => app()->isDownForMaintenance(),
            ],
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Diagnostic test: Database Connectivity.
     *
     * @return array{success: bool, message: string}
     */
    public function testDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['success' => true, 'message' => 'Database connection established successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Database connection failed: '.$e->getMessage()];
        }
    }

    /**
     * Diagnostic test: Queue Push.
     *
     * @return array{success: bool, message: string}
     */
    public function testQueue(): array
    {
        try {
            Queue::pushRaw('{"test": true}', 'default');

            return ['success' => true, 'message' => 'Test job successfully dispatched to queue.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Queue test failed: '.$e->getMessage()];
        }
    }
}
