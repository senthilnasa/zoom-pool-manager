<?php

use App\Domain\Auth\DTOs\UserIdentityDto;
use App\Domain\Auth\Models\IdentityProvider;
use App\Domain\Auth\Models\UserIdentity;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Http\Middleware\EnsureInstalled;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('sso login rejects email domains not in the allowed_domains whitelist', function () {
    $idp = IdentityProvider::create([
        'name' => 'Campus SSO',
        'driver' => 'google',
        'allowed_domains' => ['university.edu', 'college.edu'],
        'enabled' => true,
    ]);

    $authService = app(AuthService::class);
    $dto = new UserIdentityDto(
        externalId: 'ext-12345',
        email: 'attacker@malicious.com',
        name: 'Attacker User',
    );

    expect(function () use ($authService, $idp, $dto) {
        $authService->handleSsoUser($idp, $dto, '127.0.0.1', 'TestBrowser');
    })->toThrow(AuthorizationException::class, 'Email domain [@malicious.com] is not authorized');
});

test('sso login automatically provisions new user with role and department mappings', function () {
    $dept = Department::create(['name' => 'Computer Science', 'code' => 'CS']);

    $idp = IdentityProvider::create([
        'name' => 'Campus Entra ID',
        'driver' => 'microsoft',
        'allowed_domains' => ['university.edu'],
        'role_mapping' => [
            'FacultyAdGroup' => 'faculty',
            'DeptAdminAdGroup' => 'dept_admin',
        ],
        'department_mapping' => [
            'CS_Affiliation' => 'CS',
        ],
        'enabled' => true,
    ]);

    $authService = app(AuthService::class);
    $dto = new UserIdentityDto(
        externalId: 'entra-98765',
        email: 'alice.professor@university.edu',
        name: 'Alice Professor',
        groups: ['FacultyAdGroup', 'CS_Affiliation'],
    );

    $user = $authService->handleSsoUser($idp, $dto, '127.0.0.1', 'Mozilla/5.0');

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('alice.professor@university.edu')
        ->and($user->hasRole('faculty'))->toBeTrue()
        ->and($user->department_id)->toBe($dept->id);

    $identity = UserIdentity::where('external_id', 'entra-98765')->first();
    expect($identity)->not->toBeNull()
        ->and($identity->user_id)->toBe($user->id);
});

test('saml metadata endpoint outputs valid sp metadata xml', function () {
    $idp = IdentityProvider::create([
        'name' => 'Shibboleth SAML',
        'driver' => 'saml',
        'metadata_url' => 'https://idp.university.edu/idp/shibboleth',
        'certificate_primary' => 'PRIMARY_CERT_DATA',
        'enabled' => true,
    ]);

    $response = $this->get(route('auth.saml.metadata', ['provider' => $idp->public_id]));

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/samlmetadata+xml; charset=UTF-8')
        ->assertSee('md:EntityDescriptor', false)
        ->assertSee('AssertionConsumerService', false);
});
