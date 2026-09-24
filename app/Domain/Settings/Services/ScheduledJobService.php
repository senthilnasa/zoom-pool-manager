<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class ScheduledJobService
{
    /**
     * Complete catalogue of system background scheduled jobs.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getJobDefinitions(): array
    {
        return [
            'meetings_reconcile' => [
                'name' => 'Meeting Lifecycle Reconciler',
                'command' => 'zpm:meetings:reconcile-lifecycle',
                'description' => 'Reconciles stuck allocating, active, and completed meetings with actual Zoom host state.',
                'default_cadence' => '5m',
                'default_enabled' => true,
                'category' => 'Meetings & Host Pool',
            ],
            'directory_sync' => [
                'name' => 'Enterprise Directory & AD User Sync',
                'command' => 'zpm:directory:sync',
                'description' => 'Synchronizes users, roles, and departments from Microsoft Entra ID, Google Workspace, and LDAP.',
                'default_cadence' => '15m',
                'default_enabled' => true,
                'category' => 'Identity & Governance',
            ],
            'zoom_users_sync' => [
                'name' => 'Zoom Host Account Discovery',
                'command' => 'zpm:zoom:sync-users',
                'description' => 'Discovers and syncs Zoom host accounts, license types, and capacity into local resource pool.',
                'default_cadence' => 'hourly',
                'default_enabled' => true,
                'category' => 'Meetings & Host Pool',
            ],
            'meeting_reminders' => [
                'name' => 'Meeting Start Reminders',
                'command' => 'zpm:notifications:send-reminders',
                'description' => 'Sends automated notifications and email reminders to hosts and participants prior to start.',
                'default_cadence' => '5m',
                'default_enabled' => true,
                'category' => 'Communication',
            ],
            'workflow_approvals' => [
                'name' => 'Approval Expirations & Escalations',
                'command' => 'zpm:workflow:check-approvals',
                'description' => 'Checks for pending workflow booking requests nearing expiration and auto-escalates.',
                'default_cadence' => '15m',
                'default_enabled' => true,
                'category' => 'Identity & Governance',
            ],
            'recordings_sync' => [
                'name' => 'Cloud Recordings Sync',
                'command' => 'zpm:recordings:sync',
                'description' => 'Synchronizes newly created cloud recordings, passcodes, and download links from Zoom.',
                'default_cadence' => '30m',
                'default_enabled' => true,
                'category' => 'Recordings & Media',
            ],
            'attendance_sync' => [
                'name' => 'Meeting Attendance Sync',
                'command' => 'zpm:attendance:sync',
                'description' => 'Fetches participant join/leave times, duration, and attendance logs for completed meetings.',
                'default_cadence' => '30m',
                'default_enabled' => true,
                'category' => 'Recordings & Media',
            ],
            'drift_reconcile' => [
                'name' => 'Pool Drift & Conflict Reconciliation',
                'command' => 'zpm:reconcile:drift',
                'description' => 'Detects concurrent booking conflicts, external Zoom account edits, and heals pool state.',
                'default_cadence' => 'hourly',
                'default_enabled' => true,
                'category' => 'Meetings & Host Pool',
            ],
            'health_check' => [
                'name' => 'System Health & Operations Telemetry',
                'command' => 'zpm:health:check',
                'description' => 'Assesses database connectivity, queue backlog, scheduler heartbeat, and triggers alerts.',
                'default_cadence' => '10m',
                'default_enabled' => true,
                'category' => 'System & Security',
            ],
            'backup_run' => [
                'name' => 'Automated Database Backup',
                'command' => 'zpm:backup:run',
                'description' => 'Generates encrypted database dump and archives snapshots to configured backup storage.',
                'default_cadence' => 'daily',
                'default_enabled' => true,
                'category' => 'System & Security',
            ],
        ];
    }

    /**
     * Get all jobs with their merged configured states and execution telemetry.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllJobs(): array
    {
        $definitions = $this->getJobDefinitions();
        $storedConfigs = Setting::get('scheduled_jobs.configs', []);
        $lastRuns = Setting::get('scheduled_jobs.last_runs', []);

        $jobs = [];

        foreach ($definitions as $key => $def) {
            $userConfig = $storedConfigs[$key] ?? [];
            $runData = $lastRuns[$key] ?? [];

            $enabled = array_key_exists('enabled', $userConfig) ? (bool) $userConfig['enabled'] : (bool) $def['default_enabled'];
            $cadence = ! empty($userConfig['cadence']) ? (string) $userConfig['cadence'] : (string) $def['default_cadence'];

            $jobs[] = [
                'key' => $key,
                'name' => $def['name'],
                'command' => $def['command'],
                'description' => $def['description'],
                'category' => $def['category'],
                'enabled' => $enabled,
                'cadence' => $cadence,
                'default_cadence' => $def['default_cadence'],
                'last_run_at' => $runData['timestamp'] ?? null,
                'last_status' => $runData['status'] ?? 'pending',
                'last_output' => $runData['output'] ?? null,
            ];
        }

        return $jobs;
    }

    /**
     * Update configuration for a specific background job.
     *
     * @param  array{enabled?: bool, cadence?: string}  $data
     * @return array<string, mixed>
     */
    public function updateJob(string $key, array $data): array
    {
        $definitions = $this->getJobDefinitions();
        if (! isset($definitions[$key])) {
            throw new \InvalidArgumentException("Unknown scheduled job: {$key}");
        }

        $storedConfigs = Setting::get('scheduled_jobs.configs', []);
        $current = $storedConfigs[$key] ?? [];

        if (array_key_exists('enabled', $data)) {
            $current['enabled'] = (bool) $data['enabled'];
        }

        if (! empty($data['cadence'])) {
            $current['cadence'] = (string) $data['cadence'];
        }

        $storedConfigs[$key] = $current;
        Setting::set('scheduled_jobs.configs', $storedConfigs);

        $all = $this->getAllJobs();
        foreach ($all as $job) {
            if ($job['key'] === $key) {
                return $job;
            }
        }

        return [];
    }

    /**
     * Run a scheduled job on-demand and capture its execution output.
     *
     * @return array{success: bool, command: string, output: string, exit_code: int, executed_at: string}
     */
    public function runJob(string $key): array
    {
        $definitions = $this->getJobDefinitions();
        if (! isset($definitions[$key])) {
            throw new \InvalidArgumentException("Unknown scheduled job: {$key}");
        }

        $command = $definitions[$key]['command'];
        $startTime = now();

        try {
            $exitCode = Artisan::call($command);
            $output = trim(Artisan::output());
            $success = $exitCode === 0;
            $status = $success ? 'success' : 'failed';
        } catch (Throwable $e) {
            $exitCode = 1;
            $output = 'Execution failed with exception: '.$e->getMessage();
            $success = false;
            $status = 'failed';
        }

        // Record telemetry
        $this->recordRun($key, $status, $output);

        return [
            'success' => $success,
            'command' => $command,
            'output' => $output ?: ($success ? 'Command executed successfully without output.' : 'Command exited with errors.'),
            'exit_code' => $exitCode,
            'executed_at' => $startTime->toIso8601String(),
        ];
    }

    /**
     * Record telemetry for a job execution.
     */
    public function recordRun(string $key, string $status, string $output): void
    {
        $lastRuns = Setting::get('scheduled_jobs.last_runs', []);
        $lastRuns[$key] = [
            'timestamp' => now()->toIso8601String(),
            'status' => $status,
            'output' => mb_substr($output, 0, 2000),
        ];

        Setting::set('scheduled_jobs.last_runs', $lastRuns);
    }

    /**
     * Apply scheduled jobs dynamically to Laravel console Schedule.
     */
    public function applySchedule(?Schedule $schedule = null): void
    {
        $jobs = $this->getAllJobs();

        foreach ($jobs as $job) {
            if (! $job['enabled']) {
                continue;
            }

            $event = \Illuminate\Support\Facades\Schedule::command($job['command']);

            match ($job['cadence']) {
                '1m' => $event->everyMinute(),
                '5m' => $event->everyFiveMinutes(),
                '10m' => $event->everyTenMinutes(),
                '15m' => $event->everyFifteenMinutes(),
                '30m' => $event->everyThirtyMinutes(),
                'daily' => $event->dailyAt('02:00'),
                default => $event->hourly(),
            };

            // Hook after completion to record telemetry
            $key = $job['key'];
            $event->after(function () use ($key) {
                app(self::class)->recordRun($key, 'success', 'Completed scheduled run.');
            });
        }
    }
}
