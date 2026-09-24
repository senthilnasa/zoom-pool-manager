<?php

use App\Domain\Users\Models\User;
use App\Http\Middleware\EnsureInstalled;
use Database\Seeders\RolesAndPermissionsSeeder;
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

test('unauthenticated request to /spa/csrf-token returns valid csrf token and false authenticated status', function () {
    $response = $this->getJson(route('spa.csrf-token'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'csrf_token',
            'authenticated',
            'user',
        ])
        ->assertJson([
            'authenticated' => false,
            'user' => null,
        ]);

    expect($response->json('csrf_token'))->toBeString()->not->toBeEmpty();
});

test('authenticated request to /spa/csrf-token returns csrf token, authenticated true, and user details', function () {
    $user = User::create([
        'name' => 'Test User',
        'email' => 'test@example.edu',
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->getJson(route('spa.csrf-token'));

    $response->assertStatus(200)
        ->assertJson([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'name' => 'Test User',
                'email' => 'test@example.edu',
            ],
        ]);

    expect($response->json('csrf_token'))->toBeString()->not->toBeEmpty();
    $this->assertNotNull(session('_zpm_last_ping'));
});

test('csrf-token endpoint updates session last activity', function () {
    $user = User::create([
        'name' => 'Test User 2',
        'email' => 'test2@example.edu',
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->getJson('/spa/csrf-token');

    $response->assertOk();
    $response->assertSessionHas('_zpm_last_ping');
});
