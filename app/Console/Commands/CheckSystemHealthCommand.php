<?php

namespace App\Console\Commands;

use App\Domain\Operations\Services\HealthCheckService;
use App\Domain\Operations\Services\OperationsAlertService;
use App\Domain\Settings\Models\Setting;
use Illuminate\Console\Command;

class CheckSystemHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:health:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform scheduled system diagnostics, heartbeat update, and anomaly checks';

    /**
     * Execute the console command.
     */
    public function handle(HealthCheckService $healthService, OperationsAlertService $alertService): int
    {
        // Record heartbeat
        Setting::set('scheduler.last_heartbeat_at', now()->toIso8601String());

        // Check anomalies
        $alerts = $alertService->checkAnomalies();

        $health = $healthService->getSystemHealth();

        $this->info("System health status: {$health['status']}. Active anomaly alerts: ".count($alerts));

        return self::SUCCESS;
    }
}
