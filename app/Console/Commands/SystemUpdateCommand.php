<?php

namespace App\Console\Commands;

use App\Domain\System\Services\AppUpdateService;
use Illuminate\Console\Command;

class SystemUpdateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:update
                            {--check : Check if an update is available without applying it}
                            {--force : Apply update without interactive confirmation}
                            {--url= : Manually specify release package ZIP URL}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for and apply application updates from the official GitHub release';

    /**
     * Execute the console command.
     */
    public function handle(AppUpdateService $updateService): int
    {
        $currentVersion = $updateService->getCurrentVersion();
        $this->info("=== Zoom Pool Manager Update Manager (Installed: v{$currentVersion}) ===");

        $this->comment('Checking GitHub for latest release...');
        $release = $updateService->checkForUpdates(force: true);

        if ($release['error']) {
            $this->error("Update check failed: {$release['error']}");

            return 1;
        }

        if (! $release['update_available'] && ! $this->option('url')) {
            $this->info("✓ Application is already at the latest version (v{$currentVersion}).");

            return 0;
        }

        $latestVersion = $release['latest_version'];
        $this->info("New version available: v{$latestVersion}");
        $this->line("Release Name: {$release['release_name']}");

        if ($this->option('check')) {
            $this->line("Run 'php artisan zpm:update' to apply this update.");

            return 0;
        }

        /** @var string|null $downloadUrl */
        $downloadUrl = $this->option('url') ?: $release['download_url'];
        if (! $downloadUrl) {
            $this->error('No download package asset found in the release.');

            return 1;
        }

        if (! $this->option('force') && ! $this->confirm("Are you sure you want to update from v{$currentVersion} to v{$latestVersion}?", true)) {
            $this->comment('Update cancelled by user.');

            return 0;
        }

        $this->comment('Starting update process...');

        $result = $updateService->applyUpdate($downloadUrl, $release['checksum_url']);

        foreach ($result['steps'] as $step) {
            $this->line("  → {$step}");
        }

        if ($result['success']) {
            $this->info("✓ {$result['message']}");

            return 0;
        } else {
            $this->error("✗ {$result['message']}");

            return 1;
        }
    }
}
