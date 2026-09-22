<?php

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
