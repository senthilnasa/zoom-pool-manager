<?php

namespace App\Console\Commands;

use App\Domain\Workflow\Services\ApprovalWorkflowService;
use Illuminate\Console\Command;

class CheckApprovalsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:workflow:check-approvals';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan pending workflow approvals and escalate or alert on timeouts';

    /**
     * Execute the console command.
     */
    public function handle(ApprovalWorkflowService $workflowService): int
    {
        $this->info('Scanning pending approvals for timeouts and escalation...');

        $escalated = $workflowService->checkTimeoutsAndEscalate();

        $this->info("Completed. Escalated {$escalated} overdue approval(s).");

        return Command::SUCCESS;
    }
}
