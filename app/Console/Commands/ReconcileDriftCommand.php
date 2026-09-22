<?php

namespace App\Console\Commands;

use App\Domain\Reconciliation\Services\DriftReconciliationService;
use Illuminate\Console\Command;

class ReconcileDriftCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:reconcile:drift {--days=30 : Number of days ahead to scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan managed Zoom resources and detect schedule or state drift against Zoom API';

    /**
     * Execute the console command.
     */
    public function handle(DriftReconciliationService $service): int
    {
        $days = (int) $this->option('days');
        $this->info("Scanning managed Zoom resources for drift across the next {$days} days...");

        $result = $service->reconcile($days);

        $this->info("Completed. Checked resources: {$result['checked_resources']}. Conflicts found: {$result['conflicts_created']}.");

        return self::SUCCESS;
    }
}
