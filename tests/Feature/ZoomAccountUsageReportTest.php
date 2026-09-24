<?php

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use App\Domain\Zoom\Services\ZoomAccountUsageReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->adminRole = Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
    $this->admin = User::create([
        'name' => 'Test Admin',
        'email' => 'testadmin@example.com',
        'password' => bcrypt('password123'),
        'is_active' => true,
    ]);
    $this->admin->assignRole($this->adminRole);

    $this->connection = ZoomConnection::create([
        'name' => 'Main Connection',
        'account_id' => 'zoom-acc-1',
        'client_id' => 'zoom-client-1',
        'client_secret' => 'zoom-secret-1',
        'enabled' => true,
    ]);

    // Create 3 Zoom host accounts
    $this->user1 = ZoomUser::create([
        'connection_id' => $this->connection->id,
        'zoom_user_id' => 'host_1',
        'first_name' => 'Alice',
        'last_name' => 'Smith',
        'email' => 'alice@company.com',
        'synced_at' => now(),
    ]);
    $this->res1 = ZoomResource::create([
        'zoom_user_id' => $this->user1->id,
        'managed' => true,
        'status' => 'active',
        'participant_capacity' => 100,
        'cloud_recording' => true,
    ]);

    $this->user2 = ZoomUser::create([
        'connection_id' => $this->connection->id,
        'zoom_user_id' => 'host_2',
        'first_name' => 'Bob',
        'last_name' => 'Jones',
        'email' => 'bob@company.com',
        'synced_at' => now(),
    ]);
    $this->res2 = ZoomResource::create([
        'zoom_user_id' => $this->user2->id,
        'managed' => true,
        'status' => 'active',
        'participant_capacity' => 300,
        'ai_companion' => true,
    ]);

    $this->user3 = ZoomUser::create([
        'connection_id' => $this->connection->id,
        'zoom_user_id' => 'host_3',
        'first_name' => 'Charlie',
        'last_name' => 'Brown',
        'email' => 'charlie@company.com',
        'synced_at' => now(),
    ]);
    $this->res3 = ZoomResource::create([
        'zoom_user_id' => $this->user3->id,
        'managed' => true,
        'status' => 'active',
        'participant_capacity' => 500,
    ]);

    $this->pool = ResourcePool::create([
        'name' => 'Primary Pool',
        'code' => 'POOL_PRIM',
        'pool_strategy' => 'least_hours_today',
        'is_active' => true,
    ]);
    $this->pool->resources()->attach([$this->res1->id, $this->res2->id]);
});

test('it accurately calculates peak concurrency for overlapping meetings', function () {
    $now = Carbon::now()->startOfDay()->addHours(10); // 10:00 AM

    // Meeting 1 on Res 1: 10:00 to 11:30
    Meeting::create([
        'title' => 'Meeting 1',
        'zoom_resource_id' => $this->res1->id,
        'starts_at' => $now->copy(),
        'ends_at' => $now->copy()->addMinutes(90),
        'status' => 'ended',
        'requester_user_id' => $this->admin->id,
        'owner_user_id' => $this->admin->id,
        'meeting_type' => 'scheduled',
    ]);

    // Meeting 2 on Res 2: 10:30 to 12:00 (overlaps with Meeting 1 from 10:30 to 11:30 -> Concurrency = 2)
    Meeting::create([
        'title' => 'Meeting 2',
        'zoom_resource_id' => $this->res2->id,
        'starts_at' => $now->copy()->addMinutes(30),
        'ends_at' => $now->copy()->addMinutes(120),
        'status' => 'ended',
        'requester_user_id' => $this->admin->id,
        'owner_user_id' => $this->admin->id,
        'meeting_type' => 'scheduled',
    ]);

    // Meeting 3 on Res 1: 14:00 to 15:00 (no overlap with meeting 1 or 2)
    Meeting::create([
        'title' => 'Meeting 3',
        'zoom_resource_id' => $this->res1->id,
        'starts_at' => $now->copy()->addHours(4),
        'ends_at' => $now->copy()->addHours(5),
        'status' => 'ended',
        'requester_user_id' => $this->admin->id,
        'owner_user_id' => $this->admin->id,
        'meeting_type' => 'scheduled',
    ]);

    $service = app(ZoomAccountUsageReportService::class);
    $report = $service->generateReport([
        'start_date' => $now->copy()->subDay()->format('Y-m-d'),
        'end_date' => $now->copy()->addDay()->format('Y-m-d'),
    ]);

    expect($report['summary']['total_accounts'])->toBe(3);
    expect($report['summary']['peak_concurrent_accounts'])->toBe(2);
    expect($report['summary']['concurrency_headroom'])->toBe(1); // 3 total - 2 peak = 1 headroom
    expect($report['summary']['total_meetings'])->toBe(3);

    // Verify account level breakdown
    $res1Data = collect($report['accounts'])->firstWhere('id', $this->res1->id);
    expect($res1Data)->not->toBeNull();
    expect($res1Data['meetings_count'])->toBe(2);
    expect($res1Data['total_minutes'])->toBe(150); // 90 min + 60 min

    $res2Data = collect($report['accounts'])->firstWhere('id', $this->res2->id);
    expect($res2Data)->not->toBeNull();
    expect($res2Data['meetings_count'])->toBe(1);
    expect($res2Data['total_minutes'])->toBe(90);

    // Res 3 was unused
    $res3Data = collect($report['accounts'])->firstWhere('id', $this->res3->id);
    expect($res3Data)->not->toBeNull();
    expect($res3Data['meetings_count'])->toBe(0);
    expect($res3Data['is_underutilized'])->toBeTrue();
});

test('it filters usage report by pool_id', function () {
    $now = Carbon::now()->startOfDay()->addHours(10);

    Meeting::create([
        'title' => 'Meeting in Primary Pool',
        'zoom_resource_id' => $this->res1->id,
        'starts_at' => $now->copy(),
        'ends_at' => $now->copy()->addHour(),
        'status' => 'ended',
        'requester_user_id' => $this->admin->id,
        'owner_user_id' => $this->admin->id,
        'meeting_type' => 'scheduled',
    ]);

    Meeting::create([
        'title' => 'Meeting outside Pool',
        'zoom_resource_id' => $this->res3->id,
        'starts_at' => $now->copy(),
        'ends_at' => $now->copy()->addHour(),
        'status' => 'ended',
        'requester_user_id' => $this->admin->id,
        'owner_user_id' => $this->admin->id,
        'meeting_type' => 'scheduled',
    ]);

    $service = app(ZoomAccountUsageReportService::class);
    $report = $service->generateReport([
        'pool_id' => $this->pool->id,
        'start_date' => $now->copy()->subDay()->format('Y-m-d'),
        'end_date' => $now->copy()->addDay()->format('Y-m-d'),
    ]);

    // Primary pool only has res1 and res2
    expect($report['summary']['total_accounts'])->toBe(2);
    expect($report['summary']['total_meetings'])->toBe(1);
});

test('authenticated user can fetch usage report and download CSV via SPA API', function () {
    $response = $this->actingAs($this->admin)
        ->getJson(route('spa.reports.zoom-usage'));

    $response->assertOk()
        ->assertJsonStructure([
            'summary' => [
                'total_accounts',
                'managed_accounts',
                'currently_active_accounts',
                'peak_concurrent_accounts',
                'concurrency_headroom',
                'buffer_percentage',
                'total_meetings',
                'total_hours',
            ],
            'insights' => [
                'headline',
                'recommendation',
                'status_level',
            ],
            'timeline',
            'accounts',
            'available_pools',
            'filters',
        ]);

    $csvResponse = $this->actingAs($this->admin)
        ->get(route('spa.reports.zoom-usage.export'));

    $csvResponse->assertOk();
    expect($csvResponse->headers->get('Content-Type'))->toContain('text/csv');
});
