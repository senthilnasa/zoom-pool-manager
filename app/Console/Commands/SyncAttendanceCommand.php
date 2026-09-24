<?php

namespace App\Console\Commands;

use App\Domain\Attendance\Services\ZoomAttendanceSyncService;
use Illuminate\Console\Command;

class SyncAttendanceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:attendance:sync';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize past meeting attendee participation reports from Zoom';

    /**
     * Execute the console command.
     */
    public function handle(ZoomAttendanceSyncService $syncService): int
    {
        $this->info('Starting Zoom participant attendance sync...');

        $result = $syncService->syncRecentAttendance();

        if ($result['success']) {
            $this->info("✓ {$result['message']}");

            return Command::SUCCESS;
        }

        $this->error("✗ {$result['message']}");

        return Command::FAILURE;
    }
}
