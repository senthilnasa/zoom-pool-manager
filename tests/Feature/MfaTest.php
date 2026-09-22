<?php

use App\Domain\Auth\Models\MfaRecoveryCode;
use App\Domain\Auth\Models\MfaSecret;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('super admin is always challenged for two-factor authentication upon login', function () {
    $user = User::create([
        'name' => 'Super Administrator',
        'email' => 'superadmin@example.com',
        'password' => Hash::make('SuperPassword123!'),
        'is_active' => true,
        'mfa_enabled' => true,
    ]);
    $user->assignRole('super_admin');

    $response = $this->post('/login', [
        'email' => 'superadmin@example.com',
        'password' => 'SuperPassword123!',
    ]);

    $response->assertRedirect(route('auth.totp'));
    $this->assertGuest();
    expect(session('auth.mfa_user_id'))->toBe($user->id);
});

test('user can verify two-factor challenge using a valid 6-digit TOTP code', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey(32);

    $user = User::create([
        'name' => 'Staff User',
        'email' => 'staff@example.com',
        'password' => Hash::make('Secret12345!'),
        'is_active' => true,
        'mfa_enabled' => true,
    ]);
    $user->assignRole('staff');

    MfaSecret::create([
        'user_id' => $user->id,
        'secret' => $secret,
        'enrolled_at' => now(),
    ]);

    // Set MFA session
    $this->withSession(['auth.mfa_user_id' => $user->id]);

    $validCode = $google2fa->getCurrentOtp($secret);

    $response = $this->post('/login/totp', [
        'code' => $validCode,
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('user can verify two-factor challenge even when duplicate empty code parameter is submitted', function () {
    $google2fa = new Google2FA;
    $secret = $google2fa->generateSecretKey(32);

    $user = User::create([
        'name' => 'Staff User 2',
        'email' => 'staff2@example.com',
        'password' => Hash::make('Secret12345!'),
        'is_active' => true,
        'mfa_enabled' => true,
    ]);
    $user->assignRole('staff');

    MfaSecret::create([
        'user_id' => $user->id,
        'secret' => $secret,
        'enrolled_at' => now(),
    ]);

    $this->withSession(['auth.mfa_user_id' => $user->id]);

    $validCode = $google2fa->getCurrentOtp($secret);

    // Simulate duplicate parameter in raw payload: code=VALID&code=
    $response = $this->call(
        'POST',
        '/login/totp',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'],
        "code={$validCode}&code="
    );

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
});

test('user can verify two-factor challenge using a single-use recovery code and it is consumed', function () {
    $user = User::create([
        'name' => 'Faculty User',
        'email' => 'faculty@example.com',
        'password' => Hash::make('Secret12345!'),
        'is_active' => true,
        'mfa_enabled' => true,
    ]);
    $user->assignRole('faculty');

    $plainRecoveryCode = 'ABCD1-EFGH2';
    $cleanCode = 'ABCD1EFGH2';

    $recoveryRecord = MfaRecoveryCode::create([
        'user_id' => $user->id,
        'code_hash' => Hash::make($cleanCode),
        'used_at' => null,
    ]);

    $this->withSession(['auth.mfa_user_id' => $user->id]);

    // Submit with lowercase and hyphen
    $response = $this->post('/login/totp', [
        'code' => 'abcd1-efgh2',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $recoveryRecord->refresh();
    expect($recoveryRecord->used_at)->not->toBeNull();

    // Trying to use it again should fail
    auth()->logout();
    $authService = app(AuthService::class);
    $result = $authService->verifyTotpChallenge($user, 'abcd1-efgh2', '127.0.0.1', null);
    expect($result['success'])->toBeFalse();
});

test('break-glass artisan command resets admin password and clears MFA', function () {
    $user = User::create([
        'name' => 'Break Glass Admin',
        'email' => 'lockedout@example.com',
        'password' => Hash::make('OldPassword123!'),
        'is_active' => true,
        'mfa_enabled' => true,
    ]);
    $user->assignRole('super_admin');

    MfaSecret::create([
        'user_id' => $user->id,
        'secret' => 'SECRETKEY123',
    ]);

    $this->artisan('zpm:admin:reset', [
        'email' => 'lockedout@example.com',
        '--password' => 'NewEmergencyPass123!',
        '--reset-mfa' => true,
        '--reason' => 'Lost YubiKey token and recovery codes',
    ])->assertExitCode(0);

    $user->refresh();
    expect(Hash::check('NewEmergencyPass123!', (string) $user->password))->toBeTrue()
        ->and($user->mfa_enabled)->toBeFalse()
        ->and($user->mfaSecret)->toBeNull();
});
