<?php

namespace Tests\Feature;

use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleManagementAndAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed permissions for test environment
        $permissions = [
            'meeting.view',
            'meeting.view_any',
            'meeting.create',
            'meeting.edit',
            'meeting.cancel',
            'meeting.approve',
            'recording.view',
            'recording.download',
            'user.view',
            'user.manage',
            'settings.view',
            'settings.manage',
        ];

        foreach ($permissions as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Super Administrator',
            'email' => 'admin@krea.edu.in',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');
    }

    public function test_roles_endpoint_ensures_and_returns_core_roles(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/spa/roles');

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'roles' => [
                '*' => ['id', 'name', 'description', 'is_system', 'users_count', 'permissions'],
            ],
            'core_roles',
        ]);

        $roles = collect($res->json('roles'));
        $roleNames = $roles->pluck('name')->toArray();

        $this->assertContains('Super Admin', $roleNames);
        $this->assertContains('Approval', $roleNames);
        $this->assertContains('User', $roleNames);

        $superAdmin = $roles->firstWhere('name', 'Super Admin');
        $this->assertTrue($superAdmin['is_system']);
    }

    public function test_permissions_matrix_returns_grouped_modules_and_actions(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/spa/roles/permissions-matrix');

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'matrix' => [
                'meetings' => ['name', 'description', 'actions'],
                'recordings' => ['name', 'description', 'actions'],
                'resources' => ['name', 'description', 'actions'],
                'governance' => ['name', 'description', 'actions'],
                'users' => ['name', 'description', 'actions'],
                'system' => ['name', 'description', 'actions'],
            ],
        ]);

        $meetingActions = $res->json('matrix.meetings.actions');
        $this->assertNotEmpty($meetingActions);
        $this->assertContains('meeting.view', array_column($meetingActions, 'key'));
    }

    public function test_custom_role_creation_and_permission_assignment(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/spa/roles', [
            'name' => 'Faculty Exam Proctor',
            'description' => 'Authorized to monitor examinations and view meeting recordings.',
            'permissions' => ['meeting.view', 'recording.view'],
        ]);

        $res->assertStatus(201);
        $res->assertJson([
            'success' => true,
            'role' => [
                'name' => 'Faculty Exam Proctor',
                'description' => 'Authorized to monitor examinations and view meeting recordings.',
                'is_system' => false,
            ],
        ]);

        $roleId = $res->json('role.id');
        $role = Role::findById($roleId, 'web');
        $this->assertTrue($role->hasPermissionTo('meeting.view'));
        $this->assertTrue($role->hasPermissionTo('recording.view'));
        $this->assertFalse($role->hasPermissionTo('user.manage'));
    }

    public function test_custom_role_update(): void
    {
        $createRes = $this->actingAs($this->admin)->postJson('/spa/roles', [
            'name' => 'Teaching Fellow',
            'description' => 'Initial description',
            'permissions' => ['meeting.view'],
        ]);

        $roleId = $createRes->json('role.id');

        $updateRes = $this->actingAs($this->admin)->putJson("/spa/roles/{$roleId}", [
            'name' => 'Senior Teaching Fellow',
            'description' => 'Updated scope with recording download rights.',
            'permissions' => ['meeting.view', 'recording.download'],
        ]);

        $updateRes->assertStatus(200);
        $this->assertEquals('Senior Teaching Fellow', $updateRes->json('role.name'));
        $this->assertEquals('Updated scope with recording download rights.', $updateRes->json('role.description'));

        $role = Role::findById($roleId, 'web');
        $this->assertTrue($role->hasPermissionTo('recording.download'));
    }

    public function test_custom_role_deletion_and_protection_of_system_roles(): void
    {
        $this->actingAs($this->admin)->getJson('/spa/roles');
        $superAdminRole = Role::findByName('Super Admin', 'web');

        // Cannot delete core system role
        $deleteSuperAdmin = $this->actingAs($this->admin)->deleteJson("/spa/roles/{$superAdminRole->id}");
        $deleteSuperAdmin->assertStatus(422);
        $deleteSuperAdmin->assertJson(['success' => false]);

        // Create a custom role
        $createRes = $this->actingAs($this->admin)->postJson('/spa/roles', [
            'name' => 'Temporary Auditor',
            'description' => 'Auditor role',
        ]);
        $roleId = $createRes->json('role.id');

        // Assign to a user
        $auditorUser = User::create([
            'name' => 'Auditor Jane',
            'email' => 'auditor@krea.edu.in',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $auditorUser->assignRole('Temporary Auditor');

        // Attempting to delete when users are assigned must fail
        $deleteAssigned = $this->actingAs($this->admin)->deleteJson("/spa/roles/{$roleId}");
        $deleteAssigned->assertStatus(422);
        $this->assertStringContainsString('assigned to 1 active user(s)', $deleteAssigned->json('message'));

        // Unassign role
        $auditorUser->removeRole('Temporary Auditor');

        // Now deletion should succeed
        $deleteSuccess = $this->actingAs($this->admin)->deleteJson("/spa/roles/{$roleId}");
        $deleteSuccess->assertStatus(200);
        $deleteSuccess->assertJson(['success' => true]);
        $this->assertNull(Role::where('id', $roleId)->first());
    }

    public function test_user_permissions_endpoint_inspects_effective_permissions(): void
    {
        $user = User::create([
            'name' => 'Prof. Ramanujan',
            'email' => 'ramanujan@krea.edu.in',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);

        $customRole = Role::firstOrCreate(['name' => 'Math Faculty', 'guard_name' => 'web']);
        $customRole->syncPermissions(['meeting.view', 'meeting.create']);
        $user->assignRole('Math Faculty');

        $res = $this->actingAs($this->admin)->getJson("/spa/users/{$user->id}/permissions");

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'roles',
            'permissions',
        ]);

        $this->assertContains('Math Faculty', $res->json('roles'));
        $this->assertContains('meeting.view', $res->json('permissions'));
        $this->assertContains('meeting.create', $res->json('permissions'));
    }
}
