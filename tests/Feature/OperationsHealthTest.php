<?php

namespace Tests\Feature;

use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $permHealth = Permission::create(['name' => 'health.view', 'guard_name' => 'web']);
        $role->givePermissionTo($permHealth);

        $department = Department::create(['name' => 'IT Operations', 'code' => 'IT']);

        $this->adminUser = User::create([
            'name' => 'Ops Engineer',
            'email' => 'ops@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');
    }

    public function test_admin_health_json_endpoint_returns_structured_diagnostics(): void
    {
        $response = $this->getJson(route('admin.health'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'status',
            'app' => ['name', 'version', 'installed', 'environment'],
            'checks' => [
                'database' => ['status', 'driver', 'version'],
                'zoom_connections' => ['status', 'connections'],
                'scheduler' => ['status'],
                'queue' => ['status', 'pending_jobs', 'failed_jobs'],
                'webhooks' => ['status', 'total_events'],
                'mail' => ['status', 'failed_last_24h'],
                'storage' => ['status', 'writable'],
            ],
            'checked_at',
        ]);
    }

    public function test_admin_health_dashboard_view_renders_for_authenticated_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.health'));

        $response->assertStatus(200);
        $response->assertSee('Operations & System Health', false);
        $response->assertSee('Database');
        $response->assertSee('Queue Worker');
    }

    public function test_diagnostic_test_endpoints_execute_cleanly(): void
    {
        $responseDb = $this->actingAs($this->adminUser)
            ->postJson(route('admin.health.test', 'database'));
        $responseDb->assertStatus(200);
        $responseDb->assertJson(['success' => true]);

        $responseQueue = $this->actingAs($this->adminUser)
            ->postJson(route('admin.health.test', 'queue'));
        $responseQueue->assertStatus(200);
        $responseQueue->assertJson(['success' => true]);
    }

    public function test_system_health_check_console_command_records_heartbeat(): void
    {
        $this->artisan('zpm:health:check')
            ->assertSuccessful();

        $heartbeat = Setting::get('scheduler.last_heartbeat_at');
        $this->assertNotNull($heartbeat);
    }
}
