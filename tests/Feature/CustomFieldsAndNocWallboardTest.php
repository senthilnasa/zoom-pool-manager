<?php

namespace Tests\Feature;

use App\Domain\Communication\Models\EmailDelivery;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\MeetingApproval;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CustomFieldsAndNocWallboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $facultyUser;

    protected User $deptAdmin;

    protected Department $department;

    protected ResourcePool $pool;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);

        $this->department = Department::firstOrCreate(['code' => 'CS'], ['name' => 'Computer Science']);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_test@test.local',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');
        $this->admin->assignRole('super_admin');

        $this->deptAdmin = User::create([
            'name' => 'Dept Admin User',
            'email' => 'dept_admin@test.local',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->deptAdmin->assignRole('dept_admin');
        $this->deptAdmin->assignRole('it_admin');

        $this->facultyUser = User::create([
            'name' => 'Faculty User',
            'email' => 'faculty@test.local',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->facultyUser->assignRole('faculty');

        $connection = ZoomConnection::create([
            'name' => 'Test University',
            'account_id' => 'zoom-acc-1',
            'client_id' => 'zoom-client-1',
            'client_secret' => 'zoom-secret-1',
            'enabled' => true,
            'status' => 'active',
        ]);

        // Setup Zoom Resource & Pool
        $this->pool = ResourcePool::create([
            'name' => 'General Academic Pool',
            'code' => 'ACADEMIC_POOL',
            'description' => 'General pool for faculty',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm_user_test_01',
            'email' => 'zoomhost1@test.local',
            'first_name' => 'Host',
            'last_name' => 'One',
            'display_name' => 'Host License 1',
            'user_type' => 2,
            'status' => 'active',
            'host_key' => '654321',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'participant_capacity' => 100,
            'managed' => true,
            'status' => 'active',
            'priority' => 10,
        ]);
        $this->pool->resources()->attach($this->resource->id, ['priority' => 1]);
    }

    public function test_custom_fields_crud_api(): void
    {
        // 1. Create a custom field as admin
        $createRes = $this->actingAs($this->admin)->postJson('/spa/meeting-custom-fields', [
            'name' => 'Project Code',
            'field_type' => 'text',
            'placeholder' => 'PRJ-1234',
            'is_required' => true,
            'is_active' => true,
        ]);

        $createRes->assertStatus(201);
        $field = $createRes->json('field');
        $this->assertSame('Project Code', $field['name']);
        $this->assertSame('project_code', $field['field_key']);
        $this->assertSame('text', $field['field_type']);
        $this->assertTrue($field['is_required']);

        // 2. Create a dropdown custom field
        $dropRes = $this->actingAs($this->admin)->postJson('/spa/meeting-custom-fields', [
            'name' => 'Academic Level',
            'field_type' => 'dropdown',
            'options' => ['Undergraduate', 'Postgraduate', 'Doctoral'],
            'is_required' => false,
            'is_active' => true,
        ]);
        $dropRes->assertStatus(201);
        $this->assertCount(3, $dropRes->json('field.options'));

        // 3. List custom fields
        $listRes = $this->actingAs($this->admin)->getJson('/spa/meeting-custom-fields');
        $listRes->assertStatus(200);
        $this->assertCount(2, $listRes->json('fields'));

        // 4. Update custom field
        $updateRes = $this->actingAs($this->admin)->putJson("/spa/meeting-custom-fields/{$field['public_id']}", [
            'name' => 'Grant Project Code',
            'field_type' => 'text',
            'is_required' => false,
            'is_active' => true,
        ]);
        $updateRes->assertStatus(200);
        $this->assertSame('Grant Project Code', $updateRes->json('field.name'));

        // 5. Delete custom field
        $deleteRes = $this->actingAs($this->admin)->deleteJson("/spa/meeting-custom-fields/{$field['public_id']}");
        $deleteRes->assertStatus(200);
        $this->assertDatabaseMissing('meeting_custom_fields', ['id' => $field['id']]);
    }

    public function test_booking_meeting_saves_custom_fields_and_requires_approval_for_regular_user(): void
    {
        Setting::set('org.require_meeting_approval', true);
        Setting::set('mail.send_immediately', true);

        // Regular faculty user books a meeting
        $startsAt = Carbon::now()->addHours(3)->setMinute(0)->setSecond(0);
        $endsAt = (clone $startsAt)->addHour();

        $bookingData = [
            'title' => 'Advanced AI Lab Seminar',
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'participant_count' => 15,
            'pool_id' => $this->pool->id,
            'custom_fields' => [
                'project_code' => 'AI-900',
                'academic_level' => 'Postgraduate',
                'expected_credits' => 4,
            ],
            'invitees' => 'student1@test.local, student2@test.local',
        ];

        $res = $this->actingAs($this->facultyUser)->postJson('/meetings', $bookingData);
        $res->assertStatus(200);

        /** @var Meeting $meeting */
        $meeting = Meeting::latest()->first();
        $this->assertNotNull($meeting);
        $this->assertSame('pending_approval', $meeting->status);
        $this->assertSame('AI-900', $meeting->custom_fields['project_code']);
        $this->assertSame('Postgraduate', $meeting->custom_fields['academic_level']);

        // Approvals must be created
        $approval = MeetingApproval::where('meeting_id', $meeting->id)->first();
        $this->assertNotNull($approval);
        $this->assertSame('pending', $approval->decision);

        // Approval requested email must be queued / dispatched
        $email = EmailDelivery::where('meeting_id', $meeting->id)->where('template_key', 'meeting_requested')->first();
        $this->assertNotNull($email);
    }

    public function test_noc_wallboard_api_endpoint(): void
    {
        $nocToken = 'noc_test_secret_token_12345';
        Setting::set('noc.api_token', $nocToken);

        // Create active meeting happening right now
        $liveMeeting = Meeting::create([
            'title' => 'Ongoing Campus All-Hands',
            'meeting_type' => 'meeting',
            'starts_at' => Carbon::now()->subMinutes(15),
            'ends_at' => Carbon::now()->addMinutes(45),
            'participant_count' => 50,
            'requester_user_id' => $this->admin->id,
            'owner_user_id' => $this->admin->id,
            'department_id' => $this->department->id,
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '88812345678',
            'join_url' => 'https://zoom.us/j/88812345678',
            'passcode' => '987654',
            'status' => 'started',
            'custom_fields' => [
                'department_lead' => 'Prof. Kumar',
                'budget_code' => 501,
            ],
        ]);

        // Create upcoming meeting in 2 hours
        $upcomingMeeting = Meeting::create([
            'title' => 'Afternoon Algorithm Discussion',
            'meeting_type' => 'meeting',
            'starts_at' => Carbon::now()->addHours(2),
            'ends_at' => Carbon::now()->addHours(3),
            'participant_count' => 20,
            'requester_user_id' => $this->admin->id,
            'owner_user_id' => $this->admin->id,
            'department_id' => $this->department->id,
            'zoom_resource_id' => $this->resource->id,
            'zoom_meeting_id' => '88899998888',
            'join_url' => 'https://zoom.us/j/88899998888',
            'passcode' => '112233',
            'status' => 'scheduled',
            'custom_fields' => [
                'topic_cluster' => 'Graph Theory',
            ],
        ]);

        // 1. Unauthenticated request should fail
        $unauthRes = $this->getJson('/api/v1/noc/meetings');
        $unauthRes->assertStatus(401);

        // 2. Request with X-NOC-Token header
        $res = $this->withHeaders(['X-NOC-Token' => $nocToken])
            ->getJson('/api/v1/noc/meetings?hours=4');

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('live_count', 1);
        $res->assertJsonPath('total_count', 2);

        /** @var array<int, array<string, mixed>> $meetings */
        $meetings = $res->json('meetings') ?? [];
        $this->assertCount(2, $meetings);

        // Live meeting assertions
        $liveItem = collect($meetings)->firstWhere('id', $liveMeeting->id);
        $this->assertNotNull($liveItem);
        $this->assertTrue((bool) $liveItem['is_live']);
        $this->assertSame('Ongoing Campus All-Hands', $liveItem['title']);
        $this->assertSame('Prof. Kumar', $liveItem['custom_fields']['department_lead']);

        // 3. Query with ?api_key= query parameter and to_time
        $queryRes = $this->getJson("/api/v1/noc/meetings?api_key={$nocToken}&to_time=23:59:59");
        $queryRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(2, (int) $queryRes->json('total_count'));
    }
}
