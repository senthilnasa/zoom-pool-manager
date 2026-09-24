<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\WaitlistEntry;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    protected User $user1;

    protected User $user2;

    protected Department $department;

    protected ResourcePool $pool;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TemplatesAndSecurityProfilesSeeder::class);

        Setting::set('org.min_buffer_minutes', 10);
        Setting::set('org.default_buffer_minutes', 15);
        Setting::set('org.min_notice_hours', 0);
        Setting::set('org.max_advance_days', 365);
        Setting::set('org.max_duration_minutes', 480);

        $this->department = Department::create([
            'name' => 'Mathematics',
            'code' => 'MATH',
        ]);

        $this->user1 = User::create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->user1->assignRole('faculty');

        $this->user2 = User::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->user2->assignRole('faculty');

        $connection = ZoomConnection::create([
            'name' => 'Test Zoom',
            'account_id' => 'acc_test_wl',
            'client_id' => 'cli_test_wl',
            'client_secret' => 'sec_test_wl',
            'enabled' => true,
            'status' => 'active',
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zuid_wl_1',
            'email' => 'licensed_wl1@example.com',
            'first_name' => 'Licensed',
            'last_name' => 'User1',
            'user_type' => 2,
            'status' => 'active',
            'synced_at' => now(),
            'host_key' => '123456',
        ]);

        // Pool with only 1 resource
        $this->pool = ResourcePool::create([
            'name' => 'Exclusive Pool',
            'code' => 'EXCL_POOL_WL',
            'pool_strategy' => 'least_hours_today',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'participant_capacity' => 300,
            'managed' => true,
            'status' => 'active',
        ]);

        $this->pool->resources()->attach($this->resource->id);
    }

    public function test_booking_conflict_can_be_placed_on_waitlist(): void
    {
        $meetingService = app(MeetingService::class);

        $startsAt = Carbon::now()->addDays(2)->setTime(10, 0);
        $endsAt = Carbon::now()->addDays(2)->setTime(11, 0);

        // User 1 books the only resource
        $meeting1 = $meetingService->createMeeting($this->user1, [
            'title' => 'First Meeting',
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'participant_count' => 10,
        ]);
        $this->assertEquals('scheduled', $meeting1->status);

        // User 2 requests identical time with allow_waitlist = true
        $meeting2 = $meetingService->createMeeting($this->user2, [
            'title' => 'Conflicted Waitlist Meeting',
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'participant_count' => 10,
            'allow_waitlist' => true,
        ]);

        $this->assertEquals('waitlisted', $meeting2->status);
        $this->assertDatabaseHas('waitlist_entries', [
            'meeting_id' => $meeting2->id,
            'status' => 'waiting',
        ]);
    }

    public function test_cancelling_meeting_allocates_next_waitlisted_meeting(): void
    {
        $meetingService = app(MeetingService::class);

        $startsAt = Carbon::now()->addDays(2)->setTime(14, 0);
        $endsAt = Carbon::now()->addDays(2)->setTime(15, 0);

        // 1. User 1 books slot
        $meeting1 = $meetingService->createMeeting($this->user1, [
            'title' => 'Meeting To Be Cancelled',
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'participant_count' => 10,
        ]);

        // 2. User 2 waitlisted for same slot
        $meeting2 = $meetingService->createMeeting($this->user2, [
            'title' => 'Waitlisted Meeting Waiting For Slot',
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt->toDateTimeString(),
            'participant_count' => 10,
            'allow_waitlist' => true,
        ]);
        $this->assertEquals('waitlisted', $meeting2->status);

        // 3. User 1 cancels meeting
        $meetingService->cancelMeeting($meeting1, $this->user1, 'Cannot attend');

        // 4. Waitlist entry for Meeting 2 should now be allocated!
        $this->assertEquals('scheduled', $meeting2->fresh()->status);
        $this->assertEquals($this->resource->id, $meeting2->fresh()->zoom_resource_id);

        $waitlistEntry = WaitlistEntry::where('meeting_id', $meeting2->id)->firstOrFail();
        $this->assertEquals('allocated', $waitlistEntry->status);
        $this->assertNotNull($waitlistEntry->allocated_at);
    }
}
