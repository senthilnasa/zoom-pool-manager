<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\WorkflowRule;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use App\Http\Middleware\EnsureInstalled;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserDashboardAndApprovalRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $regularUser;

    protected User $adminUser;

    protected ResourcePool $pool;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        file_put_contents(storage_path(EnsureInstalled::LOCK_FILE), json_encode(['installed_at' => now()->toIso8601String()]));

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TemplatesAndSecurityProfilesSeeder::class);

        Setting::set('org.min_buffer_minutes', 10);
        Setting::set('org.default_buffer_minutes', 15);
        Setting::set('org.min_notice_hours', 0);
        Setting::set('org.max_advance_days', 90);
        Setting::set('org.max_duration_minutes', 300);

        $dept = Department::create(['name' => 'Computer Science', 'code' => 'CS']);

        $this->regularUser = User::create([
            'name' => 'Regular Member',
            'email' => 'regular@university.edu',
            'password' => bcrypt('secret'),
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
        $this->regularUser->assignRole('faculty');

        $this->adminUser = User::create([
            'name' => 'Administrator',
            'email' => 'admin@university.edu',
            'password' => bcrypt('secret'),
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('super_admin');

        $conn = ZoomConnection::create([
            'name' => 'Default Connection',
            'account_id' => 'zoom-acc-1',
            'client_id' => 'zoom-client-1',
            'client_secret' => 'zoom-secret-1',
            'is_active' => true,
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'General Academic Pool',
            'code' => 'GAP',
            'pool_strategy' => 'round_robin',
            'is_active' => true,
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $conn->id,
            'zoom_user_id' => 'zu-test-1',
            'email' => 'host1@university.edu',
            'first_name' => 'Test',
            'last_name' => 'Host 1',
            'type' => 2,
            'status' => 'active',
            'pmi' => '1234567890',
            'host_key' => '123456',
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'name' => 'Host License 1',
            'type' => 'licensed',
            'max_participants' => 300,
            'managed' => true,
            'status' => 'active',
            'priority' => 10,
            'is_dedicated' => false,
        ]);

        DB::table('resource_pool_members')->insert([
            'pool_id' => $this->pool->id,
            'resource_id' => $this->resource->id,
            'priority' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_regular_user_booking_is_a_request_pending_approval(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(10, 0);
        $endsAt = Carbon::tomorrow()->setTime(11, 0);

        $response = $this->actingAs($this->regularUser)
            ->postJson('/meetings', [
                'title' => 'Biology Seminar',
                'meeting_type' => 'class',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'participant_count' => 25,
                'preferred_pool_id' => $this->pool->id,
            ]);

        $response->assertSuccessful();
        $meeting = Meeting::latest('id')->first();
        $this->assertNotNull($meeting);
        $this->assertEquals('pending_approval', $meeting->status);
        $this->assertStringContainsString('pending', $response->json('message'));
    }

    public function test_admin_user_booking_is_immediately_scheduled(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(14, 0);
        $endsAt = Carbon::tomorrow()->setTime(15, 0);

        $response = $this->actingAs($this->adminUser)
            ->postJson('/meetings', [
                'title' => 'Executive Meeting',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'participant_count' => 15,
            ]);

        $response->assertSuccessful();
        $meeting = Meeting::latest('id')->first();
        $this->assertNotNull($meeting);
        $this->assertEquals('scheduled', $meeting->status);
        $this->assertStringContainsString('booked', $response->json('message'));
    }

    public function test_workflow_rule_auto_approves_when_rule_matches(): void
    {
        WorkflowRule::create([
            'name' => 'Auto Approve Short Biology Lectures',
            'priority' => 1,
            'is_enabled' => true,
            'conditions' => [
                'operator' => 'AND',
                'rules' => [
                    ['field' => 'duration', 'operator' => '<=', 'value' => 60],
                ],
            ],
            'actions' => [
                'auto_approve' => true,
            ],
        ]);

        $startsAt = Carbon::tomorrow()->setTime(16, 0);
        $endsAt = Carbon::tomorrow()->setTime(16, 45);

        $response = $this->actingAs($this->regularUser)
            ->postJson('/meetings', [
                'title' => 'Short 45 Min Session',
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'participant_count' => 10,
            ]);

        $response->assertSuccessful();
        $meeting = Meeting::latest('id')->first();
        $this->assertNotNull($meeting);
        $this->assertEquals('scheduled', $meeting->status);
    }

    public function test_dashboard_hides_pool_resources_and_shows_requests_for_regular_user(): void
    {
        // Create 1 pending request for user
        $meeting = Meeting::create([
            'title' => 'Student Study Group',
            'starts_at' => Carbon::today()->setTime(13, 0),
            'ends_at' => Carbon::today()->setTime(14, 0),
            'duration_minutes' => 60,
            'participant_count' => 8,
            'requester_user_id' => $this->regularUser->id,
            'owner_user_id' => $this->regularUser->id,
            'department_id' => $this->regularUser->department_id,
            'status' => 'pending_approval',
            'meeting_type' => 'meeting',
            'recording_mode' => 'none',
        ]);

        $response = $this->actingAs($this->regularUser)
            ->getJson('/spa/dashboard/stats');

        $response->assertSuccessful();
        $data = $response->json();

        // Must not be admin
        $this->assertFalse($data['stats']['is_admin']);

        // Pool resources must be hidden / zeroed for standard user
        $this->assertEquals(0, $data['stats']['total_pools']);
        $this->assertEquals(0, $data['stats']['total_licenses']);
        $this->assertEquals(0, $data['stats']['active_licenses']);
        $this->assertEmpty($data['pools']);

        // User requests stats must be populated
        $this->assertEquals(1, $data['stats']['my_pending_requests']);
        $this->assertEquals(1, $data['stats']['my_total_requests']);
    }

    public function test_dashboard_shows_pool_resources_for_admin(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/spa/dashboard/stats');

        $response->assertSuccessful();
        $data = $response->json();

        $this->assertTrue($data['stats']['is_admin']);
        $this->assertEquals(1, $data['stats']['total_pools']);
        $this->assertEquals(1, $data['stats']['total_licenses']);
        $this->assertNotEmpty($data['pools']);
    }
}
