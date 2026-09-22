<?php

namespace Tests\Feature;

use App\Domain\HostControl\Models\Override;
use App\Domain\HostControl\Services\HostControlService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use App\Http\Middleware\EnsureInstalled;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HostControlTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherUser;

    protected User $superAdmin;

    protected ZoomResource $resource;

    protected Meeting $meeting;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        file_put_contents(storage_path(EnsureInstalled::LOCK_FILE), json_encode(['installed_at' => now()->toIso8601String()]));

        $this->owner = User::create([
            'name' => 'Prof. Owner',
            'email' => 'owner@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->otherUser = User::create([
            'name' => 'Student Other',
            'email' => 'other@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->superAdmin = User::create([
            'name' => 'IT Admin',
            'email' => 'itadmin@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $this->superAdmin->assignRole('super_admin');

        $connection = ZoomConnection::create([
            'name' => 'Main Zoom',
            'account_id' => 'zoom-acc-1',
            'client_id' => 'zoom-client-1',
            'client_secret' => 'zoom-secret-1',
            'status' => 'active',
            'enabled' => true,
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm_user_pool1',
            'email' => 'zoom01@univ.edu',
            'host_key' => '123456',
            'synced_at' => now(),
            'status' => 'active',
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'participant_capacity' => 100,
            'managed' => true,
            'status' => 'active',
            'priority' => 10,
        ]);

        $this->meeting = Meeting::create([
            'title' => 'Biology 101 Lecture',
            'starts_at' => Carbon::now()->addMinutes(10),
            'ends_at' => Carbon::now()->addMinutes(70),
            'participant_count' => 30,
            'requester_user_id' => $this->owner->id,
            'owner_user_id' => $this->owner->id,
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '9876543210',
            'status' => 'scheduled',
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

    public function test_owner_can_start_meeting_during_15_minute_lead_window(): void
    {
        $response = $this->actingAs($this->owner)
            ->get(route('meetings.start', $this->meeting->public_id));

        $response->assertStatus(302);
        $this->assertStringContainsString('https://zoom.us/s/9876543210', (string) $response->headers->get('Location'));

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'meeting.host_started',
            'auditable_id' => $this->meeting->id,
            'actor_user_id' => $this->owner->id,
        ]);
    }

    public function test_owner_cannot_start_meeting_too_early_before_window(): void
    {
        $this->meeting->update([
            'starts_at' => Carbon::now()->addHours(2),
            'ends_at' => Carbon::now()->addHours(3),
        ]);

        $response = $this->actingAs($this->owner)
            ->get(route('meetings.start', $this->meeting->public_id));

        $response->assertRedirect(route('meetings.show', $this->meeting->public_id));
        $response->assertSessionHasErrors('host_control');
    }

    public function test_unauthorized_user_cannot_access_host_start(): void
    {
        $response = $this->actingAs($this->otherUser)
            ->get(route('meetings.start', $this->meeting->public_id));

        $response->assertRedirect(route('meetings.show', $this->meeting->public_id));
        $response->assertSessionHasErrors('host_control');
    }

    public function test_it_admin_can_emergency_start_with_mandatory_reason(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('meetings.start', [
                'publicId' => $this->meeting->public_id,
                'emergency_reason' => 'Instructor reported hardware crash; IT technician hosting class.',
            ]));

        $response->assertStatus(302);

        // Verify override record was created
        $this->assertDatabaseHas('overrides', [
            'actor_user_id' => $this->superAdmin->id,
            'target_type' => 'meeting',
            'target_id' => $this->meeting->id,
            'field' => 'emergency_host_start',
            'reason' => 'Instructor reported hardware crash; IT technician hosting class.',
        ]);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'meeting.it_emergency_start',
            'auditable_id' => $this->meeting->id,
            'actor_user_id' => $this->superAdmin->id,
        ]);
    }

    public function test_it_admin_cannot_emergency_start_without_reason(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->get(route('meetings.start', $this->meeting->public_id));

        $response->assertRedirect(route('meetings.show', $this->meeting->public_id));
        $response->assertSessionHasErrors('host_control');
    }

    public function test_owner_can_reveal_host_key_during_window(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson(route('meetings.host-key', $this->meeting->public_id));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'host_key' => '123456',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'host_key.revealed',
            'auditable_id' => $this->meeting->id,
            'actor_user_id' => $this->owner->id,
        ]);
    }

    public function test_it_admin_can_reveal_host_key_with_mandatory_reason(): void
    {
        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('meetings.host-key', $this->meeting->public_id), [
                'emergency_reason' => 'Emergency exam supervision claim',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'host_key' => '123456',
        ]);

        $this->assertDatabaseHas('overrides', [
            'actor_user_id' => $this->superAdmin->id,
            'field' => 'host_key_reveal',
            'reason' => 'Emergency exam supervision claim',
        ]);
    }

    public function test_host_key_rotation_updates_zoom_user_and_audits(): void
    {
        $service = app(HostControlService::class);

        $newKey = $service->rotateHostKey($this->resource, $this->superAdmin, 'test_rotation');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $newKey);
        $this->assertEquals($newKey, $this->resource->fresh()->zoomUser->host_key);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'host_key.rotated',
            'auditable_id' => $this->resource->id,
            'actor_user_id' => $this->superAdmin->id,
        ]);
    }
}
