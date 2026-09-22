<?php

namespace Tests\Feature;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\MeetingApproval;
use App\Domain\Workflow\Models\WorkflowRule;
use App\Domain\Workflow\Services\ApprovalWorkflowService;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalChainsTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;

    protected User $deptAdmin;

    protected User $itAdmin;

    protected User $delegateUser;

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
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        $this->requester = User::create([
            'name' => 'Alice Requester',
            'email' => 'alice@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->requester->assignRole('faculty');

        $this->deptAdmin = User::create([
            'name' => 'Bob DeptAdmin',
            'email' => 'bob.admin@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->deptAdmin->assignRole('dept_admin');

        $this->itAdmin = User::create([
            'name' => 'Charlie ITAdmin',
            'email' => 'charlie.it@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->itAdmin->assignRole('it_admin');

        $this->delegateUser = User::create([
            'name' => 'Dana Delegate',
            'email' => 'dana@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->delegateUser->assignRole('faculty');

        $connection = ZoomConnection::create([
            'name' => 'Test Zoom',
            'account_id' => 'acc_test_ac',
            'client_id' => 'cli_test_ac',
            'client_secret' => 'sec_test_ac',
            'enabled' => true,
            'status' => 'active',
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zuid_ac_1',
            'email' => 'licensed_ac1@example.com',
            'first_name' => 'Licensed',
            'last_name' => 'User1',
            'user_type' => 2,
            'status' => 'active',
            'synced_at' => now(),
            'host_key' => '123456',
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'Main Pool',
            'code' => 'MAIN_POOL_AC',
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

    public function test_meeting_requiring_approval_creates_pending_approval_chain(): void
    {
        // Rule: Meetings with > 20 participants require approval
        WorkflowRule::create([
            'name' => 'Over 20 Attendees Approval',
            'priority' => 10,
            'conditions' => [
                'participant_count_min' => 20,
            ],
            'actions' => [
                'require_approval' => [
                    'approver_type' => 'dept_admin',
                    'mode' => 'ANY',
                ],
            ],
            'is_enabled' => true,
        ]);

        $meetingService = app(MeetingService::class);

        $meeting = $meetingService->createMeeting($this->requester, [
            'title' => 'Big Seminar',
            'meeting_type' => 'meeting',
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0)->toDateTimeString(),
            'participant_count' => 30,
        ]);

        $this->assertEquals('pending_approval', $meeting->status);
        $this->assertNull($meeting->zoom_resource_id);

        $this->assertDatabaseHas('meeting_approvals', [
            'meeting_id' => $meeting->id,
            'approver_user_id' => $this->deptAdmin->id,
            'decision' => 'pending',
            'step' => 1,
        ]);
    }

    public function test_anti_self_approval_rule_strictly_blocks_requester(): void
    {
        // Even if the requester has dept_admin role, they cannot approve their own meeting
        $this->requester->assignRole('dept_admin');

        WorkflowRule::create([
            'name' => 'Mandatory Approval',
            'priority' => 10,
            'conditions' => [],
            'actions' => [
                'require_approval' => [
                    'approver_type' => 'dept_admin',
                    'mode' => 'ANY',
                ],
            ],
            'is_enabled' => true,
        ]);

        $meetingService = app(MeetingService::class);
        $meeting = $meetingService->createMeeting($this->requester, [
            'title' => 'Department Seminar',
            'starts_at' => Carbon::tomorrow()->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);

        $approval = MeetingApproval::where('meeting_id', $meeting->id)->firstOrFail();

        $workflowService = app(ApprovalWorkflowService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Anti-Self-Approval violation');

        $workflowService->decide($approval, $this->requester, 'approved');
    }

    public function test_approving_meeting_allocates_resource_and_schedules(): void
    {
        WorkflowRule::create([
            'name' => 'Require Dept Approval',
            'priority' => 10,
            'conditions' => [],
            'actions' => [
                'require_approval' => [
                    'approver_type' => 'dept_admin',
                    'mode' => 'ANY',
                ],
            ],
            'is_enabled' => true,
        ]);

        $meetingService = app(MeetingService::class);
        $meeting = $meetingService->createMeeting($this->requester, [
            'title' => 'Lab Meeting',
            'starts_at' => Carbon::tomorrow()->setTime(14, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(15, 0)->toDateTimeString(),
            'participant_count' => 15,
        ]);

        $approval = MeetingApproval::where('meeting_id', $meeting->id)->firstOrFail();

        $workflowService = app(ApprovalWorkflowService::class);
        $workflowService->decide($approval, $this->deptAdmin, 'approved', 'Looks good to go.');

        $this->assertEquals('approved', $approval->fresh()->decision);
        $this->assertEquals('scheduled', $meeting->fresh()->status);
        $this->assertEquals($this->resource->id, $meeting->fresh()->zoom_resource_id);
    }

    public function test_rejecting_meeting_sets_status_to_rejected_and_cancels(): void
    {
        WorkflowRule::create([
            'name' => 'Approval Gate',
            'priority' => 10,
            'conditions' => [],
            'actions' => [
                'require_approval' => [
                    'approver_type' => 'dept_admin',
                    'mode' => 'ANY',
                ],
            ],
            'is_enabled' => true,
        ]);

        $meetingService = app(MeetingService::class);
        $meeting = $meetingService->createMeeting($this->requester, [
            'title' => 'Doubtful Session',
            'starts_at' => Carbon::tomorrow()->setTime(16, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(17, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);

        $approval = MeetingApproval::where('meeting_id', $meeting->id)->firstOrFail();

        $workflowService = app(ApprovalWorkflowService::class);
        $workflowService->decide($approval, $this->deptAdmin, 'rejected', 'Room not needed for this activity.');

        $this->assertEquals('rejected', $approval->fresh()->decision);
        $this->assertEquals('rejected', $meeting->fresh()->status);
    }

    public function test_delegation_allows_colleague_to_sign_off(): void
    {
        WorkflowRule::create([
            'name' => 'Approval Gate',
            'priority' => 10,
            'conditions' => [],
            'actions' => [
                'require_approval' => [
                    'approver_type' => 'dept_admin',
                    'mode' => 'ANY',
                ],
            ],
            'is_enabled' => true,
        ]);

        $meetingService = app(MeetingService::class);
        $meeting = $meetingService->createMeeting($this->requester, [
            'title' => 'Delegation Test Meeting',
            'starts_at' => Carbon::tomorrow()->setTime(11, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(12, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);

        $approval = MeetingApproval::where('meeting_id', $meeting->id)->firstOrFail();
        $workflowService = app(ApprovalWorkflowService::class);

        // Bob DeptAdmin delegates authority to Dana
        $workflowService->createDelegation(
            user: $this->deptAdmin,
            delegate: $this->delegateUser,
            startsAt: Carbon::yesterday(),
            endsAt: Carbon::tomorrow()->addDay()
        );

        // Dana approves on behalf of Bob
        $workflowService->decide($approval, $this->delegateUser, 'approved', 'Approved on behalf of Bob.');

        $freshApproval = $approval->fresh();
        $this->assertEquals('approved', $freshApproval->decision);
        $this->assertEquals($this->deptAdmin->id, $freshApproval->delegated_from_user_id);
        $this->assertEquals('scheduled', $meeting->fresh()->status);
    }

    public function test_overdue_approval_escalation_command(): void
    {
        WorkflowRule::create([
            'name' => 'Approval Gate',
            'priority' => 10,
            'conditions' => [],
            'actions' => [
                'require_approval' => [
                    'approver_type' => 'dept_admin',
                    'mode' => 'ANY',
                ],
            ],
            'is_enabled' => true,
        ]);

        $meetingService = app(MeetingService::class);
        $meeting = $meetingService->createMeeting($this->requester, [
            'title' => 'Overdue Review Meeting',
            'starts_at' => Carbon::tomorrow()->setTime(11, 0)->toDateTimeString(),
            'ends_at' => Carbon::tomorrow()->setTime(12, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);

        $approval = MeetingApproval::where('meeting_id', $meeting->id)->firstOrFail();
        // Make it overdue
        $approval->update([
            'due_at' => Carbon::yesterday(),
        ]);

        $this->artisan('zpm:workflow:check-approvals')
            ->expectsOutputToContain('Escalated 1 overdue approval(s).')
            ->assertExitCode(0);

        $freshApproval = $approval->fresh();
        $this->assertNotNull($freshApproval->escalated_at);
        $this->assertEquals($this->itAdmin->id, $freshApproval->approver_user_id);
    }
}
