<?php

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Operations\Models\BackupRecord;
use App\Domain\Users\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Uid\Ulid;

class BackupService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Create an automated or manual database backup stored outside the web root.
     */
    public function createDatabaseBackup(?User $actor = null, bool $encrypt = false): BackupRecord
    {
        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $uniqueId = (string) new Ulid;
        $filename = "zpm_backup_{$timestamp}_{$uniqueId}.sql";
        $filePath = "{$backupDir}/{$filename}";

        // Export database tables
        $tables = Schema::getTableListing();
        $dumpHandle = fopen($filePath, 'w');

        if (! $dumpHandle) {
            throw new \RuntimeException("Unable to open backup file for writing: {$filePath}");
        }

        fwrite($dumpHandle, "-- Zoom Pool Manager Database Backup\n");
        fwrite($dumpHandle, '-- Generated at: '.now()->toIso8601String()."\n\n");

        foreach ($tables as $table) {
            fwrite($dumpHandle, "-- Table: {$table}\n");
            $rows = DB::table($table)->get();
            foreach ($rows as $row) {
                $rowArray = (array) $row;
                $cols = implode('`, `', array_keys($rowArray));
                $values = array_map(function ($val) {
                    if ($val === null) {
                        return 'NULL';
                    }

                    return "'".addslashes((string) $val)."'";
                }, array_values($rowArray));
                $valStr = implode(', ', $values);
                fwrite($dumpHandle, "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$valStr});\n");
            }
            fwrite($dumpHandle, "\n");
        }

        fclose($dumpHandle);

        $fileSize = filesize($filePath) ?: 0;
        $checksum = hash_file('sha256', $filePath) ?: '';

        // Verification: ensure file exists and is non-empty
        $verifiedAt = ($fileSize > 0) ? now() : null;
        $status = ($fileSize > 0) ? 'completed' : 'failed';

        /** @var BackupRecord $record */
        $record = BackupRecord::create([
            'filename' => $filename,
            'file_path' => $filePath,
            'file_size_bytes' => $fileSize,
            'storage_disk' => 'local',
            'status' => $status,
            'is_encrypted' => $encrypt,
            'checksum' => $checksum,
            'verified_at' => $verifiedAt,
            'created_at' => now(),
        ]);

        $this->auditService->log(
            'backup.created',
            $record,
            null,
            [
                'filename' => $filename,
                'file_size' => $fileSize,
                'checksum' => $checksum,
            ],
            $actor
        );

        return $record;
    }

    /**
     * List all recorded database backups.
     *
     * @return Collection<int, BackupRecord>
     */
    public function listBackups(): Collection
    {
        return BackupRecord::orderByDesc('created_at')->get();
    }

    /**
     * Purge database backups older than specified days.
     */
    public function cleanupOldBackups(int $keepDays = 30): int
    {
        $cutoff = now()->subDays($keepDays);
        $oldBackups = BackupRecord::where('created_at', '<', $cutoff)->get();
        $deletedCount = 0;

        foreach ($oldBackups as $backup) {
            if (File::exists($backup->file_path)) {
                File::delete($backup->file_path);
            }
            $backup->delete();
            $deletedCount++;
        }

        return $deletedCount;
    }
}
