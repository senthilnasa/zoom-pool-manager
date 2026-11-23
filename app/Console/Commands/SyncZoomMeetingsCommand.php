<?php

namespace App\Console\Commands;

use App\Domain\Zoom\Services\ZoomMeetingSyncService;
use Illuminate\Console\Command;

class SyncZoomMeetingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:zoom:sync-meetings
        {--meeting= : Sync only this meeting (public id or Zoom meeting id)}
        {--all : Include meetings that have already ended}
        {--skip-host-keys : Do not refresh host keys from Zoom, only update meetings}
        {--dry-run : Show what would change without saving}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Zoom meeting details (join URL, passcode, UUID) and host keys from Zoom for all meetings';

    /**
     * Execute the console command.
     */
    public function handle(ZoomMeetingSyncService $syncService): int
    {
        $this->info('Starting Zoom meeting and host key sync...');

        $meeting = $this->option('meeting');

        $result = $syncService->sync(
            includePast: (bool) $this->option('all'),
            meetingPublicId: is_string($meeting) ? $meeting : null,
            dryRun: (bool) $this->option('dry-run'),
            syncHostKeys: ! $this->option('skip-host-keys'),
        );

        $this->table(['Item', 'Checked', 'Updated', 'Unchanged', 'Not in Zoom', 'Failed'], [
            ['Host keys', $result['host_keys']['checked'], $result['host_keys']['updated'], '-', '-', $result['host_keys']['failed']],
            ['Meetings', $result['meetings']['checked'], $result['meetings']['updated'], $result['meetings']['unchanged'], $result['meetings']['missing'], $result['meetings']['failed']],
        ]);

        foreach ($result['errors'] as $error) {
            $this->warn("  - {$error}");
        }

        if ($result['success']) {
            $this->info("✓ {$result['message']}");

            return Command::SUCCESS;
        }

        $this->error("✗ {$result['message']}");

        return Command::FAILURE;
    }
}
