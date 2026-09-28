<?php

use App\Domain\Installer\Services\InstallerService;
use App\Http\Middleware\EnsureInstalled;

beforeEach(function () {
    EnsureInstalled::$bypassForTesting = false;
});

afterEach(function () {
    EnsureInstalled::$bypassForTesting = null;
});

test('installer welcome page renders successfully', function () {
    $response = $this->get('/installer');
    $response->assertStatus(200)
        ->assertSee('Welcome to Zoom Pool Manager');
});

test('installer requirements page renders system checks', function () {
    $response = $this->get('/installer/requirements');
    $response->assertStatus(200)
        ->assertSee('Step 1: System Pre-Flight Check')
        ->assertSee('Required PHP Extensions');
});

test('installer database page renders credentials form', function () {
    $response = $this->get('/installer/database');
    $response->assertStatus(200)
        ->assertSee('Step 2: Database Configuration')
        ->assertSee('Test Connection');
});

test('installer admin mfa endpoint generates secret and qr code', function () {
    $response = $this->getJson('/installer/admin/mfa?email=test@example.edu');
    $response->assertStatus(200)
        ->assertJsonStructure([
            'secret',
            'qr_code',
            'recovery_codes',
        ]);
});

test('installer database test endpoint validates connection and returns json', function () {
    $response = $this->postJson('/installer/database/test', [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'zpm',
        'username' => 'invalid_user_zpm_nonexistent',
        'password' => 'invalid_pass_xyz',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => false,
        ]);
});

test('saveDatabaseConfig updates runtime database configuration and purges connection', function () {
    $envPath = app()->environmentFilePath();
    $origEnv = file_exists($envPath) ? file_get_contents($envPath) : null;
    $origMaria = config('database.connections.mariadb');
    $origMysql = config('database.connections.mysql');
    $origDefault = config('database.default');
    $service = app(InstallerService::class);

    try {
        $service->saveDatabaseConfig([
            'host' => '10.0.0.99',
            'port' => 3307,
            'database' => 'custom_zpm_db',
            'username' => 'custom_user',
            'password' => 'secret_pass_123',
        ]);

        expect(config('database.connections.mariadb.host'))->toBe('10.0.0.99');
        expect(config('database.connections.mariadb.port'))->toBe(3307);
        expect(config('database.connections.mariadb.database'))->toBe('custom_zpm_db');
        expect(config('database.connections.mariadb.username'))->toBe('custom_user');
        expect(config('database.connections.mariadb.password'))->toBe('secret_pass_123');

        expect(config('database.connections.mysql.host'))->toBe('10.0.0.99');
        expect(config('database.connections.mysql.database'))->toBe('custom_zpm_db');
    } finally {
        if ($origEnv !== null) {
            file_put_contents($envPath, $origEnv);
        }
        if ($origMaria) {
            config(['database.connections.mariadb' => $origMaria]);
        }
        if ($origMysql) {
            config(['database.connections.mysql' => $origMysql]);
        }
        config(['database.default' => $origDefault]);
        putenv("DB_CONNECTION={$origDefault}");
        $_ENV['DB_CONNECTION'] = $origDefault;
        $_SERVER['DB_CONNECTION'] = $origDefault;
        if ($origDefault === 'sqlite') {
            putenv('DB_DATABASE=:memory:');
            $_ENV['DB_DATABASE'] = ':memory:';
            $_SERVER['DB_DATABASE'] = ':memory:';
        }
        DB::purge('mariadb');
        DB::purge('mysql');
        DB::purge($origDefault);
        DB::reconnect($origDefault);
    }
});
