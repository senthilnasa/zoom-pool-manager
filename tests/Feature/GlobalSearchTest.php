<?php

namespace Tests\Feature;

use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@krea.edu.in',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');
    }

    public function test_global_search_returns_empty_results_for_blank_or_short_queries(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/spa/search?q=a');
        $res->assertStatus(200);
        $res->assertJson([
            'results' => [
                'modules' => [],
                'users' => [],
                'meetings' => [],
                'pools' => [],
            ],
            'total_matches' => 0,
        ]);
    }

    public function test_global_search_matches_application_modules(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/spa/search?q=recording');
        $res->assertStatus(200);

        $modules = collect($res->json('results.modules'));
        $this->assertTrue($modules->contains('title', 'Cloud Recordings'));
    }

    public function test_global_search_finds_users_by_name_and_email(): void
    {
        User::create([
            'name' => 'Aryabhata Astronomer',
            'email' => 'aryabhata@krea.edu.in',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->admin)->getJson('/spa/search?q=Aryabhata');
        $res->assertStatus(200);

        $users = collect($res->json('results.users'));
        $matchedUser = $users->firstWhere('name', 'Aryabhata Astronomer');

        $this->assertNotNull($matchedUser);
        $this->assertEquals('Aryabhata Astronomer', $matchedUser['name']);
        $this->assertEquals('aryabhata@krea.edu.in', $matchedUser['email']);
        $this->assertStringContainsString('/profile', $matchedUser['path']);
        $this->assertStringContainsString('/profile', $matchedUser['profile_path']);
        $this->assertStringContainsString('/app/users?search=', $matchedUser['directory_path']);
    }

    public function test_global_search_finds_pools(): void
    {
        ResourcePool::create([
            'name' => 'Executive Boardroom VIP Pool',
            'code' => 'EXEC-VIP',
            'description' => 'Dedicated licenses for executive sessions',
            'pool_strategy' => 'balanced',
            'is_active' => true,
        ]);

        $res = $this->actingAs($this->admin)->getJson('/spa/search?q=Executive');
        $res->assertStatus(200);

        $pools = collect($res->json('results.pools'));
        $matchedPool = $pools->firstWhere('name', 'Executive Boardroom VIP Pool');

        $this->assertNotNull($matchedPool);
        $this->assertEquals('Executive Boardroom VIP Pool', $matchedPool['name']);
    }
}
