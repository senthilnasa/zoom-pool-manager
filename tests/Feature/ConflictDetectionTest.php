<?php

use App\Domain\Scheduling\DTOs\ResolvedPolicyDto;
use App\Domain\Scheduling\Models\BlackoutPeriod;
use App\Domain\Scheduling\Services\ConflictDetectionService;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
