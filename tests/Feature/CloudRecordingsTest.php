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
}
