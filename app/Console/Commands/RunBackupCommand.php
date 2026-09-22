<?php

namespace App\Console\Commands;

use App\Domain\Operations\Services\BackupService;
use Illuminate\Console\Command;

class RunBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'zpm:backup:run {--encrypt : Encrypt the database backup archive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a verified database backup stored outside the web root';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info('Creating Zoom Pool Manager database backup...');

        $encrypt = (bool) $this->option('encrypt');
        $backup = $backupService->createDatabaseBackup(null, $encrypt);

        $this->info("Backup completed successfully: {$backup->filename}");
        $this->info("Size: {$backup->file_size_bytes} bytes | SHA256 Checksum: {$backup->checksum}");

        return self::SUCCESS;
    }
}
