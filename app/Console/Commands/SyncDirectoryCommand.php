<?php

namespace App\Console\Commands;

use App\Domain\Auth\Models\DirectorySyncConfig;
use App\Domain\Auth\Services\DirectorySyncService;
use Illuminate\Console\Command;

class SyncDirectoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:directory:sync
                            {--config= : Public ID or database ID of specific directory sync configuration}
                            {--force : Force synchronization even if interval has not elapsed}
                            {--dry-run : Simulate synchronization without writing changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize enterprise users and departments from Active Directory, Microsoft Entra ID, or Google Workspace';

    /**
     * Execute the console command.
     */
    public function handle(DirectorySyncService $syncService): int
    {
        $this->info('Starting Enterprise Directory / Active Directory Synchronization...');

        $configOption = $this->option('config');
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $query = DirectorySyncConfig::query();
        if ($configOption) {
            $query->where('public_id', $configOption)->orWhere('id', $configOption);
        } else {
            $query->where('is_active', true);
        }

        $configs = $query->get();

        if ($configs->isEmpty()) {
            $this->warn('No active directory sync configurations found.');

            return self::SUCCESS;
        }

        foreach ($configs as $config) {
            // Check interval if not explicitly targeted or forced
            if (! $configOption && ! $force && $config->last_synced_at) {
                $interval = (int) ($config->sync_interval_minutes ?: 60);
                $nextDue = $config->last_synced_at->copy()->addMinutes($interval);
                if (now()->lt($nextDue)) {
                    $this->line("Skipping [{$config->name}]: Not due yet (next sync at {$nextDue->toDateTimeString()})");

                    continue;
                }
            }

            $this->line("Executing sync for connector: [{$config->name}] (Provider: {$config->provider_type})");

            $result = $syncService->sync($config, $dryRun);

            if ($result['success']) {
                $this->info("✓ {$result['message']}");
            } else {
                $this->error("✗ {$result['message']}");
            }
        }

        $this->info('Directory synchronization finished.');

        return self::SUCCESS;
    }
}
