<?php

namespace App\Console\Commands;

use App\Domain\Meetings\Services\MeetingLifecycleService;
use Illuminate\Console\Command;

class ReconcileMeetingLifecycleCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:meetings:reconcile-lifecycle';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile meeting lifecycle states: resolve stale allocating meetings and transition started/completed sessions';

    /**
     * Execute the console command.
     */
    public function handle(MeetingLifecycleService $lifecycleService): int
    {
        $this->info('Starting meeting lifecycle reconciliation...');

        $stats = $lifecycleService->reconcileAll();

        $this->info('Reconciliation complete:');
        $this->line("- Resolved stale allocating: {$stats['stale_allocating']}");
        $this->line("- Transitioned to started: {$stats['transitioned_started']}");
        $this->line("- Transitioned to completed: {$stats['transitioned_completed']}");

        return Command::SUCCESS;
    }
}
