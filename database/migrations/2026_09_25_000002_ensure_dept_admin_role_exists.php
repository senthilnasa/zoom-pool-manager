<?php

use App\Domain\Users\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Ensure both dept_admin and department_admin exist
        $deptAdmin = Role::firstOrCreate(
            ['name' => 'dept_admin', 'guard_name' => 'web'],
            [
                'description' => 'Department Administrator with scoped meeting approval and management permissions.',
                'is_system' => true,
            ]
        );

        $departmentAdmin = Role::firstOrCreate(
            ['name' => 'department_admin', 'guard_name' => 'web'],
            [
                'description' => 'Department Administrator with scoped meeting approval and management permissions.',
                'is_system' => true,
            ]
        );

        $permissions = [
            'meeting.create', 'meeting.view', 'meeting.edit', 'meeting.cancel', 'meeting.reschedule',
            'meeting.approve', 'meeting.book_on_behalf',
            'recording.view', 'recording.share',
            'resource.view',
            'workflow.view',
            'quota.manage',
            'user.view', 'user.manage',
        ];

        $validPermissions = Permission::where('guard_name', 'web')
            ->whereIn('name', $permissions)
            ->pluck('name');

        if ($validPermissions->isNotEmpty()) {
            $deptAdmin->syncPermissions($validPermissions);
            $departmentAdmin->syncPermissions($validPermissions);
        }

        // Also copy role assignments from department_admin to dept_admin if any users have department_admin
        $usersWithDepartmentAdmin = User::whereHas('roles', function ($q) {
            $q->where('name', 'department_admin');
        })->get();

        foreach ($usersWithDepartmentAdmin as $u) {
            if (! $u->hasRole('dept_admin')) {
                $u->assignRole('dept_admin');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe to keep roles
    }
};
