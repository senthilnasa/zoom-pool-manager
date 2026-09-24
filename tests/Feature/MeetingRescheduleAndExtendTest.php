<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Recordings\Models\CloudRecording;
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

class MeetingRescheduleAndExtendTest extends TestCase
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

        $this->department = Department::create([
            'name' => 'Information Technology',
            'code' => 'IT',
        ]);

        $this->user = User::create([
            'name' => 'Prof. Grace Hopper',
            'email' => 'grace.hopper@krea.edu.in',
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('faculty');

        $this->pool = ResourcePool::create([
            'name' => 'Faculty Pool',
            'code' => 'FACULTY_POOL',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
        ]);

        $connection = ZoomConnection::create([
            'name' => 'Main Account',
            'account_id' => 'zoom_acct_test',
            'client_id' => 'zoom_client_test',
            'client_secret' => 'zoom_secret_test',
            'is_active' => true,
        ]);

        $this->zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm_hopper_123',
            'email' => 'licensed_host1@institution.edu',
            'first_name' => 'Licensed',
            'last_name' => 'Host',
            'user_type' => 2,
            'host_key' => '654321',
            'status' => 'active',
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'public_id' => 'zr_test_faculty_1',
            'name' => 'Faculty Host License Alpha',
            'zoom_user_id' => $this->zoomUser->id,
            'participant_capacity' => 300,
            'managed' => true,
            'status' => 'active',
        ]);

        $this->resource->pools()->attach($this->pool->id);
    }

    public function test_can_reschedule_and_update_meeting_details(): void
    {
        $startsAt = Carbon::now()->addDays(2)->setHour(10)->setMinute(0)->setSecond(0);
        $endsAt = (clone $startsAt)->addHour();

        $createRes = $this->actingAs($this->user)->postJson('/meetings', [
            'title' => 'Initial Quantum Computing Lecture',
            'agenda' => 'Initial overview of qubits',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'participant_count' => 25,
            'pool_id' => $this->pool->id,
            'waiting_room' => true,
            'passcode' => '112233',
            'invitees' => 'student1@institution.edu, student2@institution.edu',
        ]);

        $createRes->assertStatus(200);
        /** @var Meeting $meeting */
        $meeting = Meeting::where('title', 'Initial Quantum Computing Lecture')->firstOrFail();

        $this->assertCount(2, $meeting->invitees);
        $this->assertEquals('112233', $meeting->passcode);

        // Now reschedule to 2 hours later with updated title and new invitee
        $newStartsAt = (clone $startsAt)->addHours(2);
        $newEndsAt = (clone $newStartsAt)->addHours(2);

        $updateRes = $this->actingAs($this->user)->putJson("/spa/meetings/{$meeting->public_id}", [
            'title' => 'Rescheduled Quantum Computing Masterclass',
            'description' => 'Updated deeper dive into quantum entanglement',
            'starts_at' => $newStartsAt->toIso8601String(),
            'ends_at' => $newEndsAt->toIso8601String(),
            'waiting_room' => false,
            'join_before_host' => true,
            'jbh_time' => 10,
            'recording_mode' => 'cloud',
            'attendance_tracking' => true,
            'share_host_key' => true,
            'passcode' => '998877',
            'invitees' => 'guest.speaker@mit.edu',
        ]);

        $updateRes->assertStatus(200);
        $updateRes->assertJsonPath('success', true);
        $updateRes->assertJsonPath('meeting.title', 'Rescheduled Quantum Computing Masterclass');

        $meeting->refresh();
        $this->assertEquals('Rescheduled Quantum Computing Masterclass', $meeting->title);
        $this->assertEquals('998877', $meeting->passcode);
        $this->assertFalse($meeting->waiting_room);
        $this->assertTrue($meeting->join_before_host);
        $this->assertEquals('cloud', $meeting->recording_mode);

        $this->assertDatabaseHas('meeting_invitees', [
            'meeting_id' => $meeting->id,
            'email' => 'guest.speaker@mit.edu',
        ]);
    }

    public function test_can_extend_active_meeting_duration(): void
    {
        $startsAt = Carbon::now()->addDays(1)->setHour(14)->setMinute(0)->setSecond(0);
        $endsAt = (clone $startsAt)->addHour();

        $createRes = $this->actingAs($this->user)->postJson('/meetings', [
            'title' => 'Active Department Colloquium',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'participant_count' => 15,
            'pool_id' => $this->pool->id,
        ]);

        $createRes->assertStatus(200);
        /** @var Meeting $meeting */
        $meeting = Meeting::where('title', 'Active Department Colloquium')->firstOrFail();
        $meeting->status = 'started';
        $meeting->save();

        $originalEndsAt = $meeting->ends_at->copy();

        $extendRes = $this->actingAs($this->user)->postJson("/spa/meetings/{$meeting->public_id}/extend", [
            'minutes' => 30,
        ]);

        $extendRes->assertStatus(200);
        $extendRes->assertJsonPath('success', true);

        $meeting->refresh();
        $this->assertEquals($originalEndsAt->addMinutes(30)->toIso8601String(), $meeting->ends_at->toIso8601String());
    }

    public function test_can_invite_attendee_via_api(): void
    {
        $startsAt = Carbon::now()->addDays(1);
        $endsAt = (clone $startsAt)->addHour();

        $meeting = Meeting::create([
            'title' => 'Seminar on Compiler Design',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'owner_user_id' => $this->user->id,
            'requester_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
        ]);

        $inviteRes = $this->actingAs($this->user)->postJson("/spa/meetings/{$meeting->public_id}/invitees", [
            'email' => 'new.student@institution.edu',
            'name' => 'New Student',
        ]);

        $inviteRes->assertStatus(200);
        $inviteRes->assertJsonPath('success', true);
        $inviteRes->assertJsonPath('invitee.email', 'new.student@institution.edu');

        $this->assertDatabaseHas('meeting_invitees', [
            'meeting_id' => $meeting->id,
            'email' => 'new.student@institution.edu',
        ]);
    }

    public function test_can_delete_recording(): void
    {
        $recording = CloudRecording::create([
            'logical_owner_user_id' => $this->user->id,
            'topic' => 'Archive Recording to Delete',
            'zoom_meeting_id' => 'ZM_999888777',
            'storage_provider' => 'zoom',
            'play_url' => 'https://zoom.us/rec/play/xyz',
            'share_url' => 'https://zoom.us/rec/share/xyz',
            'duration_minutes' => 45,
            'file_size_bytes' => 1024000,
            'status' => 'completed',
        ]);

        $deleteRes = $this->actingAs($this->user)->deleteJson("/spa/recordings/{$recording->id}");

        $deleteRes->assertStatus(200);
        $deleteRes->assertJsonPath('success', true);

        $this->assertSoftDeleted('cloud_recordings', [
            'id' => $recording->id,
        ]);
    }
}
