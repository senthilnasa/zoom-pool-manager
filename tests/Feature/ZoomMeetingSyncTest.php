<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZoomMeetingSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected ZoomUser $zoomUser;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.demo' => false]);

        $department = Department::create(['name' => 'Physics', 'code' => 'PHY']);
        $this->owner = User::create([
            'name' => 'Prof Owner',
            'email' => 'owner@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);

        $connection = ZoomConnection::create([
            'name' => 'Zoom Core',
            'account_id' => 'acc_sync',
            'client_id' => 'cli_sync',
            'client_secret' => 'sec_sync',
            'status' => 'active',
            'enabled' => true,
        ]);

        $this->zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zu_sync_01',
            'email' => 'host_sync@univ.edu',
            'user_type' => 2,
            'status' => 'active',
            'host_key' => '111111',
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $this->zoomUser->id,
            'managed' => true,
            'participant_capacity' => 100,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeMeeting(array $overrides = []): Meeting
    {
        return Meeting::create(array_merge([
            'title' => 'Thermodynamics',
            'meeting_type' => 'class',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
            'participant_count' => 20,
            'requester_user_id' => $this->owner->id,
            'owner_user_id' => $this->owner->id,
            'status' => 'scheduled',
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '81774986295',
            'join_url' => 'https://zoom.us/j/81774986295?pwd=old',
            'passcode' => 'old',
            'host_key' => '111111',
        ], $overrides));
    }

    protected function fakeZoom(): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok'], 200),
            'api.zoom.us/v2/users/zu_sync_01' => Http::response(['id' => 'zu_sync_01', 'host_key' => '654321'], 200),
            'api.zoom.us/v2/meetings/81774986295' => Http::response([
                'id' => 81774986295,
                'uuid' => 'uuid-abc==',
                'join_url' => 'https://zoom.us/j/81774986295?pwd=new',
                'password' => 'new123',
            ], 200),
            'api.zoom.us/v2/meetings/*' => Http::response(['code' => 3001, 'message' => 'Meeting does not exist'], 404),
        ]);
    }

    public function test_it_syncs_host_keys_and_meeting_details_from_zoom(): void
    {
        $this->fakeZoom();
        $meeting = $this->makeMeeting();

        $this->artisan('zpm:zoom:sync-meetings')->assertSuccessful();

        $this->assertSame('654321', $this->zoomUser->fresh()->host_key);

        $meeting->refresh();
        $this->assertSame('654321', $meeting->host_key);
        $this->assertSame('new123', $meeting->passcode);
        $this->assertSame('https://zoom.us/j/81774986295?pwd=new', $meeting->join_url);
        $this->assertSame('uuid-abc==', $meeting->zoom_uuid);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->fakeZoom();
        $meeting = $this->makeMeeting();

        $this->artisan('zpm:zoom:sync-meetings', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame('111111', $this->zoomUser->fresh()->host_key);
        $this->assertSame('old', $meeting->fresh()->passcode);
        $this->assertSame('111111', $meeting->fresh()->host_key);
    }

    public function test_past_and_cancelled_meetings_are_skipped_unless_requested(): void
    {
        $this->fakeZoom();
        $past = $this->makeMeeting([
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHour(),
            'status' => 'completed',
        ]);
        $cancelled = $this->makeMeeting(['status' => 'cancelled']);

        $this->artisan('zpm:zoom:sync-meetings')->assertSuccessful();
        $this->assertSame('old', $past->fresh()->passcode);
        $this->assertSame('old', $cancelled->fresh()->passcode);

        $this->artisan('zpm:zoom:sync-meetings', ['--all' => true])->assertSuccessful();
        $this->assertSame('new123', $past->fresh()->passcode);
        $this->assertSame('old', $cancelled->fresh()->passcode);
    }

    public function test_meetings_missing_in_zoom_are_reported_but_not_changed(): void
    {
        $this->fakeZoom();
        $meeting = $this->makeMeeting(['zoom_meeting_id' => '89999999999']);

        $this->artisan('zpm:zoom:sync-meetings')
            ->expectsOutputToContain('was not found in Zoom')
            ->assertSuccessful();

        $this->assertSame('old', $meeting->fresh()->passcode);
    }

    public function test_it_fails_cleanly_without_zoom_credentials(): void
    {
        ZoomConnection::query()->forceDelete();

        $this->artisan('zpm:zoom:sync-meetings')->assertFailed();
    }
}
