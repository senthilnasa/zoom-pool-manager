<?php

namespace App\Console\Commands;

use App\Domain\Zoom\Services\ZoomUserSyncService;
use Illuminate\Console\Command;

class SyncZoomUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:zoom:sync-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize licensed Zoom host accounts and capacities from your Zoom organization into Zoom Pool Manager';

    /**
     * Execute the console command.
     */
    public function handle(ZoomUserSyncService $syncService): int
    {
        $this->info('Starting Zoom host accounts synchronization...');

        $result = $syncService->syncUsers();

        if ($result['success']) {
            $this->info("✓ {$result['message']}");

            return Command::SUCCESS;
        }

        $this->error("✗ {$result['message']}");

        return Command::FAILURE;
    }
}
