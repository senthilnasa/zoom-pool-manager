<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Recordings\Services\CloudRecordingService;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CloudRecordingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $ownerUser;

    protected User $unrelatedUser;

    protected User $adminUser;

    protected Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleAdmin = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $permAny = Permission::create(['name' => 'recording.view_any', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo($permAny);

        $roleUser = Role::create(['name' => 'Faculty', 'guard_name' => 'web']);
        $permView = Permission::create(['name' => 'recording.view', 'guard_name' => 'web']);
        $roleUser->givePermissionTo($permView);

        $dept = Department::create(['name' => 'Physics', 'code' => 'PHY']);
        $deptMath = Department::create(['name' => 'Mathematics', 'code' => 'MATH']);

        $this->ownerUser = User::create([
            'name' => 'Prof Einstein',
            'email' => 'einstein@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
        $this->ownerUser->assignRole('Faculty');

        $this->unrelatedUser = User::create([
            'name' => 'Prof Euler',
            'email' => 'euler@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $deptMath->id,
            'is_active' => true,
        ]);
        $this->unrelatedUser->assignRole('Faculty');

        $this->adminUser = User::create([
            'name' => 'Admin Curie',
            'email' => 'curie@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');

        $connection = ZoomConnection::create([
            'name' => 'Zoom Core',
            'account_id' => 'acc_phy',
            'client_id' => 'cli_phy',
            'client_secret' => 'sec_phy',
            'status' => 'active',
            'enabled' => true,
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zu_rec_01',
            'email' => 'res_phy@univ.edu',
            'user_type' => 2,
            'synced_at' => now(),
        ]);

        $resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'managed' => true,
            'participant_capacity' => 100,
        ]);

        $this->meeting = Meeting::create([
            'title' => 'Quantum Mechanics Lecture',
            'meeting_type' => 'class',
            'starts_at' => Carbon::now()->subHours(2),
            'ends_at' => Carbon::now()->subHour(),
            'participant_count' => 35,
            'requester_user_id' => $this->ownerUser->id,
            'owner_user_id' => $this->ownerUser->id,
            'department_id' => $dept->id,
            'status' => 'recording_processing',
            'zoom_resource_id' => $resource->id,
            'zoom_meeting_id' => '777888999',
            'zoom_uuid' => 'zm-uuid-qm-01',
        ]);
    }

    public function test_recording_ingestion_maps_to_logical_owner(): void
    {
        $service = app(CloudRecordingService::class);

        $payload = [
            'payload' => [
                'object' => [
                    'id' => '777888999',
                    'uuid' => 'zm-uuid-qm-01',
                    'topic' => 'Quantum Mechanics Lecture',
                    'duration' => 60,
                    'total_size' => 150000000,
                    'share_url' => 'https://zoom.us/rec/share/sample_token',
                    'password' => 'secRec123',
                    'recording_files' => [
                        [
                            'id' => 'file_01',
                            'file_type' => 'MP4',
                            'file_extension' => 'mp4',
                            'file_size' => 140000000,
                            'play_url' => 'https://zoom.us/rec/play/file_01_play',
                            'status' => 'completed',
                        ],
                        [
                            'id' => 'file_02',
                            'file_type' => 'AUDIO',
                            'file_extension' => 'm4a',
                            'file_size' => 10000000,
                            'play_url' => 'https://zoom.us/rec/play/file_02_play',
                            'status' => 'completed',
                        ],
                    ],
                ],
            ],
        ];

        $recording = $service->ingestCompletedRecording($payload);

        $this->assertEquals($this->meeting->id, $recording->meeting_id);
        $this->assertEquals($this->ownerUser->id, $recording->logical_owner_user_id);
        $this->assertEquals(60, $recording->duration_minutes);
        $this->assertEquals(2, $recording->files()->count());

        $this->meeting->refresh();
        $this->assertEquals('completed', $this->meeting->status);
    }

    public function test_logical_owner_can_access_recording_playback(): void
    {
        $recording = CloudRecording::create([
            'meeting_id' => $this->meeting->id,
            'logical_owner_user_id' => $this->ownerUser->id,
            'zoom_meeting_id' => '777888999',
            'topic' => 'Quantum Mechanics',
            'share_url' => 'https://zoom.us/rec/share/sample_play',
            'play_url' => 'https://zoom.us/rec/play/sample_play',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->get(route('recordings.play', $recording->public_id));

        $response->assertRedirect('https://zoom.us/rec/play/sample_play');

        $this->assertDatabaseHas('recording_access_logs', [
            'recording_id' => $recording->id,
            'user_id' => $this->ownerUser->id,
            'action' => 'play_redirect',
        ]);
    }

    public function test_unrelated_user_is_forbidden_from_playback(): void
    {
        $recording = CloudRecording::create([
            'meeting_id' => $this->meeting->id,
            'logical_owner_user_id' => $this->ownerUser->id,
            'zoom_meeting_id' => '777888999',
            'topic' => 'Quantum Mechanics',
            'play_url' => 'https://zoom.us/rec/play/sample_play',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->unrelatedUser)
            ->get(route('recordings.play', $recording->public_id));

        $response->assertStatus(403);
    }

    public function test_super_admin_can_view_and_play_any_recording(): void
    {
        $recording = CloudRecording::create([
            'meeting_id' => $this->meeting->id,
            'logical_owner_user_id' => $this->ownerUser->id,
            'zoom_meeting_id' => '777888999',
            'topic' => 'Quantum Mechanics',
            'play_url' => 'https://zoom.us/rec/play/sample_play',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('recordings.play', $recording->public_id));

        $response->assertRedirect('https://zoom.us/rec/play/sample_play');
    }

    public function test_owner_can_update_recording_passcode(): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok_123']),
            'api.zoom.us/v2/meetings/*/recordings/settings' => Http::response(null, 204),
            'api.zoom.us/v2/meetings/*/recordings' => Http::response([
                'share_url' => 'https://zoom.us/rec/share/sample_play',
                'recording_play_passcode' => 'newPlayToken123',
            ]),
        ]);

        $recording = CloudRecording::create([
            'meeting_id' => $this->meeting->id,
            'logical_owner_user_id' => $this->ownerUser->id,
            'zoom_meeting_id' => '777888999',
            'zoom_recording_id' => '/abc//def==',
            'topic' => 'Quantum Mechanics',
            'play_url' => 'https://zoom.us/rec/play/sample_play',
            'share_url' => 'https://zoom.us/rec/share/sample_play',
            'passcode' => 'OldPass123',
            'play_passcode' => 'oldPlayToken',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->putJson("/spa/recordings/{$recording->id}/passcode", [
                'passcode' => 'NewCustomPass@2026',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('one_click_url', 'https://zoom.us/rec/share/sample_play?pwd=newPlayToken123');

        $recording->refresh();
        $this->assertEquals('NewCustomPass@2026', $recording->passcode);
        $this->assertEquals('newPlayToken123', $recording->play_passcode);

        // Must target this recording instance by double-encoded UUID, not the numeric (latest-instance) ID.
        Http::assertSent(fn (Request $req) => $req->method() === 'PATCH'
            && str_contains($req->url(), '/meetings/'.rawurlencode(rawurlencode('/abc//def==')).'/recordings/settings')
            && $req['password'] === 'NewCustomPass@2026');
    }

    public function test_passcode_rejected_by_zoom_is_not_saved_locally(): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok_123']),
            'api.zoom.us/v2/meetings/*/recordings/settings' => Http::response([
                'code' => 300,
                'message' => 'Passcode does not meet the requirements.',
            ], 400),
        ]);

        $recording = CloudRecording::create([
            'meeting_id' => $this->meeting->id,
            'logical_owner_user_id' => $this->ownerUser->id,
            'zoom_meeting_id' => '777888999',
            'zoom_recording_id' => 'uuidAbc==',
            'topic' => 'Quantum Mechanics',
            'share_url' => 'https://zoom.us/rec/share/sample_play',
            'passcode' => 'OldPass@123',
            'status' => 'completed',
        ]);

        $this->actingAs($this->ownerUser)
            ->putJson("/spa/recordings/{$recording->id}/passcode", ['passcode' => '12342'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'Zoom rejected the new passcode: Passcode does not meet the requirements.']);

        $this->assertEquals('OldPass@123', $recording->fresh()->passcode);
    }

    public function test_list_api_play_token_is_not_stored_as_passcode(): void
    {
        $service = app(CloudRecordingService::class);

        // Legacy row where the encoded play token was saved as the passcode.
        CloudRecording::create([
            'zoom_meeting_id' => '777888999',
            'zoom_recording_id' => 'zm-uuid-qm-01',
            'topic' => 'Quantum Mechanics Lecture',
            'passcode' => 'encPlayToken_XYZ',
            'status' => 'completed',
        ]);

        $recording = $service->ingestCompletedRecording(['payload' => ['object' => [
            'id' => '777888999',
            'uuid' => 'zm-uuid-qm-01',
            'topic' => 'Quantum Mechanics Lecture',
            'share_url' => 'https://zoom.us/rec/share/sample_token',
            'recording_play_passcode' => 'encPlayToken_XYZ',
        ]]]);

        $this->assertNull($recording->passcode);
        $this->assertEquals('encPlayToken_XYZ', $recording->play_passcode);
        $this->assertEquals('https://zoom.us/rec/share/sample_token?pwd=encPlayToken_XYZ', $recording->one_click_url);

        // Webhook payloads carry the real passcode; it wins.
        $recording = $service->ingestCompletedRecording(['payload' => ['object' => [
            'id' => '777888999',
            'uuid' => 'zm-uuid-qm-01',
            'topic' => 'Quantum Mechanics Lecture',
            'share_url' => 'https://zoom.us/rec/share/sample_token',
            'password' => 'Real@Pass1',
            'recording_play_passcode' => 'encPlayToken_XYZ',
        ]]]);
        $this->assertEquals('Real@Pass1', $recording->passcode);

        // A later list-API sync (no `password`) must not wipe the known passcode.
        $recording = $service->ingestCompletedRecording(['payload' => ['object' => [
            'id' => '777888999',
            'uuid' => 'zm-uuid-qm-01',
            'topic' => 'Quantum Mechanics Lecture',
            'recording_play_passcode' => 'encPlayToken_XYZ',
        ]]]);
        $this->assertEquals('Real@Pass1', $recording->passcode);
    }

    public function test_sync_fetches_real_passcode_from_recording_settings(): void
    {
        Http::fake([
            'zoom.us/oauth/token*' => Http::response(['access_token' => 'tok_123']),
            'api.zoom.us/v2/users/*/recordings*' => Http::response(['meetings' => [[
                'id' => 777888999,
                'uuid' => 'zm-uuid-qm-01',
                'topic' => 'Quantum Mechanics Lecture',
                'share_url' => 'https://zoom.us/rec/share/sample_token',
                'recording_play_passcode' => 'encPlayToken_XYZ',
                'recording_files' => [],
            ]]]),
            'api.zoom.us/v2/meetings/*/recordings/settings' => Http::response(['password' => 'Typed@Pass9']),
        ]);

        $this->artisan('zpm:recordings:sync', ['--days' => 1])->assertSuccessful();

        $recording = CloudRecording::where('zoom_recording_id', 'zm-uuid-qm-01')->firstOrFail();
        $this->assertEquals('Typed@Pass9', $recording->passcode);
        $this->assertEquals('encPlayToken_XYZ', $recording->play_passcode);
    }

    public function test_owner_can_send_recording_invitation(): void
    {
        $recording = CloudRecording::create([
            'meeting_id' => $this->meeting->id,
            'logical_owner_user_id' => $this->ownerUser->id,
            'zoom_meeting_id' => '777888999',
            'topic' => 'Quantum Mechanics',
            'play_url' => 'https://zoom.us/rec/play/sample_play',
            'share_url' => 'https://zoom.us/rec/share/sample_play',
            'passcode' => 'Pass@2026',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->ownerUser)
            ->postJson("/spa/recordings/{$recording->id}/send", [
                'recipient_emails' => ['student1@univ.edu', 'student2@univ.edu'],
                'message' => 'Please watch session by Monday.',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
    }
}
