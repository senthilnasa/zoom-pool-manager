<?php

use App\Domain\Meetings\Policies\MeetingAuthorizationPolicy;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('seeder initializes all 9 roles and 36 permissions', function () {
    expect(Role::count())->toBe(9)
        ->and(Permission::count())->toBe(36);

    $superAdmin = Role::findByName('super_admin');
    expect($superAdmin->permissions()->count())->toBe(36);

    $auditor = Role::findByName('auditor');
    expect($auditor->hasPermissionTo('audit.view'))->toBeTrue()
        ->and($auditor->hasPermissionTo('meeting.create'))->toBeFalse();
});

test('anti-self-approval rule strictly prevents users from approving their own meetings', function () {
    $approverRole = Role::findByName('approver');

    $user = User::create([
        'name' => 'Approver John',
        'email' => 'john.approver@example.com',
        'password' => bcrypt('secret123'),
        'is_active' => true,
    ]);
    $user->assignRole($approverRole);

    $policy = new MeetingAuthorizationPolicy;

    // 1. Meeting where user is requester
    $meeting1 = (object) [
        'id' => 1,
        'requester_user_id' => $user->id,
        'owner_user_id' => 999,
        'department_id' => null,
    ];
    expect($policy->approve($user, $meeting1))->toBeFalse();

    // 2. Meeting where user is owner
    $meeting2 = (object) [
        'id' => 2,
        'requester_user_id' => 999,
        'owner_user_id' => $user->id,
        'department_id' => null,
    ];
    expect($policy->approve($user, $meeting2))->toBeFalse();

    // 3. Meeting requested and owned by another user
    $meeting3 = (object) [
        'id' => 3,
        'requester_user_id' => 888,
        'owner_user_id' => 999,
        'department_id' => null,
    ];
    expect($policy->approve($user, $meeting3))->toBeTrue();
});

test('department administrator can only approve meetings within their own department', function () {
    $dept1 = Department::create(['name' => 'Computer Science', 'code' => 'CS']);
    $dept2 = Department::create(['name' => 'Mathematics', 'code' => 'MATH']);

    $deptAdmin = User::create([
        'name' => 'CS Dept Admin',
        'email' => 'admin.cs@example.com',
        'password' => bcrypt('secret123'),
        'department_id' => $dept1->id,
        'is_active' => true,
    ]);
    $deptAdmin->assignRole('dept_admin');

    $policy = new MeetingAuthorizationPolicy;

    // Meeting in CS department
    $csMeeting = (object) [
        'requester_user_id' => 500,
        'owner_user_id' => 501,
        'department_id' => $dept1->id,
    ];
    expect($policy->approve($deptAdmin, $csMeeting))->toBeTrue();

    // Meeting in Math department
    $mathMeeting = (object) [
        'requester_user_id' => 500,
        'owner_user_id' => 501,
        'department_id' => $dept2->id,
    ];
    expect($policy->approve($deptAdmin, $mathMeeting))->toBeFalse();
});
