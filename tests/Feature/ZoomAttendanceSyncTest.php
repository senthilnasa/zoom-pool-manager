<?php

namespace Tests\Feature;

use App\Domain\Attendance\Models\MeetingAttendance;
use App\Domain\Attendance\Services\ZoomAttendanceSyncService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZoomAttendanceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Department $department;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        // The service simulates attendance in the testing environment; exercise the real Zoom path.
        $this->app['env'] = 'local';
        config(['app.demo' => false]);

        $this->department = Department::create(['name' => 'Physics', 'code' => 'PHY']);

        $this->owner = User::create([
            'name' => 'Prof Owner',
            'email' => 'owner@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);

        $connection = ZoomConnection::create([
            'name' => 'Zoom Core',
            'account_id' => 'acc_att',
            'client_id' => 'cli_att',
            'client_secret' => 'sec_att',
            'status' => 'active',
            'enabled' => true,
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zu_att_01',
            'email' => 'host_att@univ.edu',
            'user_type' => 2,
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
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
            'starts_at' => Carbon::parse('2026-11-20 10:00:00'),
            'ends_at' => Carbon::parse('2026-11-20 11:00:00'),
            'participant_count' => 20,
            'requester_user_id' => $this->owner->id,
            'owner_user_id' => $this->owner->id,
            'department_id' => $this->department->id,
            'status' => 'ended',
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '81774986295',
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function participantsPayload(): array
    {
        return [
            'participants' => [
                [
                    'id' => 'p1',
                    'name' => 'Alice Student',
                    'user_email' => 'alice@univ.edu',
                    'join_time' => '2026-11-20T10:00:00Z',
                    'leave_time' => '2026-11-20T11:00:00Z',
                    'duration' => 3600,
                ],
            ],
            'next_page_token' => '',
        ];
    }

    public function test_report_uses_stored_session_uuid(): void
    {
        $meeting = $this->makeMeeting(['zoom_uuid' => 'sessUuid123==']);

        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok']),
            'api.zoom.us/v2/report/meetings/*' => Http::response($this->participantsPayload()),
        ]);

        $result = app(ZoomAttendanceSyncService::class)->syncAttendanceForMeeting($meeting);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['attendees_count']);
        Http::assertSent(fn (Request $req) => str_contains($req->url(), '/report/meetings/'.rawurlencode('sessUuid123==').'/participants'));
        Http::assertNotSent(fn (Request $req) => str_contains($req->url(), '/report/meetings/81774986295/'));
    }

    public function test_meeting_not_found_falls_back_to_matching_past_instance(): void
    {
        $meeting = $this->makeMeeting();
        $instanceUuid = '/abc//def==';
        $encodedUuid = rawurlencode(rawurlencode($instanceUuid));

        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok']),
            'api.zoom.us/v2/report/meetings/81774986295/*' => Http::response([
                'code' => 3001,
                'message' => 'Meeting does not exist: 81774986295.',
            ], 404),
            'api.zoom.us/v2/past_meetings/81774986295/instances*' => Http::response([
                'meetings' => [
                    ['uuid' => 'otherDayUuid==', 'start_time' => '2026-11-13T10:02:00Z'],
                    ['uuid' => $instanceUuid, 'start_time' => '2026-11-20T10:03:00Z'],
                ],
            ]),
            "api.zoom.us/v2/report/meetings/{$encodedUuid}/*" => Http::response($this->participantsPayload()),
        ]);

        $result = app(ZoomAttendanceSyncService::class)->syncAttendanceForMeeting($meeting);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertEquals(1, MeetingAttendance::where('meeting_id', $meeting->id)->count());
        $this->assertEquals($instanceUuid, $meeting->fresh()->zoom_uuid);
    }

    public function test_never_started_meeting_reports_a_clear_message(): void
    {
        $meeting = $this->makeMeeting();

        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok']),
            'api.zoom.us/v2/report/meetings/*' => Http::response([
                'code' => 3001,
                'message' => 'Meeting does not exist: 81774986295.',
            ], 404),
            'api.zoom.us/v2/past_meetings/*' => Http::response(['meetings' => []]),
        ]);

        $result = app(ZoomAttendanceSyncService::class)->syncAttendanceForMeeting($meeting);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('never started in Zoom', $result['message']);
    }

    public function test_report_pages_are_all_imported(): void
    {
        $meeting = $this->makeMeeting(['zoom_uuid' => 'pagedUuid==']);

        $page1 = $this->participantsPayload();
        $page1['next_page_token'] = 'page2';
        $page2 = [
            'participants' => [[
                'id' => 'p2',
                'name' => 'Bob Student',
                'join_time' => '2026-11-20T10:05:00Z',
                'leave_time' => '2026-11-20T10:35:00Z',
                'duration' => 1800,
            ]],
            'next_page_token' => '',
        ];

        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok']),
            'api.zoom.us/v2/report/meetings/*' => Http::sequence()->push($page1)->push($page2),
        ]);

        $result = app(ZoomAttendanceSyncService::class)->syncAttendanceForMeeting($meeting);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['attendees_count']);
    }

    public function test_bulk_sync_skips_meetings_that_never_started(): void
    {
        $this->travelTo(Carbon::parse('2026-11-21 09:00:00'));

        $this->makeMeeting(['zoom_meeting_id' => '11111111111', 'zoom_uuid' => 'ranUuid==']);
        $this->makeMeeting(['zoom_meeting_id' => '22222222222', 'status' => 'scheduled']);

        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok']),
            'api.zoom.us/v2/report/meetings/*' => Http::response($this->participantsPayload()),
        ]);

        $result = app(ZoomAttendanceSyncService::class)->syncRecentAttendance();

        $this->assertEquals(1, $result['synced_meetings']);
        Http::assertNotSent(fn (Request $req) => str_contains($req->url(), '22222222222'));
    }
}
