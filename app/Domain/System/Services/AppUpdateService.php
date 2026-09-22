<?php

namespace App\Domain\System\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Operations\Services\BackupService;
use Exception;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use ZipArchive;

class AppUpdateService
{
    protected string $lockFile;

    public function __construct()
    {
        $this->lockFile = storage_path('framework/update.lock');
    }

    /**
     * Get the current installed application version.
     */
    public function getCurrentVersion(): string
    {
        /** @var string $version */
        $version = config('zpm.version', '1.0.0');

        return ltrim($version, 'v');
    }

    /**
     * Check GitHub for available updates.
     *
     * @return array{
     *     installed_version: string,
     *     latest_version: string,
     *     update_available: bool,
     *     release_name: ?string,
     *     release_notes: ?string,
     *     published_at: ?string,
     *     download_url: ?string,
     *     checksum_url: ?string,
     *     html_url: ?string,
     *     checked_at: string,
     *     error: ?string
     * }
     */
    public function checkForUpdates(bool $force = false): array
    {
        $cacheKey = 'zpm:system:update_check';
        $cacheTtl = (int) config('zpm.updates.cache_ttl_seconds', 3600);

        if ($force) {
            Cache::forget($cacheKey);
        }

        /** @var array{installed_version: string, latest_version: string, update_available: bool, release_name: ?string, release_notes: ?string, published_at: ?string, download_url: ?string, checksum_url: ?string, html_url: ?string, checked_at: string, error: ?string} $cached */
        $cached = Cache::remember($cacheKey, $cacheTtl, function () {
            return $this->fetchLatestReleaseFromGitHub();
        });

        return $cached;
    }

    /**
     * Query the GitHub releases API.
     *
     * @return array{
     *     installed_version: string,
     *     latest_version: string,
     *     update_available: bool,
     *     release_name: ?string,
     *     release_notes: ?string,
     *     published_at: ?string,
     *     download_url: ?string,
     *     checksum_url: ?string,
     *     html_url: ?string,
     *     checked_at: string,
     *     error: ?string
     * }
     */
    protected function fetchLatestReleaseFromGitHub(): array
    {
        $currentVersion = $this->getCurrentVersion();
        /** @var string $apiUrl */
        $apiUrl = config('zpm.release_api_url', 'https://api.github.com/repos/senthilnasa/zoom-pool-manager/releases/latest');

        try {
            $response = Http::timeout((int) config('zpm.updates.timeout_seconds', 15))
                ->withHeaders([
                    'User-Agent' => 'Zoom-Pool-Manager/'.$currentVersion,
                    'Accept' => 'application/vnd.github.v3+json',
                ])
                ->get($apiUrl);

            if (! $response->successful()) {
                return [
                    'installed_version' => $currentVersion,
                    'latest_version' => $currentVersion,
                    'update_available' => false,
                    'release_name' => null,
                    'release_notes' => null,
                    'published_at' => null,
                    'download_url' => null,
                    'checksum_url' => null,
                    'html_url' => null,
                    'checked_at' => now()->toIso8601String(),
                    'error' => 'GitHub API returned status '.$response->status(),
                ];
            }

            /** @var array{tag_name?: string, name?: string, body?: string, published_at?: string, html_url?: string, assets?: array<int, array{name: string, browser_download_url: string}>} $data */
            $data = $response->json();
            $tagName = $data['tag_name'] ?? 'v'.$currentVersion;
            $latestVersion = ltrim($tagName, 'v');

            $downloadUrl = null;
            $checksumUrl = null;

            if (isset($data['assets']) && is_array($data['assets'])) {
                foreach ($data['assets'] as $asset) {
                    if (str_ends_with($asset['name'], '.zip')) {
                        $downloadUrl = $asset['browser_download_url'];
                    } elseif (str_ends_with($asset['name'], '.sha256')) {
                        $checksumUrl = $asset['browser_download_url'];
                    }
                }
            }

            $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

            return [
                'installed_version' => $currentVersion,
                'latest_version' => $latestVersion,
                'update_available' => $updateAvailable,
                'release_name' => $data['name'] ?? 'Release '.$tagName,
                'release_notes' => $data['body'] ?? 'No release notes provided.',
                'published_at' => $data['published_at'] ?? now()->toIso8601String(),
                'download_url' => $downloadUrl,
                'checksum_url' => $checksumUrl,
                'html_url' => $data['html_url'] ?? 'https://github.com/senthilnasa/zoom-pool-manager/releases',
                'checked_at' => now()->toIso8601String(),
                'error' => null,
            ];
        } catch (Exception $e) {
            Log::warning('GitHub release check failed: '.$e->getMessage());

            return [
                'installed_version' => $currentVersion,
                'latest_version' => $currentVersion,
                'update_available' => false,
                'release_name' => null,
                'release_notes' => null,
                'published_at' => null,
                'download_url' => null,
                'checksum_url' => null,
                'html_url' => null,
                'checked_at' => now()->toIso8601String(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if an update process is currently locked.
     */
    public function isLocked(): bool
    {
        if (! File::exists($this->lockFile)) {
            return false;
        }

        $lockTimeoutMinutes = (int) config('zpm.updates.lock_timeout_minutes', 30);
        $lastModified = File::lastModified($this->lockFile);

        // If lock is older than timeout, consider it stale and automatically remove it
        if (time() - $lastModified > ($lockTimeoutMinutes * 60)) {
            $this->releaseLock();

            return false;
        }

        return true;
    }

    /**
     * Acquire the update lock.
     */
    public function acquireLock(): bool
    {
        if ($this->isLocked()) {
            return false;
        }

        File::ensureDirectoryExists(dirname($this->lockFile));

        return File::put($this->lockFile, json_encode([
            'locked_at' => now()->toIso8601String(),
            'pid' => getmypid(),
        ])) !== false;
    }

    /**
     * Release the update lock.
     */
    public function releaseLock(): void
    {
        if (File::exists($this->lockFile)) {
            File::delete($this->lockFile);
        }
    }

    /**
     * Perform the complete safe update lifecycle.
     *
     * @return array{success: bool, message: string, steps: array<int, string>}
     */
    public function applyUpdate(?string $downloadUrl = null, ?string $checksumUrl = null): array
    {
        $steps = [];

        if ($this->isLocked()) {
            return [
                'success' => false,
                'message' => 'An application update is currently in progress. Please wait.',
                'steps' => ['Update lock active'],
            ];
        }

        if (! $this->acquireLock()) {
            return [
                'success' => false,
                'message' => 'Failed to acquire update lock.',
                'steps' => ['Lock acquisition failed'],
            ];
        }

        try {
            // Step 1: Resolve release package URL if not provided directly
            if (! $downloadUrl) {
                $releaseInfo = $this->checkForUpdates(force: true);
                if (! $releaseInfo['update_available'] || ! $releaseInfo['download_url']) {
                    throw new Exception('No newer release download URL was found.');
                }
                $downloadUrl = $releaseInfo['download_url'];
                $checksumUrl = $releaseInfo['checksum_url'];
            }

            $steps[] = 'Verified release download URL: '.$downloadUrl;

            // Step 2: Validate download source
            if (! str_starts_with($downloadUrl, 'https://github.com/senthilnasa/zoom-pool-manager/releases/') &&
                ! str_starts_with($downloadUrl, 'https://api.github.com/')) {
                throw new Exception('Security violation: untrusted update source '.$downloadUrl);
            }

            // Step 3: Pre-flight storage and permissions check
            /** @var string $updatesStorage */
            $updatesStorage = config('zpm.updates.storage_path', storage_path('app/updates'));
            File::ensureDirectoryExists($updatesStorage);

            $freeSpace = disk_free_space(base_path());
            if ($freeSpace !== false && $freeSpace < (50 * 1024 * 1024)) {
                throw new Exception('Insufficient disk space for update (less than 50MB available).');
            }
            $steps[] = 'Pre-flight system checks passed';

            // Step 4: Create pre-update backup
            if (config('zpm.updates.backup_before_update', true)) {
                try {
                    $backupService = app(BackupService::class);
                    $backup = $backupService->createDatabaseBackup();
                    $steps[] = 'Pre-update database backup created: '.$backup->filename;
                } catch (Exception $e) {
                    Log::warning('Pre-update backup warning: '.$e->getMessage());
                    $steps[] = 'Backup warning (proceeding): '.$e->getMessage();
                }
            }

            // Step 5: Download release ZIP
            $tempZipFile = $updatesStorage.DIRECTORY_SEPARATOR.'update-'.time().'.zip';
            $steps[] = 'Downloading update package...';

            $downloadResponse = Http::timeout((int) config('zpm.updates.download_timeout_seconds', 300))
                ->sink($tempZipFile)
                ->get($downloadUrl);

            if (! $downloadResponse->successful() || ! File::exists($tempZipFile)) {
                throw new Exception('Failed to download update archive from GitHub.');
            }

            $steps[] = 'Downloaded update archive ('.round(filesize($tempZipFile) / 1024 / 1024, 2).' MB)';

            // Step 6: Verify Package Integrity (Zip inspection & path traversal check)
            $zip = new ZipArchive;
            if ($zip->open($tempZipFile) !== true) {
                throw new Exception('Downloaded archive is corrupt or invalid ZIP.');
            }

            $entryCount = $zip->numFiles;
            $safeEntries = [];
            for ($i = 0; $i < $entryCount; $i++) {
                $entryName = $zip->getNameIndex($i);
                if ($entryName === false) {
                    continue;
                }

                // Security check: Path traversal prevention
                if (str_contains($entryName, '..') ||
                    str_starts_with($entryName, '/') ||
                    str_starts_with($entryName, '\\') ||
                    preg_match('/^[a-zA-Z]:/', $entryName)) {
                    $zip->close();
                    throw new Exception('Malicious path traversal detected in package: '.$entryName);
                }

                $safeEntries[] = $entryName;
            }

            $steps[] = 'Package integrity verified ('.count($safeEntries).' files audited, 0 path traversals)';

            // Step 7: Enter Maintenance Mode
            Artisan::call('down', [
                '--render' => 'errors::503',
            ]);
            $steps[] = 'Application entered maintenance mode';

            // Step 8: Safe Extraction (Preserve .env, storage/*, uploads, keys, git)
            $extractBase = base_path();
            for ($i = 0; $i < $entryCount; $i++) {
                $entryName = $zip->getNameIndex($i);
                if ($entryName === false) {
                    continue;
                }

                // Strip leading archive root folder if zip was packed inside a subfolder (e.g. zpm-release-xxx/)
                $normalizedPath = $entryName;
                if (preg_match('#^[^/]+/(.*)$#', $entryName, $matches)) {
                    $normalizedPath = $matches[1];
                }

                if (empty($normalizedPath)) {
                    continue;
                }

                // Strictly protected files / directories
                if ($normalizedPath === '.env' ||
                    str_starts_with($normalizedPath, 'storage/') ||
                    str_starts_with($normalizedPath, '.git/') ||
                    str_starts_with($normalizedPath, 'dist/')) {
                    continue;
                }

                $targetPath = $extractBase.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

                if (str_ends_with($entryName, '/')) {
                    File::ensureDirectoryExists($targetPath);
                } else {
                    File::ensureDirectoryExists(dirname($targetPath));
                    $stream = $zip->getStream($entryName);
                    if ($stream !== false) {
                        $contents = stream_get_contents($stream);
                        if ($contents !== false) {
                            file_put_contents($targetPath, $contents);
                        }
                        fclose($stream);
                    }
                }
            }
            $zip->close();
            File::delete($tempZipFile);
            $steps[] = 'Applied update files to application root (protected .env and storage)';

            // Step 9: Post-update Laravel maintenance commands
            Artisan::call('migrate', ['--force' => true]);
            $steps[] = 'Ran database migrations safely';

            Artisan::call('optimize:clear');
            Artisan::call('config:cache');
            Artisan::call('route:cache');
            Artisan::call('view:cache');
            $steps[] = 'Cleared and rebuilt application caches';

            // Step 10: Exit Maintenance Mode
            Artisan::call('up');
            $steps[] = 'Application brought out of maintenance mode';

            // Audit update event
            try {
                app(AuditService::class)->log(
                    event: 'system.update.applied',
                    auditable: null,
                    oldValues: ['version' => $this->getCurrentVersion()],
                    newValues: ['download_url' => $downloadUrl]
                );
            } catch (Exception $e) {
                Log::info('Audit logging update notice: '.$e->getMessage());
            }

            Cache::forget('zpm:system:update_check');

            return [
                'success' => true,
                'message' => 'Application updated successfully!',
                'steps' => $steps,
            ];
        } catch (Exception $e) {
            // Restore from maintenance mode if we failed midway
            try {
                Artisan::call('up');
            } catch (Exception $ignored) {
            }

            Log::error('Update process failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return [
                'success' => false,
                'message' => 'Update failed: '.$e->getMessage(),
                'steps' => [...$steps, 'Error: '.$e->getMessage()],
            ];
        } finally {
            $this->releaseLock();
        }
    }
}
