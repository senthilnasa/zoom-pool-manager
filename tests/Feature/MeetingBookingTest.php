<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Scheduling\Models\BookingPolicy;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use App\Http\Middleware\EnsureInstalled;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeetingBookingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ResourcePool $pool;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        file_put_contents(storage_path(EnsureInstalled::LOCK_FILE), json_encode(['installed_at' => now()->toIso8601String()]));

        Setting::set('org.min_buffer_minutes', 10);
        Setting::set('org.default_buffer_minutes', 15);
        Setting::set('org.min_notice_hours', 0);
        Setting::set('org.max_advance_days', 90);
        Setting::set('org.max_duration_minutes', 300);

        $dept = Department::create(['name' => 'Computer Science', 'code' => 'CS']);

        $this->user = User::create([
            'name' => 'Prof. Alan Turing',
            'email' => 'aturing@univ.edu',
            'password' => bcrypt('secret123'),
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $connection = ZoomConnection::create([
            'name' => 'Test University',
            'account_id' => 'zoom-acc-1',
            'client_id' => 'zoom-client-1',
            'client_secret' => 'zoom-secret-1',
            'enabled' => true,
            'status' => 'active',
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm-user-1',
            'email' => 'licensed1@univ.edu',
            'first_name' => 'Licensed',
            'last_name' => 'One',
            'user_type' => 2,
            'status' => 'active',
            'synced_at' => now(),
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'Academic Pool',
            'code' => 'ACADEMIC_POOL',
            'description' => 'General pool for faculty',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'participant_capacity' => 100,
            'managed' => true,
            'status' => 'active',
            'priority' => 10,
        ]);

        DB::table('resource_pool_members')->insert([
            'pool_id' => $this->pool->id,
            'resource_id' => $this->resource->id,
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        BookingPolicy::create([
            'name' => 'CS Department Policy',
            'department_id' => $dept->id,
            'min_notice_hours' => 0,
            'max_advance_days' => 60,
            'min_buffer_minutes' => 10,
            'default_buffer_minutes' => 15,
            'max_duration_minutes' => 240,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $lockFile = storage_path(EnsureInstalled::LOCK_FILE);
        if (file_exists($lockFile)) {
            unlink($lockFile);
        }

        parent::tearDown();
    }

    public function test_preview_conflicts_endpoint(): void
    {
        $startsAt = Carbon::now()->addDay()->setHour(10)->setMinute(0);
        $endsAt = Carbon::now()->addDay()->setHour(11)->setMinute(0);

        $response = $this->actingAs($this->user)
            ->postJson(route('meetings.preview-conflicts'), [
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'participant_count' => 50,
                'preferred_pool_id' => $this->pool->id,
            ]);

        $response->assertOk();
        $response->assertJson([
            'has_conflict' => false,
            'available_resource_count' => 1,
        ]);
    }

    public function test_booking_form_submission_creates_meeting_and_reserves_resource(): void
    {
        $startsAt = Carbon::now()->addDays(2)->setHour(14)->setMinute(0);
        $endsAt = Carbon::now()->addDays(2)->setHour(15)->setMinute(30);

        $response = $this->actingAs($this->user)
            ->post(route('meetings.store'), [
                'title' => 'Algorithms & Data Structures Lecture',
                'description' => 'Weekly review session on graphs.',
                'meeting_type' => 'class',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'participant_count' => 45,
                'preferred_pool_id' => $this->pool->id,
                'invitees' => 'student1@univ.edu, student2@univ.edu',
            ]);

        $meeting = Meeting::where('title', 'Algorithms & Data Structures Lecture')->first();
        $this->assertNotNull($meeting);

        $response->assertRedirect(route('meetings.show', $meeting->public_id));
        $this->assertEquals('allocating', $meeting->status);
        $this->assertEquals($this->resource->id, $meeting->zoom_resource_id);
        $this->assertCount(2, $meeting->invitees);

        // Verify single source of truth in resource_reservations
        $reservation = ResourceReservation::where('meeting_id', $meeting->id)->first();
        $this->assertNotNull($reservation);
        $this->assertEquals($this->resource->id, $reservation->resource_id);
        $this->assertEquals('held', $reservation->status);
        // Occupied from is starts_at, occupied until includes buffer: 15 min
        $this->assertEquals(
            $startsAt->timestamp,
            $reservation->occupied_from->timestamp
        );
        $this->assertEquals(
            $endsAt->copy()->addMinutes(15)->timestamp,
            $reservation->occupied_until->timestamp
        );
    }

    public function test_cancelling_meeting_releases_reservation(): void
    {
        $startsAt = Carbon::now()->addDays(3)->setHour(9)->setMinute(0);
        $endsAt = Carbon::now()->addDays(3)->setHour(10)->setMinute(0);

        $meeting = Meeting::create([
            'title' => 'Lab Discussion',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participant_count' => 20,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'zoom_resource_id' => $this->resource->id,
            'status' => 'scheduled',
        ]);

        $reservation = ResourceReservation::create([
            'resource_id' => $this->resource->id,
            'meeting_id' => $meeting->id,
            'reservation_type' => 'buffer_overlap',
            'occupied_from' => $startsAt,
            'occupied_until' => $endsAt,
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('meetings.cancel', $meeting->public_id), [
                'reason' => 'Instructor unwell, cancelling class.',
            ]);

        $response->assertRedirect(route('meetings.show', $meeting->public_id));
        $this->assertEquals('cancelled', $meeting->fresh()->status);
        $this->assertEquals('released', $reservation->fresh()->status);
    }

    public function test_authenticated_views_render_successfully(): void
    {
        $startsAt = Carbon::now()->addDay()->setHour(11)->setMinute(0);
        $endsAt = Carbon::now()->addDay()->setHour(12)->setMinute(0);

        $meeting = Meeting::create([
            'title' => 'Faculty Meeting',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participant_count' => 15,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'zoom_resource_id' => $this->resource->id,
            'status' => 'scheduled',
        ]);

        // Meetings index
        $this->actingAs($this->user)
            ->get(route('meetings.index'))
            ->assertOk()
            ->assertSee('Faculty Meeting');

        // Meetings create
        $this->actingAs($this->user)
            ->get(route('meetings.create'))
            ->assertOk()
            ->assertSee('Request Pooled Zoom Meeting');

        // Meeting show
        $this->actingAs($this->user)
            ->get(route('meetings.show', $meeting->public_id))
            ->assertOk()
            ->assertSee('Faculty Meeting')
            ->assertSee('Cancel Meeting');

        // Calendar view
        $this->actingAs($this->user)
            ->get(route('calendar'))
            ->assertOk()
            ->assertSee('Resource Timeline Calendar');
    }
}
