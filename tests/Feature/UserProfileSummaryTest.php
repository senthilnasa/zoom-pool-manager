<?php

namespace Tests\Feature;

use App\Domain\Attendance\Models\MeetingAttendance;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\MeetingApproval;
use App\Domain\Workflow\Models\Quota;
use App\Domain\Workflow\Models\QuotaUsage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserProfileSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Department $department;

    protected User $facultyUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

        $this->department = Department::create([
            'name' => 'School of Humanities',
            'code' => 'SOH',
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'System Admin',
            'email' => 'sysadmin@university.edu',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');

        $this->facultyUser = User::create([
            'name' => 'Dr. Radhakrishnan',
            'email' => 'radhakrishnan@university.edu',
            'designation' => 'Professor of Philosophy',
            'department_id' => $this->department->id,
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->facultyUser->assignRole('User');
    }

    public function test_guest_cannot_access_user_profile(): void
    {
        $res = $this->getJson("/spa/users/{$this->facultyUser->public_id}/profile");
        $res->assertStatus(401);
    }

    public function test_authenticated_user_can_fetch_user_profile_and_level_summary(): void
    {
        // 1. Create meeting requests for faculty user
        $m1 = Meeting::create([
            'title' => 'Ancient Indian Philosophy Seminar',
            'meeting_type' => 'instant',
            'status' => 'completed',
            'starts_at' => Carbon::now()->subDays(2),
            'ends_at' => Carbon::now()->subDays(2)->addMinutes(90),
            'requester_user_id' => $this->facultyUser->id,
            'owner_user_id' => $this->facultyUser->id,
            'department_id' => $this->department->id,
            'participant_count' => 35,
        ]);

        $m2 = Meeting::create([
            'title' => 'Ethics in Modern Thought Lecture',
            'meeting_type' => 'scheduled',
            'status' => 'scheduled',
            'starts_at' => Carbon::now()->addDays(2),
            'ends_at' => Carbon::now()->addDays(2)->addMinutes(60),
            'requester_user_id' => $this->facultyUser->id,
            'owner_user_id' => $this->facultyUser->id,
            'department_id' => $this->department->id,
            'participant_count' => 50,
        ]);

        $m3 = Meeting::create([
            'title' => 'Postponed Workshop',
            'meeting_type' => 'scheduled',
            'status' => 'cancelled',
            'starts_at' => Carbon::now()->addDays(5),
            'ends_at' => Carbon::now()->addDays(5)->addMinutes(120),
            'requester_user_id' => $this->facultyUser->id,
            'owner_user_id' => $this->facultyUser->id,
            'department_id' => $this->department->id,
            'participant_count' => 10,
        ]);

        // 2. Create approval request
        MeetingApproval::create([
            'meeting_id' => $m2->id,
            'step' => 1,
            'approver_user_id' => $this->admin->id,
            'decision' => 'approved',
            'decision_notes' => 'Approved for semester lecture series.',
            'decided_at' => Carbon::now(),
        ]);

        // 3. Create attendance log
        MeetingAttendance::create([
            'meeting_id' => $m1->id,
            'zoom_meeting_id' => '98765432101',
            'participant_name' => 'Scholar Student',
            'participant_email' => 'student@university.edu',
            'join_time' => Carbon::now()->subDays(2),
            'leave_time' => Carbon::now()->subDays(2)->addMinutes(85),
            'duration_seconds' => 5100,
        ]);

        // 4. Create department quota
        $quota = Quota::create([
            'scope_type' => 'department',
            'scope_id' => $this->department->id,
            'max_meetings_per_month' => 40,
            'max_hours_per_month' => 80,
            'is_active' => true,
        ]);

        QuotaUsage::create([
            'quota_id' => $quota->id,
            'period_year' => (int) date('Y'),
            'period_month' => (int) date('n'),
            'meetings_count' => 8,
            'minutes_used' => 720,
        ]);

        // Fetch User Profile using public_id
        $res = $this->actingAs($this->admin)->getJson("/spa/users/{$this->facultyUser->public_id}/profile");
        $res->assertOk();

        // Validate user details
        $res->assertJsonPath('user.name', 'Dr. Radhakrishnan');
        $res->assertJsonPath('user.email', 'radhakrishnan@university.edu');
        $res->assertJsonPath('user.designation', 'Professor of Philosophy');
        $res->assertJsonPath('user.department.name', 'School of Humanities');

        // Validate summary metrics
        $res->assertJsonPath('summary.total_requests', 3);
        $res->assertJsonPath('summary.scheduled_requests', 1);
        $res->assertJsonPath('summary.completed_requests', 1);
        $res->assertJsonPath('summary.cancelled_requests', 1);
        $res->assertJsonPath('summary.approvals_submitted', 1);
        $res->assertJsonPath('summary.approvals_approved', 1);
        $res->assertJsonPath('summary.attendance_sessions_count', 1);

        // Validate quota
        $res->assertJsonPath('quota.max_meetings_per_month', 40);
        $res->assertJsonPath('quota.current_month_meetings', 8);
        $res->assertJsonPath('quota.current_month_hours', 12);

        // Validate meetings list contains titles
        $titles = collect($res->json('meetings'))->pluck('title');
        $this->assertTrue($titles->contains('Ancient Indian Philosophy Seminar'));
        $this->assertTrue($titles->contains('Ethics in Modern Thought Lecture'));
        $this->assertTrue($titles->contains('Postponed Workshop'));

        // Validate approvals list
        $this->assertCount(1, $res->json('approvals'));
        $this->assertEquals('approved', $res->json('approvals.0.decision'));
    }

    public function test_user_profile_can_be_retrieved_by_numeric_id(): void
    {
        $res = $this->actingAs($this->admin)->getJson("/spa/users/{$this->facultyUser->id}/profile");
        $res->assertOk();
        $res->assertJsonPath('user.id', $this->facultyUser->id);
        $res->assertJsonPath('user.name', 'Dr. Radhakrishnan');
    }
}
