<?php

namespace App\Console\Commands;

use App\Domain\Settings\Models\Setting;
use App\Http\Middleware\EnsureInstalled;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class UnlockInstaller extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:installer:unlock {--force : Force unlock without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Unlock the Zoom Pool Manager web installer wizard';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $lockPath = storage_path(EnsureInstalled::LOCK_FILE);
        $hasLockFile = file_exists($lockPath);
        $hasDbFlag = false;

        try {
            if (Schema::hasTable('system_settings')) {
                $hasDbFlag = ! empty(Setting::get('installed_at'));
            }
        } catch (\Throwable $e) {
            // DB not reachable
        }

        if (! $hasLockFile && ! $hasDbFlag) {
            $this->info('Installer is already unlocked.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Are you sure you want to unlock the installer? This allows re-running setup.')) {
            $this->warn('Operation cancelled.');

            return self::FAILURE;
        }

        $removedLock = true;
        if ($hasLockFile) {
            $removedLock = @unlink($lockPath);
        }

        if ($hasDbFlag) {
            try {
                Setting::forget('installed_at');
            } catch (\Throwable $e) {
                $this->warn('Could not clear database flag: '.$e->getMessage());
            }
        }

        if ($removedLock) {
            $this->info('Installer successfully unlocked. You can now access /installer.');

            return self::SUCCESS;
        }

        $this->error('Failed to remove lock file at: '.$lockPath);

        return self::FAILURE;
    }
}
