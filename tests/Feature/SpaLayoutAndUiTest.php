<?php

namespace Tests\Feature;

use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpaLayoutAndUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Standard User', 'guard_name' => 'web']);
    }

    public function test_layout_contains_senthil_nasa_attribution_and_theme_manager(): void
    {
        $user = User::create([
            'name' => 'Standard User',
            'email' => 'user@univ.edu',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $user->assignRole('Standard User');

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Made with ❤️ by Senthil Nasa');
        $response->assertSee('https://github.com/senthilnasa');
        $response->assertSee('themeManager()', false);
        $response->assertSee('zpm-spa.js');
        $response->assertSee('Primary Navigation');
    }

    public function test_ajax_update_check_endpoint_returns_json(): void
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
                'name' => 'v1.0.0',
                'body' => 'Changelog',
                'assets' => [],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.system.updates.check'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'installed_version',
            'latest_version',
            'update_available',
        ]);
    }
}
