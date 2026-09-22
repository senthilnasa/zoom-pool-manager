<?php

use App\Domain\Scheduling\Models\BookingPolicy;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Scheduling\Services\EffectivePolicyResolver;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('organization default policy is resolved when no overrides are present', function () {
    Setting::set('org.min_buffer_minutes', 10);
    Setting::set('org.default_buffer_minutes', 15);
    Setting::set('org.min_notice_hours', 2);
    Setting::set('org.max_advance_days', 90);
    Setting::set('org.max_duration_minutes', 300);

    $resolver = new EffectivePolicyResolver;
    $policy = $resolver->resolve();

    expect($policy->bufferMinutes)->toBe(15)
        ->and($policy->minNoticeHours)->toBe(2)
        ->and($policy->maxAdvanceDays)->toBe(90)
        ->and($policy->maxDurationMinutes)->toBe(300)
        ->and($policy->aiCompanionPolicy)->toBe('ALLOWED');
});

test('security profile enforces disabled ai companion and overrides lower layers', function () {
    $profile = SecurityProfile::create([
        'name' => 'Confidential Exam',
        'code' => 'CONF_EXAM',
        'settings' => [
            'ai_companion' => 'DISABLED',
            'passcode' => true,
            'waiting_room' => true,
        ],
    ]);

    $template = MeetingTemplate::create([
        'name' => 'Exam Template',
        'code' => 'T_EXAM',
        'ai_companion_policy' => 'ALLOWED',
    ]);

    $resolver = new EffectivePolicyResolver;
    $policy = $resolver->resolve(
        securityProfile: $profile,
        template: $template,
        requestInput: ['ai_companion_policy' => 'REQUIRED']
    );

    // Profile level DISABLED overrides template and request level
    expect($policy->aiCompanionPolicy)->toBe('DISABLED')
        ->and($policy->securitySettings['waiting_room'])->toBeTrue();
});

test('department policy can restrict notice and buffer limits further', function () {
    Setting::set('org.min_buffer_minutes', 10);
    Setting::set('org.min_notice_hours', 2);
    Setting::set('org.max_advance_days', 90);

    $dept = Department::create(['name' => 'Physics Department', 'code' => 'PHYS']);

    BookingPolicy::create([
        'name' => 'Physics High Demand Policy',
        'department_id' => $dept->id,
        'min_notice_hours' => 24, // Requires 24h notice instead of 2h
        'max_advance_days' => 30, // Restricts advance booking to 30 days
        'min_buffer_minutes' => 20, // Requires 20m buffer instead of 10m
        'default_buffer_minutes' => 20,
        'max_duration_minutes' => 120,
    ]);

    $resolver = new EffectivePolicyResolver;
    $policy = $resolver->resolve(department: $dept);

    expect($policy->minNoticeHours)->toBe(24)
        ->and($policy->maxAdvanceDays)->toBe(30)
        ->and($policy->bufferMinutes)->toBe(20)
        ->and($policy->maxDurationMinutes)->toBe(120);
});

test('privileged administrator roles bypass notice limits', function () {
    Setting::set('org.min_notice_hours', 24);

    $admin = User::create([
        'name' => 'IT Super Admin',
        'email' => 'admin.it@example.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $admin->assignRole('super_admin');

    $resolver = new EffectivePolicyResolver;
    $policy = $resolver->resolve(user: $admin);

    expect($policy->minNoticeHours)->toBe(0)
        ->and($policy->maxAdvanceDays)->toBe(365);
});

test('user request cannot reduce buffer below organization minimum', function () {
    Setting::set('org.min_buffer_minutes', 15);
    Setting::set('org.default_buffer_minutes', 15);

    $resolver = new EffectivePolicyResolver;
    $policy = $resolver->resolve(requestInput: ['buffer_minutes' => 5]);

    // Request asked for 5, but org minimum is 15
    expect($policy->bufferMinutes)->toBe(15);
});
