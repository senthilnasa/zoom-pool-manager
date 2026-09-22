<?php

namespace Tests\Feature;

use App\Domain\Communication\Services\IcsCalendarService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IcsCalendarTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TemplatesAndSecurityProfilesSeeder::class);

        $this->department = Department::create([
            'name' => 'Computer Science',
            'code' => 'CS',
        ]);

        $this->user = User::create([
            'name' => 'Prof. Turing',
            'email' => 'turing@univ.edu',
            'password' => bcrypt('secret123'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('faculty');
    }

    public function test_ics_generation_creates_valid_rfc5545_calendar(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(14, 0);
        $endsAt = Carbon::tomorrow()->setTime(15, 30);

        $meeting = Meeting::create([
            'title' => 'Theory of Computation',
            'description' => 'Turing machines and automata discussion.',
            'meeting_type' => 'class',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participant_count' => 30,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
            'passcode' => '123456',
            'zoom_meeting_id' => '9876543210',
        ]);

        $icsService = app(IcsCalendarService::class);
        $icsContent = $icsService->generate($meeting, 'REQUEST');

        // Verify RFC 5545 structure
        $this->assertStringContainsString('BEGIN:VCALENDAR', $icsContent);
        $this->assertStringContainsString('END:VCALENDAR', $icsContent);
        $this->assertStringContainsString('METHOD:REQUEST', $icsContent);
        $this->assertStringContainsString('BEGIN:VEVENT', $icsContent);
        $this->assertStringContainsString('END:VEVENT', $icsContent);

        // Verify content and stable UID
        $this->assertStringContainsString("UID:zpm-{$meeting->public_id}@", $icsContent);
        $this->assertStringContainsString('SUMMARY:Theory of Computation', $icsContent);
        $this->assertStringContainsString('STATUS:CONFIRMED', $icsContent);

        // Per RFC 5545, lines are folded at 75 characters. Unfold before checking long strings.
        $unfolded = str_replace(["\r\n ", "\n "], '', $icsContent);
        $this->assertStringContainsString('9876543210', $unfolded);
    }

    public function test_ics_cancellation_generates_cancel_method_and_status(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(10, 0);
        $endsAt = Carbon::tomorrow()->setTime(11, 0);

        $meeting = Meeting::create([
            'title' => 'Cancelled Seminar',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participant_count' => 15,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'cancelled',
        ]);

        $icsService = app(IcsCalendarService::class);
        $icsContent = $icsService->generate($meeting, 'CANCEL');

        $this->assertStringContainsString('METHOD:CANCEL', $icsContent);
        $this->assertStringContainsString('STATUS:CANCELLED', $icsContent);
    }

    public function test_direct_ics_download_route(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(16, 0);
        $endsAt = Carbon::tomorrow()->setTime(17, 0);

        $meeting = Meeting::create([
            'title' => 'Office Hours',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participant_count' => 5,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('meetings.ics', $meeting->public_id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=UTF-8');
        $response->assertHeader('Content-Disposition', "attachment; filename=\"meeting-{$meeting->public_id}.ics\"");
        $this->assertStringContainsString('BEGIN:VCALENDAR', $response->getContent());
    }
}
