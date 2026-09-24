<?php

namespace App\Domain\Auth\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Users\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleManagementService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Ensure the 3 core default roles exist in the database with standard permissions.
     */
    public function ensureCoreRoles(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Super Admin
        $superAdmin = Role::firstOrCreate(
            ['name' => 'Super Admin', 'guard_name' => 'web'],
            [
                'description' => 'Full institutional administrative access and complete system control.',
                'is_system' => true,
            ]
        );
        if (! (bool) $superAdmin->getAttribute('is_system')) {
            $superAdmin->update([
                'is_system' => true,
                'description' => $superAdmin->getAttribute('description') ?: 'Full institutional administrative access and complete system control.',
            ]);
        }
        $allPermissions = Permission::where('guard_name', 'web')->get();
        if ($allPermissions->isNotEmpty() && $superAdmin->permissions()->count() === 0) {
            $superAdmin->syncPermissions($allPermissions);
        }

        // 2. Approval
        $approval = Role::firstOrCreate(
            ['name' => 'Approval', 'guard_name' => 'web'],
            [
                'description' => 'Authorized to review, approve, and reject pooled meeting reservations.',
                'is_system' => true,
            ]
        );
        if (! (bool) $approval->getAttribute('is_system')) {
            $approval->update([
                'is_system' => true,
                'description' => $approval->getAttribute('description') ?: 'Authorized to review, approve, and reject pooled meeting reservations.',
            ]);
        }
        if ($approval->permissions()->count() === 0) {
            $approvalPermissions = ['meeting.view', 'meeting.view_any', 'meeting.approve', 'resource.view', 'recording.view'];
            $existing = Permission::where('guard_name', 'web')->whereIn('name', $approvalPermissions)->pluck('name');
            $approval->syncPermissions($existing);
        }

        // 3. User
        $userRole = Role::firstOrCreate(
            ['name' => 'User', 'guard_name' => 'web'],
            [
                'description' => 'Standard user account capable of booking pooled Zoom sessions and viewing personal recordings.',
                'is_system' => true,
            ]
        );
        if (! (bool) $userRole->getAttribute('is_system')) {
            $userRole->update([
                'is_system' => true,
                'description' => $userRole->getAttribute('description') ?: 'Standard user account capable of booking pooled Zoom sessions and viewing personal recordings.',
            ]);
        }
        if ($userRole->permissions()->count() === 0) {
            $userPermissions = ['meeting.create', 'meeting.view', 'meeting.cancel', 'recording.view', 'recording.share'];
            $existing = Permission::where('guard_name', 'web')->whereIn('name', $userPermissions)->pluck('name');
            $userRole->syncPermissions($existing);
        }
    }

    /**
     * Return the complete permission catalog grouped by modules and actions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPermissionsMatrix(): array
    {
        return [
            'meetings' => [
                'name' => 'Meetings & Schedules',
                'description' => 'Pooled Zoom meeting reservations, rescheduling, calendar access, and host keys.',
                'actions' => [
                    ['key' => 'meeting.view', 'label' => 'View Own Meetings', 'action' => 'View'],
                    ['key' => 'meeting.view_any', 'label' => 'View All Meetings', 'action' => 'View'],
                    ['key' => 'meeting.create', 'label' => 'Book Meetings', 'action' => 'Create'],
                    ['key' => 'meeting.edit', 'label' => 'Edit Meetings', 'action' => 'Edit'],
                    ['key' => 'meeting.cancel', 'label' => 'Cancel Meetings', 'action' => 'Delete'],
                    ['key' => 'meeting.reschedule', 'label' => 'Reschedule Meetings', 'action' => 'Edit'],
                    ['key' => 'meeting.approve', 'label' => 'Approve Reservations', 'action' => 'Approve'],
                    ['key' => 'meeting.allocate', 'label' => 'Manual Host Allocation', 'action' => 'Manage'],
                    ['key' => 'meeting.override', 'label' => 'Emergency Override', 'action' => 'Manage'],
                    ['key' => 'meeting.book_on_behalf', 'label' => 'Book on Behalf of Faculty', 'action' => 'Create'],
                    ['key' => 'meeting.start_as_host', 'label' => 'Claim / Start as Host', 'action' => 'Manage'],
                ],
            ],
            'recordings' => [
                'name' => 'Cloud Recordings & Media',
                'description' => 'Access and distribution of cloud session recordings and playback redirects.',
                'actions' => [
                    ['key' => 'recording.view', 'label' => 'View Own Recordings', 'action' => 'View'],
                    ['key' => 'recording.view_any', 'label' => 'View All Recordings', 'action' => 'View'],
                    ['key' => 'recording.manage', 'label' => 'Manage & Delete Recordings', 'action' => 'Manage'],
                    ['key' => 'recording.download', 'label' => 'Download Media Files', 'action' => 'Export'],
                    ['key' => 'recording.share', 'label' => 'Generate Shareable Links', 'action' => 'Manage'],
                ],
            ],
            'resources' => [
                'name' => 'Host Pools & Zoom Resources',
                'description' => 'Dedicated Zoom host account pools, priority routing, and account capacity.',
                'actions' => [
                    ['key' => 'resource.view', 'label' => 'View Zoom Resources', 'action' => 'View'],
                    ['key' => 'resource.manage', 'label' => 'Manage Resource Accounts', 'action' => 'Manage'],
                    ['key' => 'zoom.view', 'label' => 'View Zoom Integration', 'action' => 'View'],
                    ['key' => 'zoom.manage', 'label' => 'Manage Zoom Server OAuth', 'action' => 'Manage'],
                    ['key' => 'pool.manage', 'label' => 'Manage Resource Pools', 'action' => 'Manage'],
                ],
            ],
            'governance' => [
                'name' => 'Governance, Policies & Workflows',
                'description' => 'Approval workflows, quota allocations, security profiles, and scheduling policies.',
                'actions' => [
                    ['key' => 'workflow.view', 'label' => 'View Workflow Rules', 'action' => 'View'],
                    ['key' => 'workflow.manage', 'label' => 'Create & Edit Workflow Rules', 'action' => 'Manage'],
                    ['key' => 'template.manage', 'label' => 'Manage Meeting Templates', 'action' => 'Manage'],
                    ['key' => 'security_profile.manage', 'label' => 'Manage Security Profiles', 'action' => 'Manage'],
                    ['key' => 'quota.manage', 'label' => 'Manage Department Quotas', 'action' => 'Manage'],
                ],
            ],
            'users' => [
                'name' => 'User Directory & Departments',
                'description' => 'User provisioning, department structures, and role assignments.',
                'actions' => [
                    ['key' => 'user.view', 'label' => 'View User Directory', 'action' => 'View'],
                    ['key' => 'user.manage', 'label' => 'Create, Edit & Assign Users', 'action' => 'Manage'],
                ],
            ],
            'system' => [
                'name' => 'System Administration & Security',
                'description' => 'Platform settings, cryptographic audit logs, database backups, and health telemetry.',
                'actions' => [
                    ['key' => 'settings.view', 'label' => 'View System Settings', 'action' => 'View'],
                    ['key' => 'settings.manage', 'label' => 'Manage System Settings', 'action' => 'Manage'],
                    ['key' => 'audit.view', 'label' => 'View Audit Log Trail', 'action' => 'View'],
                    ['key' => 'backup.manage', 'label' => 'Manage Database Backups', 'action' => 'Manage'],
                    ['key' => 'api.manage', 'label' => 'Manage API Keys & Webhooks', 'action' => 'Manage'],
                    ['key' => 'health.view', 'label' => 'View Health Telemetry', 'action' => 'View'],
                    ['key' => 'emergency.use', 'label' => 'Trigger Emergency Override', 'action' => 'Manage'],
                    ['key' => 'privacy.manage', 'label' => 'Manage Privacy & Retention', 'action' => 'Manage'],
                ],
            ],
        ];
    }

    /**
     * Get all roles formatted with system flags, descriptions, user counts, and permissions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAllRoles(): array
    {
        $this->ensureCoreRoles();

        $roles = Role::with(['permissions'])
            ->withCount('users')
            ->orderByRaw("CASE 
                WHEN name = 'Super Admin' THEN 1 
                WHEN name = 'Approval' THEN 2 
                WHEN name = 'User' THEN 3 
                ELSE 4 
            END, name ASC")
            ->get();

        return $roles->map(function (Role $role) {
            $isCore = in_array($role->name, ['Super Admin', 'Approval', 'User'], true);

            return [
                'id' => $role->id,
                'name' => $role->name,
                'description' => $role->getAttribute('description') ?? ($isCore ? 'Core System Role' : 'Custom Role'),
                'is_system' => (bool) ($role->getAttribute('is_system') || $isCore),
                'users_count' => $role->users_count,
                'permissions' => $role->permissions->pluck('name')->values()->toArray(),
                'created_at' => $role->created_at?->toIso8601String(),
            ];
        })->toArray();
    }

    /**
     * Create a new custom role.
     *
     * @param  array<string>  $permissions
     * @return array<string, mixed>
     */
    public function createRole(string $name, ?string $description = null, array $permissions = []): array
    {
        $cleanName = trim($name);
        if (empty($cleanName)) {
            throw new Exception('Role name cannot be empty.');
        }

        if (Role::where('name', $cleanName)->where('guard_name', 'web')->exists()) {
            throw new Exception("A role named '{$cleanName}' already exists.");
        }

        /** @var Role $role */
        $role = Role::create([
            'name' => $cleanName,
            'guard_name' => 'web',
            'description' => $description ?: 'Custom user role.',
            'is_system' => false,
        ]);

        if (! empty($permissions)) {
            // Ensure permissions exist
            foreach ($permissions as $p) {
                Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            }
            $role->syncPermissions($permissions);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->auditService->log(
            event: 'role.created',
            auditable: $role instanceof Model ? $role : null,
            oldValues: [],
            newValues: ['name' => $cleanName, 'permissions_count' => count($permissions)]
        );

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->getAttribute('description'),
            'is_system' => false,
            'users_count' => 0,
            'permissions' => $role->permissions->pluck('name')->values()->toArray(),
        ];
    }

    /**
     * Update an existing role.
     *
     * @param  array{name?: string, description?: string, permissions?: array<string>}  $data
     * @return array<string, mixed>
     */
    public function updateRole(int|string $id, array $data): array
    {
        $role = Role::findOrFail($id);
        $isCore = in_array($role->name, ['Super Admin', 'Approval', 'User'], true);

        $oldName = $role->name;
        $updates = [];

        // If not a core role, allow renaming
        if (! empty($data['name']) && ! $isCore) {
            $newName = trim($data['name']);
            if ($newName !== $oldName) {
                if (Role::where('name', $newName)->where('guard_name', 'web')->where('id', '!=', $role->id)->exists()) {
                    throw new Exception("A role named '{$newName}' already exists.");
                }
                $updates['name'] = $newName;
            }
        }

        if (array_key_exists('description', $data)) {
            $updates['description'] = $data['description'];
        }

        if (! empty($updates)) {
            $role->update($updates);
        }

        // Update permissions if provided
        if (isset($data['permissions']) && is_array($data['permissions'])) {
            foreach ($data['permissions'] as $p) {
                Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
            }
            $role->syncPermissions($data['permissions']);
            app()[PermissionRegistrar::class]->forgetCachedPermissions();
        }

        $this->auditService->log(
            event: 'role.updated',
            auditable: $role,
            oldValues: ['name' => $oldName],
            newValues: ['name' => $role->name, 'permissions_count' => $role->permissions()->count()]
        );

        return [
            'id' => $role->id,
            'name' => $role->name,
            'description' => $role->getAttribute('description'),
            'is_system' => (bool) ($role->getAttribute('is_system') || $isCore),
            'users_count' => $role->users()->count(),
            'permissions' => $role->permissions()->pluck('name')->values()->toArray(),
        ];
    }

    /**
     * Delete a custom role if allowed.
     */
    public function deleteRole(int|string $id): void
    {
        $role = Role::findOrFail($id);
        $isCore = in_array($role->name, ['Super Admin', 'Approval', 'User'], true);

        if ((bool) $role->getAttribute('is_system') || $isCore) {
            throw new Exception("Core system role '{$role->name}' cannot be deleted.");
        }

        $assignedCount = $role->users()->count();
        if ($assignedCount > 0) {
            throw new Exception("Cannot delete role '{$role->name}' because it is assigned to {$assignedCount} active user(s). Reassign these users first.");
        }

        $roleName = $role->name;
        $role->delete();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->auditService->log(
            event: 'role.deleted',
            auditable: null,
            oldValues: ['name' => $roleName],
            newValues: []
        );
    }

    /**
     * Get effective permissions for a user.
     *
     * @return array<string, mixed>
     */
    public function getUserPermissions(User $user): array
    {
        $roles = $user->roles()->pluck('name')->values()->toArray();
        $permissions = $user->getAllPermissions()->pluck('name')->values()->toArray();

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
            'roles' => $roles,
            'permissions' => $permissions,
            'is_super_admin' => in_array('Super Admin', $roles, true) || in_array('super_admin', $roles, true),
        ];
    }
}
