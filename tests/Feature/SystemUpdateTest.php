<?php

namespace Tests\Feature;

use App\Domain\System\Services\AppUpdateService;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Standard User', 'guard_name' => 'web']);
    }

    public function test_get_current_version_returns_configured_version(): void
    {
        config(['zpm.version' => '1.2.3']);
        $service = app(AppUpdateService::class);

        $this->assertEquals('1.2.3', $service->getCurrentVersion());
    }

    public function test_check_for_updates_detects_newer_version(): void
    {
        config(['zpm.version' => '1.0.0']);

        Http::fake([
            'https://api.github.com/repos/senthilnasa/zoom-pool-manager/releases/latest' => Http::response([
                'tag_name' => 'v1.1.0',
                'name' => 'Release v1.1.0',
                'body' => 'Added new features and optimizations.',
                'published_at' => '2026-10-01T12:00:00Z',
                'html_url' => 'https://github.com/senthilnasa/zoom-pool-manager/releases/tag/v1.1.0',
                'assets' => [
                    [
                        'name' => 'zpm-release-v1.1.0.zip',
                        'browser_download_url' => 'https://github.com/senthilnasa/zoom-pool-manager/releases/download/v1.1.0/zpm-release-v1.1.0.zip',
                    ],
                    [
                        'name' => 'zpm-release-v1.1.0.zip.sha256',
                        'browser_download_url' => 'https://github.com/senthilnasa/zoom-pool-manager/releases/download/v1.1.0/zpm-release-v1.1.0.zip.sha256',
                    ],
                ],
            ], 200),
        ]);

        $service = app(AppUpdateService::class);
        $result = $service->checkForUpdates(force: true);

        $this->assertTrue($result['update_available']);
        $this->assertEquals('1.1.0', $result['latest_version']);
        $this->assertEquals('1.0.0', $result['installed_version']);
        $this->assertEquals('Release v1.1.0', $result['release_name']);
        $this->assertNotNull($result['download_url']);
        $this->assertNull($result['error']);
    }

    public function test_check_for_updates_handles_api_failure_gracefully(): void
    {
        config(['zpm.version' => '1.0.0']);

        Http::fake([
            'https://api.github.com/repos/senthilnasa/zoom-pool-manager/releases/latest' => Http::response('Server error', 500),
        ]);

        $service = app(AppUpdateService::class);
        $result = $service->checkForUpdates(force: true);

        $this->assertFalse($result['update_available']);
        $this->assertEquals('1.0.0', $result['installed_version']);
        $this->assertNotNull($result['error']);
    }

    public function test_update_locking_mechanism_prevents_duplicate_runs(): void
    {
        $service = app(AppUpdateService::class);
        $service->releaseLock();

        $this->assertFalse($service->isLocked());
        $this->assertTrue($service->acquireLock());
        $this->assertTrue($service->isLocked());

        // Attempting to acquire lock again should fail
        $this->assertFalse($service->acquireLock());

        $service->releaseLock();
        $this->assertFalse($service->isLocked());
    }

    public function test_unauthorized_user_cannot_access_updates_page(): void
    {
        $user = User::create([
            'name' => 'Standard User',
            'email' => 'user@univ.edu',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $user->assignRole('Standard User');

        $response = $this->actingAs($user)->get(route('admin.system.updates.index'));
        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_updates_page(): void
    {
        $admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@univ.edu',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Administrator');

        Http::fake([
            'https://api.github.com/repos/senthilnasa/zoom-pool-manager/releases/latest' => Http::response([
                'tag_name' => 'v1.0.0',
                'name' => 'Release v1.0.0',
                'body' => 'Initial release.',
                'assets' => [],
            ], 200),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.system.updates.index'));
        $response->assertStatus(200);
        $response->assertSee('System Updates');
        $response->assertSee('Senthil Nasa');
    }

    public function test_version_artisan_command(): void
    {
        $this->artisan('zpm:version')
            ->expectsOutputToContain('Zoom Pool Manager')
            ->expectsOutputToContain('Senthil Nasa')
            ->assertExitCode(0);
    }

    public function test_update_check_artisan_command(): void
    {
        Http::fake([
            'https://api.github.com/repos/senthilnasa/zoom-pool-manager/releases/latest' => Http::response([
                'tag_name' => 'v1.0.0',
                'name' => 'Release v1.0.0',
                'body' => 'Initial release.',
                'assets' => [],
            ], 200),
        ]);

        $this->artisan('zpm:update', ['--check' => true])
            ->expectsOutputToContain('Zoom Pool Manager Update Manager')
            ->assertExitCode(0);
    }
}
