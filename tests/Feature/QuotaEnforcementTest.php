<?php

namespace Tests\Feature;

use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\Quota;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotaEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $privilegedUser;

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
            'name' => 'Physics',
            'code' => 'PHY',
        ]);

        $this->user = User::create([
            'name' => 'Paul Physicist',
            'email' => 'paul@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('faculty');

        $this->privilegedUser = User::create([
            'name' => 'Super User',
            'email' => 'super@example.com',
            'password' => bcrypt('password'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->privilegedUser->assignRole('super_admin');

        $connection = ZoomConnection::create([
            'name' => 'Test Zoom',
            'account_id' => 'acc_test_qe',
            'client_id' => 'cli_test_qe',
            'client_secret' => 'sec_test_qe',
            'enabled' => true,
            'status' => 'active',
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zuid_qe_1',
            'email' => 'licensed_qe1@example.com',
            'first_name' => 'Licensed',
            'last_name' => 'User1',
            'user_type' => 2,
            'status' => 'active',
            'synced_at' => now(),
            'host_key' => '123456',
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'Default Pool',
            'code' => 'DEFAULT_POOL_QE',
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

    public function test_user_monthly_meetings_quota_enforced(): void
    {
        // Limit user to 1 meeting per month
        Quota::create([
            'scope_type' => 'user',
            'scope_id' => $this->user->id,
            'max_meetings_per_month' => 1,
            'is_active' => true,
        ]);

        $meetingService = app(MeetingService::class);

        // 1st booking should succeed
        $meeting1 = $meetingService->createMeeting($this->user, [
            'title' => 'First Allowed Meeting',
            'starts_at' => Carbon::now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::now()->addDays(2)->setTime(11, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);
        $this->assertEquals('scheduled', $meeting1->status);

        // 2nd booking in same month should be rejected
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Quota exceeded: User Paul Physicist has reached the limit of 1 meetings for this month.');

        $meetingService->createMeeting($this->user, [
            'title' => 'Second Exceeding Meeting',
            'starts_at' => Carbon::now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::now()->addDays(3)->setTime(11, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);
    }

    public function test_department_monthly_hours_quota_enforced(): void
    {
        // Limit Physics department to 2 hours per month
        Quota::create([
            'scope_type' => 'department',
            'scope_id' => $this->department->id,
            'max_hours_per_month' => 2,
            'is_active' => true,
        ]);

        $meetingService = app(MeetingService::class);

        // 1st booking: 1.5 hours (90 mins) -> ok
        $meetingService->createMeeting($this->user, [
            'title' => 'Physics Seminar Part 1',
            'starts_at' => Carbon::now()->addDays(2)->setTime(9, 0)->toDateTimeString(),
            'ends_at' => Carbon::now()->addDays(2)->setTime(10, 30)->toDateTimeString(),
            'participant_count' => 10,
        ]);

        // 2nd booking: 1 hour (60 mins) -> total 150 mins > 120 mins (2 hrs) -> should reject
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Quota exceeded: Department has used 1.5 of 2 allocated hours this month.');

        $meetingService->createMeeting($this->user, [
            'title' => 'Physics Seminar Part 2',
            'starts_at' => Carbon::now()->addDays(3)->setTime(9, 0)->toDateTimeString(),
            'ends_at' => Carbon::now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);
    }

    public function test_cancellation_releases_quota_usage(): void
    {
        Quota::create([
            'scope_type' => 'user',
            'scope_id' => $this->user->id,
            'max_meetings_per_month' => 1,
            'is_active' => true,
        ]);

        $meetingService = app(MeetingService::class);

        $meeting = $meetingService->createMeeting($this->user, [
            'title' => 'Temp Meeting',
            'starts_at' => Carbon::now()->addDays(2)->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::now()->addDays(2)->setTime(11, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);

        // Cancel the meeting
        $meetingService->cancelMeeting($meeting, $this->user, 'Cancelled by user');

        // Now user should be able to book again
        $meeting2 = $meetingService->createMeeting($this->user, [
            'title' => 'Replacement Meeting',
            'starts_at' => Carbon::now()->addDays(3)->setTime(10, 0)->toDateTimeString(),
            'ends_at' => Carbon::now()->addDays(3)->setTime(11, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);

        $this->assertEquals('scheduled', $meeting2->status);
    }

    public function test_privileged_user_bypasses_quota_limits(): void
    {
        Quota::create([
            'scope_type' => 'user',
            'scope_id' => $this->privilegedUser->id,
            'max_meetings_per_month' => 0, // 0 allowed
            'is_active' => true,
        ]);

        $meetingService = app(MeetingService::class);

        $meeting = $meetingService->createMeeting($this->privilegedUser, [
            'title' => 'Admin Urgent Meeting',
            'starts_at' => Carbon::now()->addDays(2)->setTime(14, 0)->toDateTimeString(),
            'ends_at' => Carbon::now()->addDays(2)->setTime(15, 0)->toDateTimeString(),
            'participant_count' => 10,
        ]);

        $this->assertEquals('scheduled', $meeting->status);
    }
}
