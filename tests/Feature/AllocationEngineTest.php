<?php

use App\Domain\Scheduling\DTOs\ResolvedPolicyDto;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Scheduling\Services\AllocationEngine;
use App\Domain\Scheduling\Services\ConflictDetectionService;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->connection = ZoomConnection::create([
        'name' => 'Main Account',
        'account_id' => 'zoom-acc-1',
        'client_id' => 'zoom-client-1',
        'client_secret' => 'zoom-secret-1',
        'enabled' => true,
    ]);

    $this->userA = ZoomUser::create([
        'connection_id' => $this->connection->id,
        'zoom_user_id' => 'zm_user_A',
        'email' => 'zoomA@university.edu',
        'synced_at' => now(),
    ]);

    $this->resourceA = ZoomResource::create([
        'zoom_user_id' => $this->userA->id,
        'managed' => true,
        'status' => 'active',
        'participant_capacity' => 100,
        'priority' => 10,
    ]);

    $this->userB = ZoomUser::create([
        'connection_id' => $this->connection->id,
        'zoom_user_id' => 'zm_user_B',
        'email' => 'zoomB@university.edu',
        'synced_at' => now(),
    ]);

    $this->resourceB = ZoomResource::create([
        'zoom_user_id' => $this->userB->id,
        'managed' => true,
        'status' => 'active',
        'participant_capacity' => 100,
        'priority' => 20,
    ]);

    $this->pool = ResourcePool::create([
        'name' => 'General Pool',
        'code' => 'POOL_GEN',
        'pool_strategy' => 'least_hours_today',
        'is_active' => true,
    ]);

    $this->pool->resources()->attach([
        $this->resourceA->id => ['priority' => 10],
        $this->resourceB->id => ['priority' => 20],
    ]);

    $this->engine = new AllocationEngine(new ConflictDetectionService);
    $this->policy = new ResolvedPolicyDto(
        bufferMinutes: 15,
        minNoticeHours: 0,
        maxAdvanceDays: 90,
        maxDurationMinutes: 300,
        aiCompanionPolicy: 'ALLOWED',
        recordingMode: 'none'
    );
});

test('holding a resource sets occupied_until including buffer time', function () {
    $startsAt = Carbon::now()->addDays(2)->setTime(10, 0);
    $endsAt = (clone $startsAt)->addMinutes(60); // 10:00 to 11:00

    $reservation = $this->engine->holdResource(
        startsAt: $startsAt,
        endsAt: $endsAt,
        participantCount: 30,
        policy: $this->policy,
        pool: $this->pool
    );

    expect($reservation->status)->toBe('held')
        ->and($reservation->occupied_from->format('H:i'))->toBe('10:00')
        ->and($reservation->occupied_until->format('H:i'))->toBe('11:15') // 11:00 + 15m buffer
        ->and($reservation->hold_expires_at)->not->toBeNull();
});

test('buffer edge case: meeting starting exactly when previous buffer ends is permitted', function () {
    $day = Carbon::now()->addDays(2);
    $meeting1Start = (clone $day)->setTime(10, 0);
    $meeting1End = (clone $meeting1Start)->addMinutes(60); // 10:00 - 11:00, buffer until 11:15

    // Hold Meeting 1 specifically on Resource A
    $res1 = $this->engine->holdResource(
        startsAt: $meeting1Start,
        endsAt: $meeting1End,
        participantCount: 30,
        policy: $this->policy,
        preferredResource: $this->resourceA
    );
    expect($res1->resource_id)->toBe($this->resourceA->id);

    // Meeting 2 starting exactly at 11:15 on Resource A is allowed
    $meeting2Start = (clone $day)->setTime(11, 15);
    $meeting2End = (clone $meeting2Start)->addMinutes(45);

    $res2 = $this->engine->holdResource(
        startsAt: $meeting2Start,
        endsAt: $meeting2End,
        participantCount: 30,
        policy: $this->policy,
        preferredResource: $this->resourceA
    );
    expect($res2->resource_id)->toBe($this->resourceA->id);

    // Meeting 3 starting at 11:14 (overlapping buffer by 1 minute) is rejected
    $meeting3Start = (clone $day)->setTime(11, 14);
    $meeting3End = (clone $meeting3Start)->addMinutes(45);

    expect(function () use ($meeting3Start, $meeting3End) {
        $this->engine->holdResource(
            startsAt: $meeting3Start,
            endsAt: $meeting3End,
            participantCount: 30,
            policy: $this->policy,
            preferredResource: $this->resourceA
        );
    })->toThrow(RuntimeException::class, 'Allocation rejected');
});

test('external meeting reservation blocks allocation', function () {
    $day = Carbon::now()->addDays(2);
    $start = (clone $day)->setTime(14, 0);
    $end = (clone $start)->addMinutes(60);

    // External booking imported directly from Zoom calendar
    ResourceReservation::create([
        'resource_id' => $this->resourceA->id,
        'source' => 'external',
        'occupied_from' => $start,
        'occupied_until' => (clone $end)->addMinutes(15),
        'status' => 'confirmed',
    ]);

    // Requesting specifically Resource A should fail
    expect(function () use ($start, $end) {
        $this->engine->holdResource(
            startsAt: $start,
            endsAt: $end,
            participantCount: 20,
            policy: $this->policy,
            preferredResource: $this->resourceA
        );
    })->toThrow(RuntimeException::class, 'Allocation rejected');
});

test('pool strategy least_hours_today chooses the resource with fewer hours today', function () {
    $day = Carbon::now()->addDays(2);

    // Book Resource A for 2 hours (120 minutes)
    ResourceReservation::create([
        'resource_id' => $this->resourceA->id,
        'source' => 'zpm',
        'occupied_from' => (clone $day)->setTime(8, 0),
        'occupied_until' => (clone $day)->setTime(10, 0),
        'status' => 'confirmed',
    ]);

    // Resource B has 0 hours today
    $newStart = (clone $day)->setTime(14, 0);
    $newEnd = (clone $newStart)->addMinutes(60);

    $reservation = $this->engine->holdResource(
        startsAt: $newStart,
        endsAt: $newEnd,
        participantCount: 20,
        policy: $this->policy,
        pool: $this->pool,
        strategy: 'least_hours_today'
    );

    // Engine must pick Resource B because it has 0 hours vs 2 hours for Resource A
    expect($reservation->resource_id)->toBe($this->resourceB->id);
});

test('pool strategy priority chooses higher priority resource', function () {
    $day = Carbon::now()->addDays(2);
    $start = (clone $day)->setTime(15, 0);
    $end = (clone $start)->addMinutes(60);

    $reservation = $this->engine->holdResource(
        startsAt: $start,
        endsAt: $end,
        participantCount: 20,
        policy: $this->policy,
        pool: $this->pool,
        strategy: 'priority'
    );

    // Resource A priority is 10, Resource B priority is 20. (Lower number = higher priority)
    expect($reservation->resource_id)->toBe($this->resourceA->id);
});

test('releaseExpiredHolds automatically frees holds older than 10 minutes', function () {
    $pastStart = Carbon::now()->addDays(2)->setTime(10, 0);
    $pastEnd = (clone $pastStart)->addMinutes(60);

    $reservation = ResourceReservation::create([
        'resource_id' => $this->resourceA->id,
        'source' => 'zpm',
        'occupied_from' => $pastStart,
        'occupied_until' => (clone $pastEnd)->addMinutes(15),
        'status' => 'held',
        'hold_expires_at' => Carbon::now()->subMinutes(5), // Expired 5 minutes ago
    ]);

    $freedCount = $this->engine->releaseExpiredHolds();

    expect($freedCount)->toBe(1);
    $reservation->refresh();
    expect($reservation->status)->toBe('released');
});
