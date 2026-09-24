<?php

namespace Tests\Feature;

use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\WorkflowRule;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowRulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $admin;

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
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        $this->user = User::create([
            'name' => 'Regular User',
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('faculty');

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $connection = ZoomConnection::create([
            'name' => 'Test Zoom',
            'account_id' => 'acc_test_wf',
            'client_id' => 'cli_test_wf',
            'client_secret' => 'sec_test_wf',
            'enabled' => true,
            'status' => 'active',
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zuid_wf_1',
            'email' => 'licensed1@example.com',
            'first_name' => 'Licensed',
            'last_name' => 'User1',
            'user_type' => 2,
            'status' => 'active',
            'synced_at' => now(),
            'host_key' => '123456',
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'Default Pool',
            'code' => 'DEFAULT_POOL_WF',
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

    public function test_workflow_rule_matching_and_rejection(): void
    {
        // Rule: Proctored exams over 120 minutes are rejected
        WorkflowRule::create([
            'name' => 'Exams Limit Rule',
            'priority' => 10,
            'conditions' => [
                'meeting_type' => 'exam',
                'duration_min' => 121,
            ],
            'actions' => [
                'reject' => 'Exams cannot exceed 2 hours without Dean approval.',
            ],
            'is_enabled' => true,
        ]);

        $meetingService = app(MeetingService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Exams cannot exceed 2 hours without Dean approval.');

        $meetingService->createMeeting($this->user, [
            'title' => 'Long Final Exam',
            'meeting_type' => 'exam',
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(13, 0)->toDateTimeString(), // 180 min
            'participant_count' => 50,
        ]);
    }

    public function test_workflow_rule_auto_approves_when_conditions_met(): void
    {
        // Rule: Short meetings (< 30 min) by faculty are auto-approved
        WorkflowRule::create([
            'name' => 'Quick Faculty Sessions',
            'priority' => 10,
            'conditions' => [
                'duration_max' => 30,
                'role' => 'faculty',
            ],
            'actions' => [
                'auto_approve' => true,
            ],
            'is_enabled' => true,
        ]);

        $meetingService = app(MeetingService::class);

        $meeting = $meetingService->createMeeting($this->user, [
            'title' => 'Quick 15 Min Check-in',
            'meeting_type' => 'meeting',
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(10, 15)->toDateTimeString(),
            'participant_count' => 5,
        ]);

        $this->assertEquals('scheduled', $meeting->status);
        $this->assertEquals($this->resource->id, $meeting->zoom_resource_id);
    }

    public function test_workflow_rule_simulation_endpoint(): void
    {
        WorkflowRule::create([
            'name' => 'Large Gathering Rule',
            'priority' => 5,
            'conditions' => [
                'participant_count_min' => 100,
            ],
            'actions' => [
                'require_approval' => [
                    'approver_type' => 'it_admin',
                    'mode' => 'ANY',
                ],
            ],
            'is_enabled' => true,
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('workflows.simulate'), [
            'starts_at' => Carbon::tomorrow()->setTime(14, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(15, 0)->toDateTimeString(),
            'participant_count' => 150,
            'meeting_type' => 'meeting',
        ]);

        $response->assertOk()
            ->assertJson([
                'matched_count' => 1,
                'requires_approval' => true,
                'is_auto_approved' => false,
            ]);
    }

    public function test_workflow_rule_crud_web_routes(): void
    {
        // Index
        $response = $this->actingAs($this->admin)->get(route('workflows.index'));
        $response->assertOk();

        // Create form
        $response = $this->actingAs($this->admin)->get(route('workflows.create'));
        $response->assertOk();

        // Store
        $response = $this->actingAs($this->admin)->post(route('workflows.store'), [
            'name' => 'Webinar Protocol',
            'priority' => 50,
            'conditions' => [
                'meeting_type' => 'webinar',
            ],
            'actions' => [
                'enable_recording' => 'cloud',
            ],
            'is_enabled' => 1,
        ]);
        $response->assertRedirect(route('workflows.index'));

        $this->assertDatabaseHas('workflow_rules', [
            'name' => 'Webinar Protocol',
            'priority' => 50,
        ]);

        $rule = WorkflowRule::where('name', 'Webinar Protocol')->firstOrFail();

        // Toggle
        $this->actingAs($this->admin)->post(route('workflows.toggle', $rule->public_id));
        $this->assertFalse($rule->fresh()->is_enabled);

        // Delete
        $this->actingAs($this->admin)->delete(route('workflows.destroy', $rule->public_id));
        $this->assertDatabaseMissing('workflow_rules', ['id' => $rule->id]);
    }
}
