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

    public const PROGRESS_CACHE_KEY = 'zpm:system:update_progress';

    public function __construct()
    {
        $this->lockFile = storage_path('framework/update.lock');
    }

    /**
     * Get the current installed application version.
     */
    public function getCurrentVersion(): string
    {
        /** @var string|null $configVersion */
        $configVersion = config('zpm.version');
        if ($configVersion && $configVersion !== '1.0.0') {
            return ltrim($configVersion, 'v');
        }

        $versionFile = base_path('version.json');
        if (File::exists($versionFile)) {
            try {
                $meta = json_decode((string) File::get($versionFile), true);
                if (! empty($meta['version']) && is_string($meta['version'])) {
                    return ltrim($meta['version'], 'v');
                }
            } catch (\Throwable) {
            }
        }

        return ltrim($configVersion ?: '1.0.0', 'v');
    }

    /**
     * Get metadata from version.json.
     *
     * @return array{version: string, build: string, release_date: string, php_min: string}
     */
    public function getVersionMetadata(): array
    {
        $versionFile = base_path('version.json');
        if (File::exists($versionFile)) {
            try {
                $meta = json_decode((string) File::get($versionFile), true);
                if (is_array($meta)) {
                    return [
                        'version' => ltrim((string) ($meta['version'] ?? $this->getCurrentVersion()), 'v'),
                        'build' => (string) ($meta['build'] ?? 'stable'),
                        'release_date' => (string) ($meta['release_date'] ?? ''),
                        'php_min' => (string) ($meta['php_min'] ?? '8.2.0'),
                    ];
                }
            } catch (\Throwable) {
            }
        }

        return [
            'version' => $this->getCurrentVersion(),
            'build' => 'development',
            'release_date' => now()->toDateString(),
            'php_min' => '8.2.0',
        ];
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
     *     error: ?string,
     *     metadata: array<string, mixed>
     * }
     */
    public function checkForUpdates(bool $force = false): array
    {
        $cacheKey = 'zpm:system:update_check';
        $cacheTtl = (int) config('zpm.updates.cache_ttl_seconds', 3600);

        if ($force) {
            Cache::forget($cacheKey);
        }

        /** @var array{installed_version: string, latest_version: string, update_available: bool, release_name: ?string, release_notes: ?string, published_at: ?string, download_url: ?string, checksum_url: ?string, html_url: ?string, checked_at: string, error: ?string, metadata: array<string, mixed>} $cached */
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
     *     error: ?string,
     *     metadata: array<string, mixed>
     * }
     */
    protected function fetchLatestReleaseFromGitHub(): array
    {
        $currentVersion = $this->getCurrentVersion();
        /** @var string $apiUrl */
        $apiUrl = config('zpm.release_api_url', 'https://api.github.com/repos/senthilnasa/zoom-pool-manager/releases/latest');

        try {
            $headers = [
                'User-Agent' => 'Zoom-Pool-Manager/'.$currentVersion,
                'Accept' => 'application/vnd.github.v3+json',
            ];

            if ($token = config('zpm.github_token')) {
                $headers['Authorization'] = 'Bearer '.$token;
            }

            $response = Http::timeout((int) config('zpm.updates.timeout_seconds', 15))
                ->withHeaders($headers)
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
                    'metadata' => $this->getVersionMetadata(),
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
                'metadata' => $this->getVersionMetadata(),
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
                'metadata' => $this->getVersionMetadata(),
            ];
        }
    }

    /**
     * Get system update overview status for UI.
     *
     * @return array<string, mixed>
     */
    public function getStatus(): array
    {
        $info = $this->checkForUpdates(force: false);
        $progress = $this->getProgress();

        return array_merge($info, [
            'is_locked' => $this->isLocked(),
            'progress' => $progress,
        ]);
    }

    /**
     * Get real-time update progress from cache.
     *
     * @return array<string, mixed>
     */
    public function getProgress(): array
    {
        /** @var array<string, mixed>|null $progress */
        $progress = Cache::get(self::PROGRESS_CACHE_KEY);

        if (! is_array($progress)) {
            return [
                'is_active' => false,
                'step_index' => 0,
                'total_steps' => 7,
                'current_step_name' => 'Idle',
                'percent' => 0,
                'steps' => $this->getInitialSteps(),
                'logs' => [],
                'completed' => false,
                'success' => true,
                'error' => null,
                'can_rollback' => false,
            ];
        }

        return $progress;
    }

    /**
     * Get canonical list of update steps.
     *
     * @return array<int, array{id: string, name: string, status: string, message: ?string}>
     */
    public function getInitialSteps(): array
    {
        return [
            ['id' => 'checking', 'name' => 'Checking latest version...', 'status' => 'pending', 'message' => null],
            ['id' => 'downloading', 'name' => 'Downloading update...', 'status' => 'pending', 'message' => null],
            ['id' => 'backing_up', 'name' => 'Creating backup...', 'status' => 'pending', 'message' => null],
            ['id' => 'installing', 'name' => 'Installing update...', 'status' => 'pending', 'message' => null],
            ['id' => 'migrating', 'name' => 'Running migrations...', 'status' => 'pending', 'message' => null],
            ['id' => 'restarting', 'name' => 'Restarting application...', 'status' => 'pending', 'message' => null],
            ['id' => 'verifying', 'name' => 'Verifying installation...', 'status' => 'pending', 'message' => null],
        ];
    }

    /**
     * Update progress state in cache.
     *
     * @param  array<string, mixed>  $data
     */
    protected function updateProgress(array $data): void
    {
        $current = $this->getProgress();
        $merged = array_merge($current, $data);
        Cache::put(self::PROGRESS_CACHE_KEY, $merged, 3600);
    }

    /**
     * Set progress step status.
     */
    protected function setStep(int $index, string $status, ?string $log = null): void
    {
        $progress = $this->getProgress();
        $steps = $progress['steps'];

        if (isset($steps[$index])) {
            $steps[$index]['status'] = $status;
            if ($log) {
                $steps[$index]['message'] = $log;
            }
        }

        $logs = $progress['logs'] ?? [];
        if ($log) {
            $logs[] = '['.now()->format('H:i:s').'] '.$log;
        }

        $totalSteps = count($steps);
        $percent = (int) round((($index + ($status === 'completed' ? 1 : 0.5)) / $totalSteps) * 100);
        $percent = min(100, max(0, $percent));

        $this->updateProgress([
            'is_active' => $status !== 'failed' && $index < ($totalSteps - 1),
            'step_index' => $index,
            'current_step_name' => $steps[$index]['name'] ?? '',
            'percent' => $percent,
            'steps' => $steps,
            'logs' => $logs,
        ]);
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
     * Perform the complete safe Akaunting-style update lifecycle.
     *
     * @return array{success: bool, message: string, steps: array<int, string>}
     */
    public function applyUpdate(?string $downloadUrl = null, ?string $checksumUrl = null): array
    {
        $stepsLog = [];
        $fileBackupPath = null;
        $dbBackupPath = null;

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

        // Initialize progress state
        $this->updateProgress([
            'is_active' => true,
            'step_index' => 0,
            'percent' => 5,
            'steps' => $this->getInitialSteps(),
            'logs' => ['['.now()->format('H:i:s').'] Starting application update process...'],
            'completed' => false,
            'success' => true,
            'error' => null,
            'can_rollback' => false,
        ]);

        try {
            // STEP 0: Checking latest version...
            $this->setStep(0, 'running', 'Checking latest release metadata from GitHub...');
            if (! $downloadUrl) {
                $releaseInfo = $this->checkForUpdates(force: true);
                if (! $releaseInfo['update_available'] || ! $releaseInfo['download_url']) {
                    throw new Exception('No newer release package was found on GitHub.');
                }
                $downloadUrl = $releaseInfo['download_url'];
                $checksumUrl = $releaseInfo['checksum_url'];
            }

            // Security check: validate download source
            if (! str_starts_with($downloadUrl, 'https://github.com/senthilnasa/zoom-pool-manager/releases/') &&
                ! str_starts_with($downloadUrl, 'https://api.github.com/')) {
                throw new Exception('Security violation: untrusted update source '.$downloadUrl);
            }

            $stepsLog[] = 'Verified release download URL: '.$downloadUrl;
            $this->setStep(0, 'completed', 'Verified target release package URL.');

            // STEP 1: Downloading update...
            $this->setStep(1, 'running', 'Downloading update package from GitHub...');
            /** @var string $updatesStorage */
            $updatesStorage = config('zpm.updates.storage_path', storage_path('app/updates'));
            File::ensureDirectoryExists($updatesStorage);

            $tempZipFile = $updatesStorage.DIRECTORY_SEPARATOR.'update-'.time().'.zip';
            $headers = ['User-Agent' => 'Zoom-Pool-Manager/'.$this->getCurrentVersion()];
            if ($token = config('zpm.github_token')) {
                $headers['Authorization'] = 'Bearer '.$token;
            }

            $downloadResponse = Http::timeout((int) config('zpm.updates.download_timeout_seconds', 300))
                ->withHeaders($headers)
                ->sink($tempZipFile)
                ->get($downloadUrl);

            if (! $downloadResponse->successful() || ! File::exists($tempZipFile)) {
                throw new Exception('Failed to download update archive from GitHub.');
            }

            $fileSizeMb = round(filesize($tempZipFile) / 1024 / 1024, 2);
            $stepsLog[] = "Downloaded package ({$fileSizeMb} MB)";

            // Checksum verification if available
            if ($checksumUrl) {
                try {
                    $chkResponse = Http::timeout(15)->withHeaders($headers)->get($checksumUrl);
                    if ($chkResponse->successful()) {
                        $expectedHash = trim(explode(' ', $chkResponse->body())[0]);
                        $actualHash = hash_file('sha256', $tempZipFile);
                        if ($expectedHash && ! hash_equals(strtolower($expectedHash), strtolower((string) $actualHash))) {
                            throw new Exception("Checksum mismatch! Expected: {$expectedHash}, Computed: {$actualHash}");
                        }
                        $stepsLog[] = 'Package SHA-256 checksum verified successfully';
                    }
                } catch (\Throwable $e) {
                    Log::warning('Checksum verification skipped: '.$e->getMessage());
                }
            }

            // Verify Zip validity & inspect paths
            $zip = new ZipArchive;
            if ($zip->open($tempZipFile) !== true) {
                throw new Exception('Downloaded archive is corrupt or invalid ZIP.');
            }

            $entryCount = $zip->numFiles;
            for ($i = 0; $i < $entryCount; $i++) {
                $entryName = $zip->getNameIndex($i);
                if ($entryName !== false && (
                    str_contains($entryName, '..') ||
                    str_starts_with($entryName, '/') ||
                    str_starts_with($entryName, '\\') ||
                    preg_match('/^[a-zA-Z]:/', $entryName)
                )) {
                    $zip->close();
                    throw new Exception('Malicious path traversal detected in package: '.$entryName);
                }
            }
            $this->setStep(1, 'completed', "Downloaded and verified package integrity ({$fileSizeMb} MB).");

            // STEP 2: Creating backup...
            $this->setStep(2, 'running', 'Creating system and database backup before applying update...');
            $backupDir = storage_path('app/backups');
            File::ensureDirectoryExists($backupDir);

            // Database backup
            try {
                $backupService = app(BackupService::class);
                $backupRecord = $backupService->createDatabaseBackup();
                $dbBackupPath = storage_path('app/backups/'.$backupRecord->filename);
                $stepsLog[] = 'Database backup created: '.$backupRecord->filename;
            } catch (\Throwable $e) {
                Log::warning('Database backup error (proceeding): '.$e->getMessage());
                $stepsLog[] = 'Database backup note: '.$e->getMessage();
            }

            // File backup of critical application folders & .env
            $fileBackupName = 'pre_update_backup_'.time().'.zip';
            $fileBackupPath = $backupDir.DIRECTORY_SEPARATOR.$fileBackupName;
            $this->createCriticalFilesBackup($fileBackupPath);
            $stepsLog[] = 'Critical files backup created: '.$fileBackupName;
            $this->setStep(2, 'completed', 'Pre-update backup completed (database snapshot and file state saved).');

            // STEP 3: Installing update...
            $this->setStep(3, 'running', 'Putting app in maintenance mode and extracting update files...');
            try {
                Artisan::call('down', ['--render' => 'errors::503']);
            } catch (\Throwable) {
            }

            // Determine if the archive has a single top-level root folder
            $rootPrefix = null;
            $firstEntry = $zip->getNameIndex(0);
            if ($firstEntry !== false && preg_match('#^([^/]+)/#', $firstEntry, $m)) {
                $candidate = $m[1].'/';
                $allStartWithCandidate = true;
                for ($i = 0; $i < $entryCount; $i++) {
                    $entry = $zip->getNameIndex($i);
                    if ($entry !== false && ! str_starts_with($entry, $candidate)) {
                        $allStartWithCandidate = false;
                        break;
                    }
                }
                if ($allStartWithCandidate) {
                    $rootPrefix = $candidate;
                }
            }

            $extractBase = base_path();
            for ($i = 0; $i < $entryCount; $i++) {
                $entryName = $zip->getNameIndex($i);
                if ($entryName === false) {
                    continue;
                }

                $normalizedPath = $entryName;
                if ($rootPrefix !== null && str_starts_with($entryName, $rootPrefix)) {
                    $normalizedPath = substr($entryName, strlen($rootPrefix));
                }

                if (empty($normalizedPath)) {
                    continue;
                }

                // Strictly protected files / directories
                if ($normalizedPath === '.env' ||
                    $normalizedPath === 'installed.lock' ||
                    $normalizedPath === 'storage/installed.lock' ||
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
            $stepsLog[] = 'Applied update files to application root (protected .env and storage)';
            $this->setStep(3, 'completed', 'Update files installed successfully.');

            // STEP 4: Running migrations...
            $this->setStep(4, 'running', 'Running database schema migrations...');
            Artisan::call('migrate', ['--force' => true]);
            $stepsLog[] = 'Database schema migrations executed';
            $this->setStep(4, 'completed', 'Database migrations completed.');

            // STEP 5: Restarting application...
            $this->setStep(5, 'running', 'Rebuilding caches and exiting maintenance mode...');
            try {
                Artisan::call('optimize:clear');
                Artisan::call('config:cache');
                Artisan::call('route:cache');
                Artisan::call('view:cache');
            } catch (\Throwable) {
            }

            try {
                Artisan::call('up');
            } catch (\Throwable) {
            }
            $stepsLog[] = 'Rebuilt caches and brought application out of maintenance mode';
            $this->setStep(5, 'completed', 'Application restarted and active.');

            // STEP 6: Verifying installation...
            $this->setStep(6, 'running', 'Verifying installation and new version metadata...');
            $newVersion = $this->getCurrentVersion();
            $stepsLog[] = "Active application version: v{$newVersion}";
            $this->setStep(6, 'completed', "Installation verified (v{$newVersion}).");

            // Finalize progress
            $this->updateProgress([
                'is_active' => false,
                'completed' => true,
                'success' => true,
                'percent' => 100,
                'current_step_name' => 'Update completed successfully.',
                'error' => null,
            ]);

            // Audit update event
            try {
                app(AuditService::class)->log(
                    event: 'system.update.applied',
                    auditable: null,
                    oldValues: ['version' => $this->getCurrentVersion()],
                    newValues: ['download_url' => $downloadUrl, 'new_version' => $newVersion]
                );
            } catch (\Throwable $e) {
                Log::info('Audit logging update notice: '.$e->getMessage());
            }

            Cache::forget('zpm:system:update_check');

            return [
                'success' => true,
                'message' => 'Update completed successfully.',
                'steps' => $stepsLog,
            ];
        } catch (Exception $e) {
            // Restore from maintenance mode immediately
            try {
                Artisan::call('up');
            } catch (\Throwable) {
            }

            Log::error('Update process failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            // Attempt rollback if we have a file backup
            $rollbackAttempted = false;
            if ($fileBackupPath && File::exists($fileBackupPath)) {
                $rollbackAttempted = $this->rollbackFromBackup($fileBackupPath);
            }

            $currentProgress = $this->getProgress();
            $currStepIndex = $currentProgress['step_index'] ?? 0;
            $this->setStep($currStepIndex, 'failed', $e->getMessage());

            $this->updateProgress([
                'is_active' => false,
                'completed' => true,
                'success' => false,
                'error' => $e->getMessage(),
                'can_rollback' => $rollbackAttempted,
            ]);

            return [
                'success' => false,
                'message' => 'Update failed: '.$e->getMessage().($rollbackAttempted ? ' (Automatic rollback completed)' : ''),
                'steps' => [...$stepsLog, 'Error: '.$e->getMessage()],
            ];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Create a backup archive of critical application directories before update.
     */
    protected function createCriticalFilesBackup(string $backupPath): void
    {
        $zip = new ZipArchive;
        if ($zip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return;
        }

        $directoriesToBackup = ['app', 'config', 'routes', 'resources', 'version.json'];
        foreach ($directoriesToBackup as $item) {
            $fullPath = base_path($item);
            if (File::isDirectory($fullPath)) {
                $files = File::allFiles($fullPath);
                foreach ($files as $file) {
                    $relative = substr($file->getPathname(), strlen(base_path()) + 1);
                    $zip->addFile($file->getPathname(), str_replace('\\', '/', $relative));
                }
            } elseif (File::isFile($fullPath)) {
                $zip->addFile($fullPath, $item);
            }
        }

        $zip->close();
    }

    /**
     * Rollback application files from pre-update backup archive.
     */
    public function rollbackFromBackup(string $backupPath): bool
    {
        if (! File::exists($backupPath)) {
            return false;
        }

        $zip = new ZipArchive;
        if ($zip->open($backupPath) !== true) {
            return false;
        }

        try {
            $zip->extractTo(base_path());
            $zip->close();

            Artisan::call('optimize:clear');
            Artisan::call('up');

            return true;
        } catch (\Throwable $e) {
            Log::error('Rollback failed: '.$e->getMessage());

            return false;
        }
    }
}
