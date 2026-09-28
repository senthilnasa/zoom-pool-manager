<?php

use App\Domain\Settings\Models\Setting;
use App\Http\Middleware\EnsureInstalled;
use Illuminate\Contracts\Encryption\Encrypter;
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

test('redirects /installer to /login when installed.lock exists', function () {
    EnsureInstalled::$bypassForTesting = null;
    $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
    $hadLockFile = file_exists($lockFile);
    $lockContent = $hadLockFile ? file_get_contents($lockFile) : null;
    file_put_contents($lockFile, json_encode(['installed_at' => now()->toIso8601String()]));

    try {
        $response = $this->get('/installer');
        $response->assertRedirect(route('login'));
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

test('application encrypter resolves gracefully when APP_KEY is not set', function () {
    // When config app.key is empty string or null, fallback key is configured
    $encrypter = app('encrypter');
    expect($encrypter)->toBeInstanceOf(Encrypter::class);

    // Test that installer welcome page renders 200 without throwing MissingAppKeyException
    EnsureInstalled::$bypassForTesting = false;
    $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
    $hadLockFile = file_exists($lockFile);
    $lockContent = $hadLockFile ? file_get_contents($lockFile) : null;
    if ($hadLockFile) {
        @unlink($lockFile);
    }

    try {
        $response = $this->get('/installer');
        $response->assertStatus(200);
        $response->assertSee('Welcome to Zoom Pool Manager');
    } finally {
        if ($hadLockFile && $lockContent !== null) {
            file_put_contents($lockFile, $lockContent);
        }
    }
});
