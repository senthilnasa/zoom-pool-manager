<?php

use App\Domain\Scheduling\DTOs\ResolvedPolicyDto;
use App\Domain\Scheduling\Models\BlackoutPeriod;
use App\Domain\Scheduling\Services\ConflictDetectionService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->connection = ZoomConnection::create([
        'name' => 'Main University Account',
        'account_id' => 'zoom-acc-1',
        'client_id' => 'zoom-client-1',
        'client_secret' => 'zoom-secret-1',
        'enabled' => true,
    ]);

    $this->zoomUser = ZoomUser::create([
        'connection_id' => $this->connection->id,
        'zoom_user_id' => 'zm_user_01',
        'email' => 'zoom01@university.edu',
        'user_type' => 2,
        'status' => 'active',
        'synced_at' => now(),
    ]);

    $this->resource = ZoomResource::create([
        'zoom_user_id' => $this->zoomUser->id,
        'managed' => true,
        'status' => 'active',
        'participant_capacity' => 100,
        'cloud_recording' => true,
    ]);
});

test('booking with insufficient notice triggers notice violation conflict', function () {
    $policy = new ResolvedPolicyDto(
        bufferMinutes: 10,
        minNoticeHours: 4,
        maxAdvanceDays: 90,
        maxDurationMinutes: 180,
        aiCompanionPolicy: 'ALLOWED',
        recordingMode: 'none'
    );

    $service = new ConflictDetectionService;

    // Booking in 1 hour (requires 4 hours)
    $startsAt = Carbon::now()->addHour();
    $endsAt = (clone $startsAt)->addMinutes(60);

    $result = $service->check(
        startsAt: $startsAt,
        endsAt: $endsAt,
        participantCount: 20,
        policy: $policy
    );

    expect($result->hasConflict)->toBeTrue()
        ->and($result->conflicts[0]['type'])->toBe('notice_violation');
});

test('booking beyond max advance days triggers advance booking violation', function () {
    $policy = new ResolvedPolicyDto(
        bufferMinutes: 10,
        minNoticeHours: 2,
        maxAdvanceDays: 30,
        maxDurationMinutes: 180,
        aiCompanionPolicy: 'ALLOWED',
        recordingMode: 'none'
    );

    $service = new ConflictDetectionService;

    // Booking 45 days in advance (limit is 30)
    $startsAt = Carbon::now()->addDays(45);
    $endsAt = (clone $startsAt)->addMinutes(60);

    $result = $service->check(
        startsAt: $startsAt,
        endsAt: $endsAt,
        participantCount: 20,
        policy: $policy
    );

    expect($result->hasConflict)->toBeTrue()
        ->and($result->conflicts[0]['type'])->toBe('advance_booking_violation');
});

test('blackout period overlapping meeting triggers blackout conflict', function () {
    $policy = new ResolvedPolicyDto(
        bufferMinutes: 10,
        minNoticeHours: 2,
        maxAdvanceDays: 90,
        maxDurationMinutes: 180,
        aiCompanionPolicy: 'ALLOWED',
        recordingMode: 'none'
    );

    $blackoutStart = Carbon::now()->addDays(5)->setTime(9, 0);
    $blackoutEnd = (clone $blackoutStart)->addHours(8);

    BlackoutPeriod::create([
        'name' => 'University Convocation Ceremony',
        'type' => 'ceremony',
        'starts_at' => $blackoutStart,
        'ends_at' => $blackoutEnd,
        'reason' => 'Campus network maintenance and convocation live stream',
    ]);

    $service = new ConflictDetectionService;

    // Meeting during blackout
    $startsAt = (clone $blackoutStart)->addHour();
    $endsAt = (clone $startsAt)->addMinutes(60);

    $result = $service->check(
        startsAt: $startsAt,
        endsAt: $endsAt,
        participantCount: 20,
        policy: $policy
    );

    expect($result->hasConflict)->toBeTrue()
        ->and($result->conflicts[0]['type'])->toBe('blackout_conflict')
        ->and($result->conflicts[0]['message'])->toContain('University Convocation Ceremony');
});

test('capacity exceeding available resources triggers capacity mismatch conflict', function () {
    $policy = new ResolvedPolicyDto(
        bufferMinutes: 10,
        minNoticeHours: 2,
        maxAdvanceDays: 90,
        maxDurationMinutes: 180,
        aiCompanionPolicy: 'ALLOWED',
        recordingMode: 'none'
    );

    $service = new ConflictDetectionService;

    $startsAt = Carbon::now()->addDays(2)->setTime(10, 0);
    $endsAt = (clone $startsAt)->addMinutes(60);

    // Requesting 250 participants when resource only holds 100
    $result = $service->check(
        startsAt: $startsAt,
        endsAt: $endsAt,
        participantCount: 250,
        policy: $policy
    );

    expect($result->hasConflict)->toBeTrue()
        ->and($result->conflicts[0]['type'])->toBe('capacity_mismatch');
});

test('bypassNoticeConstraints allows booking within minimum notice window without conflict', function () {
    $policy = new ResolvedPolicyDto(
        bufferMinutes: 10,
        minNoticeHours: 4,
        maxAdvanceDays: 90,
        maxDurationMinutes: 180,
        aiCompanionPolicy: 'ALLOWED',
        recordingMode: 'none'
    );

    $service = new ConflictDetectionService;

    // Booking in 30 minutes (violates 4h policy normally)
    $startsAt = Carbon::now()->addMinutes(30);
    $endsAt = (clone $startsAt)->addMinutes(60);

    $result = $service->check(
        startsAt: $startsAt,
        endsAt: $endsAt,
        participantCount: 20,
        policy: $policy,
        bypassNoticeConstraints: true
    );

    expect($result->hasConflict)->toBeFalse();
});

test('requester with Super Administrator role automatically bypasses lead time restrictions', function () {
    Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);

    $admin = User::create([
        'name' => 'Super Admin User',
        'email' => 'superadmin@univ.edu',
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);
    $admin->assignRole('Super Administrator');

    $policy = new ResolvedPolicyDto(
        bufferMinutes: 10,
        minNoticeHours: 6,
        maxAdvanceDays: 90,
        maxDurationMinutes: 180,
        aiCompanionPolicy: 'ALLOWED',
        recordingMode: 'none'
    );

    $service = new ConflictDetectionService;

    // Admin books 15 minutes before meeting starts
    $startsAt = Carbon::now()->addMinutes(15);
    $endsAt = (clone $startsAt)->addMinutes(45);

    $result = $service->check(
        startsAt: $startsAt,
        endsAt: $endsAt,
        participantCount: 10,
        policy: $policy,
        requester: $admin
    );

    expect($result->hasConflict)->toBeFalse();
});

test('requester with configured exempt role automatically bypasses lead time restrictions', function () {
    Role::firstOrCreate(['name' => 'VIP Faculty', 'guard_name' => 'web']);

    $vip = User::create([
        'name' => 'Distinguished Prof',
        'email' => 'vip@univ.edu',
        'password' => bcrypt('secret'),
        'is_active' => true,
    ]);
    $vip->assignRole('VIP Faculty');

    Setting::set('org.lead_time_exempt_roles', ['VIP Faculty']);

    $policy = new ResolvedPolicyDto(
        bufferMinutes: 10,
        minNoticeHours: 12,
        maxAdvanceDays: 90,
        maxDurationMinutes: 180,
        aiCompanionPolicy: 'ALLOWED',
        recordingMode: 'none'
    );

    $service = new ConflictDetectionService;

    // VIP books 30 minutes before meeting starts
    $startsAt = Carbon::now()->addMinutes(30);
    $endsAt = (clone $startsAt)->addMinutes(60);

    $result = $service->check(
        startsAt: $startsAt,
        endsAt: $endsAt,
        participantCount: 10,
        policy: $policy,
        requester: $vip
    );

    expect($result->hasConflict)->toBeFalse();
});
