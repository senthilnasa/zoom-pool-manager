<?php

namespace Tests\Feature;

use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $department = Department::create(['name' => 'IT Operations', 'code' => 'IT']);

        $this->adminUser = User::create([
            'name' => 'Docs Admin',
            'email' => 'docsadmin@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');
    }

    public function test_api_documentation_ui_renders_successfully(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/docs/api');

        $response->assertStatus(200);
    }

    public function test_openapi_specification_json_endpoint_returns_valid_json(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/docs/api.json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'openapi',
            'info' => ['title', 'version'],
            'paths',
        ]);
    }
}
