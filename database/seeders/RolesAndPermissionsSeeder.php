<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // All 36 Granular Permissions defined in docs/permissions.md
        $permissions = [
            // Meetings
            'meeting.create',
            'meeting.view',
            'meeting.view_any',
            'meeting.edit',
            'meeting.cancel',
            'meeting.reschedule',
            'meeting.approve',
            'meeting.allocate',
            'meeting.override',
            'meeting.book_on_behalf',
            'meeting.start_as_host',

            // Recordings
            'recording.view',
            'recording.view_any',
            'recording.manage',
            'recording.download',
            'recording.share',

            // Zoom Connections & Resources
            'zoom.view',
            'zoom.manage',
            'resource.view',
            'resource.manage',
            'pool.manage',

            // Governance & Templates
            'template.manage',
            'security_profile.manage',
            'workflow.view',
            'workflow.manage',
            'quota.manage',

            // Administration & Users
            'user.view',
            'user.manage',
            'settings.view',
            'settings.manage',
            'audit.view',
            'backup.manage',
            'api.manage',
            'health.view',
            'emergency.use',
            'privacy.manage',
        ];

        foreach ($permissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        // 1. Super Administrator (all permissions)
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // 2. IT Administrator
        $itAdmin = Role::firstOrCreate(['name' => 'it_admin', 'guard_name' => 'web']);
        $itAdmin->syncPermissions([
            'meeting.create', 'meeting.view', 'meeting.view_any', 'meeting.edit', 'meeting.cancel',
            'meeting.reschedule', 'meeting.approve', 'meeting.allocate', 'meeting.override',
            'meeting.book_on_behalf', 'meeting.start_as_host',
            'recording.view', 'recording.view_any', 'recording.manage', 'recording.download', 'recording.share',
            'zoom.view', 'zoom.manage',
            'resource.view', 'resource.manage', 'pool.manage',
            'template.manage', 'security_profile.manage',
            'workflow.view', 'workflow.manage', 'quota.manage',
            'user.view', 'user.manage',
            'settings.view',
            'audit.view', 'backup.manage', 'api.manage', 'health.view', 'emergency.use',
        ]);

        // 3. Meeting Administrator
        $meetingAdmin = Role::firstOrCreate(['name' => 'meeting_admin', 'guard_name' => 'web']);
        $meetingAdmin->syncPermissions([
            'meeting.create', 'meeting.view', 'meeting.view_any', 'meeting.edit', 'meeting.cancel',
            'meeting.reschedule', 'meeting.approve', 'meeting.allocate', 'meeting.override',
            'meeting.book_on_behalf', 'meeting.start_as_host',
            'recording.view', 'recording.view_any', 'recording.manage', 'recording.download', 'recording.share',
            'resource.view',
            'template.manage', 'security_profile.manage',
            'workflow.view', 'workflow.manage', 'quota.manage',
            'user.view',
        ]);

        // 4. Department Administrator (scoped to department)
        $deptAdmin = Role::firstOrCreate(['name' => 'dept_admin', 'guard_name' => 'web']);
        $deptAdminPermissions = [
            'meeting.create', 'meeting.view', 'meeting.edit', 'meeting.cancel', 'meeting.reschedule',
            'meeting.approve', 'meeting.book_on_behalf',
            'recording.view', 'recording.share',
            'resource.view',
            'workflow.view',
            'quota.manage',
            'user.view', 'user.manage',
        ];
        $deptAdmin->syncPermissions($deptAdminPermissions);

        $departmentAdmin = Role::firstOrCreate(['name' => 'department_admin', 'guard_name' => 'web']);
        $departmentAdmin->syncPermissions($deptAdminPermissions);

        // 5. Approver
        $approver = Role::firstOrCreate(['name' => 'approver', 'guard_name' => 'web']);
        $approver->syncPermissions([
            'meeting.create', 'meeting.view', 'meeting.approve',
            'resource.view',
        ]);

        // 6. Faculty
        $faculty = Role::firstOrCreate(['name' => 'faculty', 'guard_name' => 'web']);
        $faculty->syncPermissions([
            'meeting.create', 'meeting.view', 'meeting.edit', 'meeting.cancel', 'meeting.reschedule',
            'meeting.start_as_host',
            'recording.view', 'recording.download', 'recording.share',
        ]);

        // 7. Staff
        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        $staff->syncPermissions([
            'meeting.create', 'meeting.view', 'meeting.edit', 'meeting.cancel', 'meeting.reschedule',
            'meeting.start_as_host',
            'recording.view', 'recording.download', 'recording.share',
        ]);

        // 8. Viewer / Auditor
        $auditor = Role::firstOrCreate(['name' => 'auditor', 'guard_name' => 'web']);
        $auditor->syncPermissions([
            'meeting.view', 'meeting.view_any',
            'recording.view', 'recording.view_any',
            'zoom.view', 'resource.view',
            'workflow.view', 'user.view', 'settings.view', 'audit.view',
        ]);

        // 9. API Client (machine-to-machine, permissions assigned dynamically via API key scopes)
        Role::firstOrCreate(['name' => 'api_client', 'guard_name' => 'web']);
    }
}
