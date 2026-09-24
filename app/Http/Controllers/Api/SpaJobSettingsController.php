<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Services\ScheduledJobService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaJobSettingsController extends Controller
{
    public function __construct(
        protected ScheduledJobService $jobService,
        protected AuditService $auditService
    ) {}

    /**
     * List all scheduled platform background jobs with execution telemetry.
     */
    public function index(): JsonResponse
    {
        $jobs = $this->jobService->getAllJobs();
        $lastHeartbeat = Setting::get('scheduler.last_heartbeat_at');

        $isHealthy = false;
        $heartbeatHuman = 'Never';
        if ($lastHeartbeat) {
            try {
                $dt = Carbon::parse($lastHeartbeat);
                $isHealthy = $dt->diffInMinutes(now()) <= 15;
                $heartbeatHuman = $dt->diffForHumans();
            } catch (\Throwable) {
                $isHealthy = false;
            }
        }

        $cadenceOptions = [
            ['value' => '1m', 'label' => 'Every minute (1m)'],
            ['value' => '5m', 'label' => 'Every 5 minutes (5m)'],
            ['value' => '10m', 'label' => 'Every 10 minutes (10m)'],
            ['value' => '15m', 'label' => 'Every 15 minutes (15m)'],
            ['value' => '30m', 'label' => 'Every 30 minutes (30m)'],
            ['value' => 'hourly', 'label' => 'Hourly'],
            ['value' => 'daily', 'label' => 'Daily (at 02:00)'],
        ];

        return response()->json([
            'jobs' => $jobs,
            'cadence_options' => $cadenceOptions,
            'daemon_status' => [
                'last_heartbeat' => $lastHeartbeat,
                'heartbeat_human' => $heartbeatHuman,
                'is_scheduler_healthy' => $isHealthy,
                'server_time' => now()->toIso8601String(),
                'active_jobs_count' => count(array_filter($jobs, fn ($j) => $j['enabled'])),
                'total_jobs_count' => count($jobs),
            ],
        ]);
    }

    /**
     * Update configuration for a specific background job.
     */
    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'cadence' => 'nullable|string|in:1m,5m,10m,15m,30m,hourly,daily',
        ]);

        try {
            $updated = $this->jobService->updateJob($key, $validated);

            $this->auditService->log(
                event: 'scheduled_job.updated',
                auditable: null,
                oldValues: [],
                newValues: ['job_key' => $key, 'description' => "Updated scheduled job configuration: {$key}", ...$validated]
            );

            return response()->json([
                'success' => true,
                'job' => $updated,
                'message' => 'Job schedule updated successfully.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Trigger on-demand execution of a scheduled job ("Run Now").
     */
    public function run(string $key): JsonResponse
    {
        try {
            $result = $this->jobService->runJob($key);

            $this->auditService->log(
                event: 'scheduled_job.manual_run',
                auditable: null,
                oldValues: [],
                newValues: ['job_key' => $key, 'exit_code' => $result['exit_code'], 'description' => "Manually triggered scheduled job: {$key}"]
            );

            return response()->json([
                'success' => $result['success'],
                'command' => $result['command'],
                'output' => $result['output'],
                'exit_code' => $result['exit_code'],
                'executed_at' => $result['executed_at'],
                'message' => $result['success'] ? 'Job completed successfully.' : 'Job completed with errors.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
