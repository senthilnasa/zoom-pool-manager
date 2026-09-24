<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpaCalendarAndQuickBookTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $facultyUser;

    protected Department $department;

    protected ResourcePool $pool;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TemplatesAndSecurityProfilesSeeder::class);

        $this->department = Department::create([
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@krea.edu.in',
            'password' => bcrypt('secret123'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('super_admin');

        $this->facultyUser = User::create([
            'name' => 'Prof Alan Turing',
            'email' => 'turing@krea.edu.in',
            'password' => bcrypt('secret123'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->facultyUser->assignRole('faculty');

        $connection = ZoomConnection::create([
            'name' => 'Main Account',
            'account_id' => 'zoom-acc-test',
            'client_id' => 'zoom-client-test',
            'client_secret' => 'zoom-secret-test',
            'enabled' => true,
            'status' => 'active',
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'email' => 'host1@krea.edu.in',
            'zoom_user_id' => 'zoom_usr_123',
            'user_type' => 2,
            'status' => 'active',
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'managed' => true,
            'status' => 'active',
            'participant_capacity' => 300,
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'Academic Pool',
            'code' => 'academic-pool',
            'pool_strategy' => 'least_hours_today',
            'status' => 'active',
        ]);

        $this->resource->pools()->attach($this->pool->id);
    }

    public function test_calendar_endpoint_returns_resources_pools_meetings_and_buffers(): void
    {
        $meeting = Meeting::create([
            'title' => 'Algorithms Lecture',
            'starts_at' => Carbon::now()->addHour(),
            'ends_at' => Carbon::now()->addHours(2),
            'participant_count' => 50,
            'requester_user_id' => $this->facultyUser->id,
            'owner_user_id' => $this->facultyUser->id,
            'department_id' => $this->department->id,
            'zoom_resource_id' => $this->resource->id,
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->facultyUser)
            ->getJson('/spa/calendar');

        $response->assertOk();
        $response->assertJsonStructure([
            'resources',
            'meetings',
            'pools',
            'departments',
            'templates',
            'buffer_minutes',
        ]);

        $data = $response->json();
        $this->assertNotEmpty($data['resources']);
        $this->assertNotEmpty($data['pools']);
        $this->assertGreaterThanOrEqual(1, count($data['meetings']));
        $this->assertEquals(15, $data['buffer_minutes']);
    }

    public function test_quick_book_creates_meeting_and_allocates_resource(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(10, 0);
        $endsAt = Carbon::tomorrow()->setTime(11, 0);

        $payload = [
            'title' => 'Rapid AI Symposium',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'pool_id' => $this->pool->id,
            'department_id' => $this->department->id,
            'participant_count' => 25,
            'meeting_type' => 'class',
            'description' => 'Fast reservation from interactive calendar timeline',
        ];

        $response = $this->actingAs($this->facultyUser)
            ->postJson('/spa/calendar/quick-book', $payload);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('meetings', [
            'title' => 'Rapid AI Symposium',
            'requester_user_id' => $this->facultyUser->id,
            'participant_count' => 25,
        ]);
    }

    public function test_ics_download_streams_valid_rfc5545_calendar(): void
    {
        $meeting = Meeting::create([
            'title' => 'Quantum Computing Workshop',
            'starts_at' => Carbon::tomorrow()->setTime(14, 0),
            'ends_at' => Carbon::tomorrow()->setTime(15, 30),
            'participant_count' => 40,
            'requester_user_id' => $this->facultyUser->id,
            'owner_user_id' => $this->facultyUser->id,
            'department_id' => $this->department->id,
            'zoom_resource_id' => $this->resource->id,
            'status' => 'scheduled',
            'zoom_meeting_id' => '1122334455',
            'passcode' => '998877',
        ]);

        $response = $this->actingAs($this->facultyUser)
            ->get("/spa/meetings/{$meeting->public_id}/ics");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
        $this->assertStringContainsString('SUMMARY:Quantum Computing Workshop', $response->getContent());
        $this->assertStringContainsString("UID:zpm-{$meeting->public_id}@", $response->getContent());
    }

    public function test_end_meeting_early_releases_license_and_triggers_waitlist(): void
    {
        $meeting = Meeting::create([
            'title' => 'Live Demonstration Session',
            'starts_at' => Carbon::now()->subMinutes(30),
            'ends_at' => Carbon::now()->addMinutes(30),
            'participant_count' => 20,
            'requester_user_id' => $this->facultyUser->id,
            'owner_user_id' => $this->facultyUser->id,
            'department_id' => $this->department->id,
            'zoom_resource_id' => $this->resource->id,
            'status' => 'started',
        ]);

        ResourceReservation::create([
            'meeting_id' => $meeting->id,
            'resource_id' => $this->resource->id,
            'occupied_from' => Carbon::now()->subMinutes(30),
            'occupied_until' => Carbon::now()->addMinutes(45),
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->facultyUser)
            ->postJson("/spa/meetings/{$meeting->public_id}/end-early");

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $meeting->refresh();
        $this->assertEquals('completed', $meeting->status);
        $this->assertNotNull($meeting->ends_at);

        $this->assertDatabaseHas('resource_reservations', [
            'meeting_id' => $meeting->id,
            'status' => 'released',
        ]);
    }

    public function test_dashboard_stats_includes_active_meetings_list(): void
    {
        Meeting::create([
            'title' => 'Broadcast Lecture Now Live',
            'starts_at' => Carbon::now()->subMinutes(15),
            'ends_at' => Carbon::now()->addMinutes(45),
            'participant_count' => 85,
            'requester_user_id' => $this->facultyUser->id,
            'owner_user_id' => $this->facultyUser->id,
            'department_id' => $this->department->id,
            'zoom_resource_id' => $this->resource->id,
            'status' => 'started',
            'zoom_meeting_id' => '5566778899',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/spa/dashboard/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'stats',
            'recent_meetings',
            'active_meetings_list',
            'pools',
        ]);

        $activeList = $response->json('active_meetings_list');
        $this->assertNotEmpty($activeList);
        $this->assertEquals('Broadcast Lecture Now Live', $activeList[0]['title']);
        $this->assertGreaterThanOrEqual(14, $activeList[0]['elapsed_minutes']);
    }
}
