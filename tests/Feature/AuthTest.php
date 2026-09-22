<?php

use App\Domain\Auth\Models\IdentityProvider;
use App\Domain\Auth\Models\LoginAttempt;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Http\Middleware\EnsureInstalled;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
    file_put_contents($lockFile, json_encode(['installed_at' => now()->toIso8601String()]));
});

afterEach(function () {
    $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
    if (file_exists($lockFile)) {
        unlink($lockFile);
    }
});

test('login page renders correctly with available identity providers', function () {
    IdentityProvider::create([
        'name' => 'University Google SSO',
        'driver' => 'google',
        'client_id' => 'google-client-id',
        'client_secret' => 'google-secret',
        'enabled' => true,
    ]);

    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('Sign In to ZPM')
        ->assertSee('University Google SSO');
});

test('sso-only mode hides local credentials form on /login and provides break-glass link', function () {
    Setting::set('auth.sso_only', true);

    IdentityProvider::create([
        'name' => 'Microsoft Entra ID',
        'driver' => 'microsoft',
        'client_id' => 'entra-client-id',
        'client_secret' => 'entra-secret',
        'enabled' => true,
    ]);

    $response = $this->get('/login');

    $response->assertStatus(200)
        ->assertSee('Use Break-Glass Login')
        ->assertDontSee('Sign in with password');
});

test('break-glass local login page renders dedicated emergency form', function () {
    $response = $this->get('/login/local');

    $response->assertStatus(200)
        ->assertSee('Break-Glass Administrator Emergency Access')
        ->assertSee('Administrator Email');
});

test('local login authenticates active user without MFA and creates session', function () {
    $user = User::create([
        'name' => 'Jane Staff',
        'email' => 'jane.staff@example.com',
        'password' => Hash::make('CorrectPassword123!'),
        'is_active' => true,
        'mfa_enabled' => false,
    ]);
    $user->assignRole('staff');

    $response = $this->post('/login', [
        'email' => 'jane.staff@example.com',
        'password' => 'CorrectPassword123!',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    expect(LoginAttempt::where('email', 'jane.staff@example.com')->where('was_successful', true)->exists())->toBeTrue();
});

test('failed login attempts are recorded and rate limited after 5 attempts', function () {
    $user = User::create([
        'name' => 'Target User',
        'email' => 'target@example.com',
        'password' => Hash::make('ActualPassword123!'),
        'is_active' => true,
        'mfa_enabled' => false,
    ]);

    // 5 failed attempts
    for ($i = 0; $i < 5; $i++) {
        $response = $this->post('/login', [
            'email' => 'target@example.com',
            'password' => 'WrongPassword!',
        ]);
        $response->assertSessionHasErrors('email');
    }

    // 6th attempt should be rate limited
    $response = $this->post('/login', [
        'email' => 'target@example.com',
        'password' => 'WrongPassword!',
    ]);

    $response->assertSessionHasErrors('email');
    $this->assertStringContainsString('Too many login attempts', session('errors')->first('email'));
    expect(LoginAttempt::where('email', 'target@example.com')->where('was_successful', false)->count())->toBe(6);
});

test('authenticated user can log out', function () {
    $user = User::create([
        'name' => 'Logout User',
        'email' => 'logout@example.com',
        'password' => Hash::make('Password123!'),
        'is_active' => true,
        'mfa_enabled' => false,
    ]);

    $this->actingAs($user);

    $response = $this->post('/logout');

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
