<?php

use App\Domain\Settings\Models\Setting;
use App\Http\Middleware\EnsureInstalled;
use Illuminate\Support\Facades\Artisan;

test('redirects root route to /installer when lock file and db flag are missing', function () {
    EnsureInstalled::$bypassForTesting = null;
    $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
    $hadLockFile = file_exists($lockFile);
    $lockContent = $hadLockFile ? file_get_contents($lockFile) : null;
    if ($hadLockFile) {
        @unlink($lockFile);
    }

    try {
        $response = $this->get('/');
        $response->assertRedirect(route('installer.welcome'));
    } finally {
        if ($hadLockFile && $lockContent !== null) {
            file_put_contents($lockFile, $lockContent);
        }
    }
});

test('blocks access to /installer when installed.lock exists', function () {
    EnsureInstalled::$bypassForTesting = null;
    $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
    $hadLockFile = file_exists($lockFile);
    $lockContent = $hadLockFile ? file_get_contents($lockFile) : null;
    file_put_contents($lockFile, json_encode(['installed_at' => now()->toIso8601String()]));

    try {
        $response = $this->get('/installer');
        $response->assertStatus(403);
    } finally {
        if ($hadLockFile && $lockContent !== null) {
            file_put_contents($lockFile, $lockContent);
        } elseif (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }
});

test('recognizes installation via DB flag even if lock file is missing', function () {
    EnsureInstalled::$bypassForTesting = null;
    $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
    $hadLockFile = file_exists($lockFile);
    $lockContent = $hadLockFile ? file_get_contents($lockFile) : null;
    if ($hadLockFile) {
        @unlink($lockFile);
    }

    // Set DB flag
    Artisan::call('migrate');
    Setting::set('installed_at', now()->toIso8601String());

    try {
        $response = $this->get('/');
        $response->assertRedirect(route('dashboard'));
        expect(file_exists($lockFile))->toBeTrue();
    } finally {
        if ($hadLockFile && $lockContent !== null) {
            file_put_contents($lockFile, $lockContent);
        } elseif (file_exists($lockFile)) {
            @unlink($lockFile);
        }
    }
});
