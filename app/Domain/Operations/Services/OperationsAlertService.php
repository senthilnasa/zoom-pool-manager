<?php

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\NotificationCenterService;
use App\Domain\Operations\Models\Alert;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Domain\Webhooks\Models\ZoomWebhookEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OperationsAlertService
{
    public function __construct(
        protected NotificationCenterService $notificationCenter,
        protected AuditService $auditService
    ) {}

    /**
     * Trigger or update an operational alert with automatic deduplication.
     *
     * @param  array<string, mixed>|null  $details
     */
    public function triggerAlert(string $key, string $severity, string $title, string $message, ?array $details = null): Alert
    {
        /** @var Alert|null $existingAlert */
        $existingAlert = Alert::where('key', $key)
            ->whereNull('resolved_at')
            ->first();

        if ($existingAlert) {
            $existingAlert->last_seen_at = now();
            $existingAlert->count++;
            $existingAlert->severity = $severity;
            $existingAlert->title = $title;
            $existingAlert->message = $message;
            if ($details !== null) {
                $existingAlert->details = $details;
            }
            $existingAlert->save();

            // Hourly throttle for repeated notifications
            if (! $existingAlert->notified_at || $existingAlert->notified_at->diffInMinutes(now()) >= 60) {
                $this->dispatchAlertNotifications($existingAlert);
                $existingAlert->update(['notified_at' => now()]);
            }

            return $existingAlert;
        }

        /** @var Alert $newAlert */
        $newAlert = Alert::create([
            'key' => $key,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'details' => $details,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'count' => 1,
            'notified_at' => now(),
        ]);

        $this->dispatchAlertNotifications($newAlert);

        $this->auditService->log(
            'alert.triggered',
            $newAlert,
            null,
            ['key' => $key, 'severity' => $severity, 'title' => $title]
        );

        return $newAlert;
    }

    /**
     * Resolve an open alert and send recovery notification.
     */
    public function resolveAlert(string $key, ?string $resolutionNote = null): ?Alert
    {
        /** @var Alert|null $alert */
        $alert = Alert::where('key', $key)
            ->whereNull('resolved_at')
            ->first();

        if (! $alert) {
            return null;
        }

        $alert->resolved_at = now();
        if ($resolutionNote) {
            $details = $alert->details ?? [];
            $details['resolution_note'] = $resolutionNote;
            $alert->details = $details;
        }
        $alert->save();

        $this->dispatchRecoveryNotifications($alert);

        $this->auditService->log(
            'alert.resolved',
            $alert,
            null,
            ['key' => $key, 'resolution_note' => $resolutionNote]
        );

        return $alert;
    }

    /**
     * Scan system indicators for operational anomalies.
     *
     * @return array<int, Alert>
     */
    public function checkAnomalies(): array
    {
        $triggered = [];

        // 1. Cron Inactivity Check
        $lastCronHeartbeat = Setting::get('scheduler.last_heartbeat_at');
        if (! empty($lastCronHeartbeat)) {
            $minutesAgo = Carbon::parse($lastCronHeartbeat)->diffInMinutes(now());
            if ($minutesAgo > 30) {
                $triggered[] = $this->triggerAlert(
                    'scheduler.inactivity',
                    'critical',
                    'Scheduler / Cron Inactive',
                    "No background scheduler heartbeat received for {$minutesAgo} minutes. Scheduled tasks may not be executing.",
                    ['last_heartbeat' => $lastCronHeartbeat, 'minutes_ago' => $minutesAgo]
                );
            } else {
                $this->resolveAlert('scheduler.inactivity', 'Scheduler heartbeat restored.');
            }
        }

        // 2. Failed Jobs Backlog Spike
        try {
            $failedJobs = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subHours(1))
                ->count();

            if ($failedJobs >= 10) {
                $triggered[] = $this->triggerAlert(
                    'queue.failed_jobs_spike',
                    'critical',
                    'Elevated Queue Job Failures',
                    "{$failedJobs} background jobs have failed in the past hour. Please inspect the queue monitor.",
                    ['failed_last_hour' => $failedJobs]
                );
            } elseif ($failedJobs === 0) {
                $this->resolveAlert('queue.failed_jobs_spike', 'Queue failure rate normalized.');
            }
        } catch (\Throwable $e) {
            // Queue tables might not exist
        }

        // 3. Webhook Silence Check
        $latestWebhook = ZoomWebhookEvent::latest()->first();
        if ($latestWebhook) {
            $hoursSinceLastWebhook = $latestWebhook->created_at->diffInHours(now());
            // If more than 48 hours without any webhooks in active system
            if ($hoursSinceLastWebhook > 48) {
                $triggered[] = $this->triggerAlert(
                    'webhooks.silence',
                    'warning',
                    'Zoom Webhook Silence Detected',
                    "No incoming webhooks received for {$hoursSinceLastWebhook} hours. Check webhook endpoint health on Zoom App Marketplace.",
                    ['last_webhook_at' => $latestWebhook->created_at->toIso8601String()]
                );
            } else {
                $this->resolveAlert('webhooks.silence', 'Webhooks received successfully.');
            }
        }

        return $triggered;
    }

    /**
     * Dispatch notification to Super Administrators and IT Administrators.
     */
    protected function dispatchAlertNotifications(Alert $alert): void
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['Super Administrator', 'IT Administrator']);
        })->get();

        foreach ($admins as $admin) {
            $this->notificationCenter->notify(
                $admin,
                'security',
                "[{$alert->severity}] {$alert->title}",
                $alert->message,
                [
                    'alert_id' => $alert->id,
                    'alert_public_id' => $alert->public_id,
                    'key' => $alert->key,
                    'severity' => $alert->severity,
                ]
            );
        }
    }

    /**
     * Dispatch resolution notification to administrators.
     */
    protected function dispatchRecoveryNotifications(Alert $alert): void
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['Super Administrator', 'IT Administrator']);
        })->get();

        foreach ($admins as $admin) {
            $this->notificationCenter->notify(
                $admin,
                'security',
                "[RESOLVED] {$alert->title}",
                "The operational alert '{$alert->key}' has been resolved.",
                [
                    'alert_id' => $alert->id,
                    'alert_public_id' => $alert->public_id,
                    'key' => $alert->key,
                ]
            );
        }
    }
}
