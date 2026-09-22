<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Operations\Services\EmergencyOverrideService;
use App\Domain\Scheduling\Models\ResourceReservation;
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

class EmergencyOverrideTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $facultyUser;

    protected ZoomResource $resourceA;

    protected ZoomResource $resourceB;

    protected Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $roleAdmin = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $permEmerg = Permission::create(['name' => 'emergency.use', 'guard_name' => 'web']);
        $roleAdmin->givePermissionTo($permEmerg);

        Role::create(['name' => 'Faculty', 'guard_name' => 'web']);

        $department = Department::create(['name' => 'Chemistry', 'code' => 'CHEM']);

        $this->adminUser = User::create([
            'name' => 'IT Admin',
            'email' => 'itadmin@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');

        $this->facultyUser = User::create([
            'name' => 'Prof Mendeleev',
            'email' => 'mendeleev@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->facultyUser->assignRole('Faculty');

        $connection = ZoomConnection::create([
            'name' => 'Main Account',
            'account_id' => 'acc_chem',
            'client_id' => 'cli_chem',
            'client_secret' => 'sec_chem',
            'status' => 'active',
            'enabled' => true,
        ]);

        $zoomUserA = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zu_res_a',
            'email' => 'hostA@univ.edu',
            'user_type' => 2,
            'synced_at' => now(),
        ]);

        $zoomUserB = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zu_res_b',
            'email' => 'hostB@univ.edu',
            'user_type' => 2,
            'synced_at' => now(),
        ]);

        $this->resourceA = ZoomResource::create([
            'zoom_user_id' => $zoomUserA->id,
            'managed' => true,
            'participant_capacity' => 100,
        ]);

        $this->resourceB = ZoomResource::create([
            'zoom_user_id' => $zoomUserB->id,
            'managed' => true,
            'participant_capacity' => 300,
        ]);

        $this->meeting = Meeting::create([
            'title' => 'Organic Chemistry Lab',
            'meeting_type' => 'class',
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(12, 0),
            'participant_count' => 80,
            'requester_user_id' => $this->facultyUser->id,
            'owner_user_id' => $this->facultyUser->id,
            'department_id' => $department->id,
            'status' => 'scheduled',
            'zoom_resource_id' => $this->resourceA->id,
            'zoom_meeting_id' => '888999000',
        ]);

        ResourceReservation::create([
            'resource_id' => $this->resourceA->id,
            'meeting_id' => $this->meeting->id,
            'occupied_from' => $this->meeting->starts_at,
            'occupied_until' => (clone $this->meeting->ends_at)->addMinutes(10),
            'status' => 'confirmed',
        ]);
    }

    public function test_emergency_reallocate_moves_resource_writes_override_and_notifies(): void
    {
        $service = app(EmergencyOverrideService::class);
        $reason = 'Host A hardware degraded; emergency migration to large capacity Host B.';

        $service->emergencyReallocate($this->meeting, $this->resourceB, $this->adminUser, $reason);

        $this->meeting->refresh();
        $this->assertEquals($this->resourceB->id, $this->meeting->zoom_resource_id);

        // Verify override record created
        $this->assertDatabaseHas('overrides', [
            'target_type' => 'meeting',
            'target_id' => $this->meeting->id,
            'field' => 'emergency_reallocation',
            'old_value' => (string) $this->resourceA->id,
            'new_value' => (string) $this->resourceB->id,
            'reason' => $reason,
        ]);

        // Verify owner notification
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->facultyUser->id,
            'type' => 'security',
        ]);
    }

    public function test_emergency_cancel_cancels_meeting_and_releases_reservation(): void
    {
        $service = app(EmergencyOverrideService::class);
        $reason = 'Campus emergency power outage.';

        $service->emergencyCancel($this->meeting, $this->adminUser, $reason);

        $this->meeting->refresh();
        $this->assertEquals('cancelled', $this->meeting->status);
        $this->assertStringContainsString('Campus emergency power outage', (string) $this->meeting->cancelled_reason);

        // Reservation released
        $this->assertEquals(0, ResourceReservation::where('meeting_id', $this->meeting->id)->count());

        // Override logged
        $this->assertDatabaseHas('overrides', [
            'target_type' => 'meeting',
            'target_id' => $this->meeting->id,
            'field' => 'emergency_cancel',
            'reason' => $reason,
        ]);
    }

    public function test_emergency_override_rejects_empty_reason(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $service = app(EmergencyOverrideService::class);
        $service->emergencyReallocate($this->meeting, $this->resourceB, $this->adminUser, '   ');
    }

    public function test_unauthorized_user_is_forbidden_from_emergency_panel(): void
    {
        $response = $this->actingAs($this->facultyUser)
            ->get(route('admin.emergency'));

        $response->assertStatus(403);
    }

    public function test_emergency_override_web_route(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.emergency.override'), [
                'meeting_public_id' => $this->meeting->public_id,
                'action' => 'reallocate',
                'resource_id' => $this->resourceB->id,
                'reason' => 'Emergency load redistribution across pooled resources.',
            ]);

        $response->assertRedirect();
        $this->meeting->refresh();
        $this->assertEquals($this->resourceB->id, $this->meeting->zoom_resource_id);
    }
}
