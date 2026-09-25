<?php

namespace Tests\Feature;

use App\Domain\Communication\Services\IcsCalendarService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingSeries;
use App\Domain\Settings\Models\Setting;
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

class ExtendedMeetingFlagsAndRecurrenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Department $department;

    protected ResourcePool $pool;

    protected ZoomResource $resource;

    protected ZoomUser $zoomUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TemplatesAndSecurityProfilesSeeder::class);
        Setting::set('org.require_meeting_approval', false);

        $this->department = Department::create([
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        $this->user = User::create([
            'name' => 'Dr. Alan Turing',
            'email' => 'alan.turing@krea.edu.in',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'General Academic Pool',
            'code' => 'GEN_POOL',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
        ]);

        $connection = ZoomConnection::create([
            'name' => 'Main Account',
            'account_id' => 'zoom-acc-test',
            'client_id' => 'zoom-client-test',
            'client_secret' => 'zoom-secret-test',
            'enabled' => true,
            'status' => 'active',
        ]);

        $this->zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm_test_user_1',
            'email' => 'host1@example.edu',
            'first_name' => 'Host',
            'last_name' => 'One',
            'user_type' => 2,
            'status' => 'active',
            'host_key' => '654321',
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $this->zoomUser->id,
            'name' => 'CS Host License 1',
            'participant_capacity' => 100,
            'managed' => true,
            'status' => 'active',
        ]);

        $this->pool->resources()->attach($this->resource->id, ['priority' => 10]);
    }

    public function test_booking_single_meeting_with_extended_flags(): void
    {
        $startsAt = Carbon::tomorrow()->setHour(14)->setMinute(0);
        $endsAt = (clone $startsAt)->addHour();

        $response = $this->actingAs($this->user)
            ->postJson('/meetings', [
                'title' => 'Advanced Algorithms Lecture',
                'agenda' => 'Dynamic programming discussion',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'participant_count' => 45,
                'pool_id' => $this->pool->id,
                'waiting_room' => true,
                'join_before_host' => true,
                'jbh_time' => 10,
                'attendance_tracking' => true,
                'share_host_key' => true,
                'recording_mode' => 'cloud',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $meeting = Meeting::where('title', 'Advanced Algorithms Lecture')->first();
        $this->assertNotNull($meeting);
        $this->assertTrue((bool) $meeting->waiting_room);
        $this->assertTrue((bool) $meeting->join_before_host);
        $this->assertEquals(10, $meeting->jbh_time);
        $this->assertTrue((bool) $meeting->attendance_tracking);
        $this->assertTrue((bool) $meeting->share_host_key);
        $this->assertEquals('cloud', $meeting->recording_mode);
        $this->assertEquals('654321', $meeting->host_key);
        $this->assertEquals('scheduled', $meeting->status);
    }

    public function test_ics_calendar_includes_host_key_when_shared(): void
    {
        $startsAt = Carbon::tomorrow()->setHour(10)->setMinute(0);
        $endsAt = (clone $startsAt)->addHour();

        $meeting = Meeting::create([
            'title' => 'Lab Session',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'zoom_resource_id' => $this->resource->id,
            'host_key' => '987654',
            'share_host_key' => true,
            'status' => 'scheduled',
        ]);

        $icsService = app(IcsCalendarService::class);
        $icsContent = $icsService->generate($meeting);

        $this->assertStringContainsString('Host Key PIN: 987654', $icsContent);
        $this->assertStringContainsString('Claim Host', $icsContent);
    }

    public function test_booking_recurring_series_from_meeting_endpoint(): void
    {
        $startsAt = Carbon::tomorrow()->setHour(9)->setMinute(0);
        $endsAt = (clone $startsAt)->addMinutes(50);

        $response = $this->actingAs($this->user)
            ->postJson('/meetings', [
                'title' => 'Weekly Linear Algebra',
                'agenda' => 'Eigenvalues and Eigenvectors',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'is_recurring' => true,
                'frequency' => 'WEEKLY',
                'interval' => 1,
                'occurrence_count' => 3,
                'pool_id' => $this->pool->id,
                'waiting_room' => true,
                'join_before_host' => false,
                'share_host_key' => true,
                'recording_mode' => 'cloud',
                'attendance_tracking' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $series = MeetingSeries::latest()->first();
        $this->assertNotNull($series);
        $this->assertEquals('cloud', $series->recording_mode);
        $this->assertTrue((bool) $series->waiting_room);
        $this->assertTrue((bool) $series->share_host_key);
        $this->assertTrue((bool) $series->attendance_tracking);

        // Check occurrences
        $this->assertEquals(3, $series->meetings()->count());
        $firstOccurrence = $series->meetings()->first();
        $this->assertEquals('cloud', $firstOccurrence->recording_mode);
        $this->assertTrue((bool) $firstOccurrence->share_host_key);
        $this->assertEquals('654321', $firstOccurrence->host_key);
    }

    public function test_quick_book_with_extended_flags(): void
    {
        $startsAt = Carbon::tomorrow()->setHour(16)->setMinute(0);
        $endsAt = (clone $startsAt)->addHour();

        $response = $this->actingAs($this->user)
            ->postJson('/spa/calendar/quick-book', [
                'title' => 'Quick Office Hours',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'pool_id' => $this->pool->id,
                'waiting_room' => false,
                'join_before_host' => true,
                'jbh_time' => 5,
                'share_host_key' => true,
                'recording_mode' => 'local',
                'attendance_tracking' => true,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $meeting = Meeting::where('title', 'Quick Office Hours')->first();
        $this->assertNotNull($meeting);
        $this->assertFalse((bool) $meeting->waiting_room);
        $this->assertTrue((bool) $meeting->join_before_host);
        $this->assertEquals(5, $meeting->jbh_time);
        $this->assertEquals('local', $meeting->recording_mode);
        $this->assertTrue((bool) $meeting->share_host_key);
    }
}
