<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Reconciliation\Models\DriftConflict;
use App\Domain\Reconciliation\Services\DriftReconciliationService;
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

class DriftReconciliationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'resource.manage', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $department = Department::create([
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');

        $connection = ZoomConnection::create([
            'name' => 'Main Account',
            'account_id' => 'acc_123',
            'client_id' => 'cli_123',
            'client_secret' => 'sec_123',
            'status' => 'active',
            'enabled' => true,
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zu_02',
            'email' => 'res02@univ.edu',
            'user_type' => 2,
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'managed' => true,
            'participant_capacity' => 100,
        ]);
    }

    public function test_drift_detection_flags_meeting_deleted_on_zoom(): void
    {
        $meeting = Meeting::create([
            'title' => 'Operating Systems',
            'meeting_type' => 'class',
            'starts_at' => Carbon::now()->addDays(2),
            'ends_at' => Carbon::now()->addDays(2)->addHours(2),
            'participant_count' => 50,
            'requester_user_id' => $this->adminUser->id,
            'owner_user_id' => $this->adminUser->id,
            'department_id' => $this->adminUser->department_id,
            'status' => 'scheduled',
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '555666777',
        ]);

        $service = app(DriftReconciliationService::class);

        // Run reconciliation where Zoom account has NO meetings
        $result = $service->reconcile(30, []);

        $this->assertEquals(1, $result['conflicts_created']);
        $this->assertDatabaseHas('drift_conflicts', [
            'meeting_id' => $meeting->id,
            'incident_type' => 'deleted_on_zoom',
            'status' => 'open',
        ]);
    }

    public function test_drift_detection_flags_unmanaged_external_meeting(): void
    {
        $service = app(DriftReconciliationService::class);

        // External meeting created directly on Zoom
        $externalZoomMeetings = [
            [
                'id' => '999888777',
                'topic' => 'Spontaneous Staff Chat',
                'start_time' => Carbon::tomorrow()->setTime(14, 0)->toIso8601String(),
                'duration' => 60,
            ],
        ];

        $result = $service->reconcile(30, $externalZoomMeetings);

        $this->assertEquals(1, $result['conflicts_created']);
        $this->assertDatabaseHas('drift_conflicts', [
            'zoom_meeting_id' => '999888777',
            'incident_type' => 'unmanaged_external_meeting',
            'status' => 'open',
        ]);
    }

    public function test_resolve_conflict_marked_external_blocks_allocator(): void
    {
        $service = app(DriftReconciliationService::class);

        $conflict = DriftConflict::create([
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '999888777',
            'incident_type' => 'unmanaged_external_meeting',
            'details' => [
                'start_time' => Carbon::tomorrow()->setTime(14, 0)->toIso8601String(),
                'duration' => 60,
            ],
            'status' => 'open',
        ]);

        $service->resolveConflict($conflict, 'marked_external', $this->adminUser, 'Marked as external exam');

        $conflict->refresh();
        $this->assertEquals('marked_external', $conflict->status);
        $this->assertEquals($this->adminUser->id, $conflict->resolved_by_user_id);

        // Verify resource reservation was created
        $this->assertDatabaseHas('resource_reservations', [
            'resource_id' => $this->resource->id,
            'meeting_id' => null,
            'status' => 'confirmed',
        ]);
    }

    public function test_resolve_conflict_accepted_zoom_updates_zpm_schedule(): void
    {
        $meeting = Meeting::create([
            'title' => 'Algorithms',
            'meeting_type' => 'class',
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0),
            'participant_count' => 30,
            'requester_user_id' => $this->adminUser->id,
            'owner_user_id' => $this->adminUser->id,
            'department_id' => $this->adminUser->department_id,
            'status' => 'scheduled',
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '333444555',
        ]);

        $newZoomTime = Carbon::tomorrow()->setTime(11, 30)->toIso8601String();

        $conflict = DriftConflict::create([
            'meeting_id' => $meeting->id,
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '333444555',
            'incident_type' => 'time_mismatch',
            'details' => [
                'zpm_start' => $meeting->starts_at->toIso8601String(),
                'zoom_start' => $newZoomTime,
            ],
            'status' => 'open',
        ]);

        $service = app(DriftReconciliationService::class);
        $service->resolveConflict($conflict, 'accepted_zoom', $this->adminUser, 'Accepted Zoom schedule shift');

        $meeting->refresh();
        $this->assertEquals(Carbon::parse($newZoomTime)->timestamp, $meeting->starts_at->timestamp);
    }

    public function test_drift_console_command_runs_successfully(): void
    {
        $this->artisan('zpm:reconcile:drift', ['--days' => 14])
            ->assertSuccessful();
    }
}
