<?php

namespace App\Console\Commands;

use App\Domain\System\Services\AppUpdateService;
use Illuminate\Console\Command;

class SystemVersionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:version {--check : Check GitHub for available updates}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the current application version and optionally check for updates';

    /**
     * Execute the console command.
     */
    public function handle(AppUpdateService $updateService): int
    {
        $currentVersion = $updateService->getCurrentVersion();
        /** @var string $author */
        $author = config('zpm.attribution.author', 'Senthil Nasa');
        /** @var string $repo */
        $repo = config('zpm.repository', 'senthilnasa/zoom-pool-manager');

        $this->info("Zoom Pool Manager (ZPM) v{$currentVersion}");
        $this->line("Repository: https://github.com/{$repo}");
        $this->line("Created by: {$author}");

        if ($this->option('check')) {
            $this->newLine();
            $this->comment('Checking GitHub for latest release...');
            $result = $updateService->checkForUpdates(force: true);

            if ($result['error']) {
                $this->error("Failed to check for updates: {$result['error']}");

                return 1;
            }

            if ($result['update_available']) {
                $this->info("⚡ An update is available: v{$result['latest_version']} (Current: v{$currentVersion})");
                $this->line("Release Name: {$result['release_name']}");
                $this->line("Run 'php artisan zpm:update' to apply the update.");
            } else {
                $this->info('✓ Your application is up to date.');
            }
        }

        return 0;
    }
}
