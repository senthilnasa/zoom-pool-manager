<?php

namespace Tests\Feature;

use App\Domain\Communication\Services\IcsCalendarService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
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

    public function test_ics_generation_includes_credentials_when_mask_credentials_disabled(): void
    {
        Setting::set('mail.mask_credentials', false);

        $startsAt = Carbon::tomorrow()->setTime(16, 0);
        $endsAt = Carbon::tomorrow()->setTime(17, 0);

        $meeting = Meeting::create([
            'title' => 'Open Seminar',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participant_count' => 10,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
            'passcode' => 'SeminarPass',
            'host_key' => '123987',
            'share_host_key' => false,
        ]);

        $icsService = app(IcsCalendarService::class);
        $icsContent = $icsService->generate($meeting, 'REQUEST');
        $unfolded = str_replace(["\r\n ", "\n "], '', $icsContent);

        $this->assertStringContainsString('Passcode: SeminarPass', $unfolded);
        $this->assertStringContainsString('Host Key PIN: 123987', $unfolded);
    }

    public function test_host_calendar_uses_publish_method_and_excludes_organizer_attendee(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(11, 0);
        $endsAt = Carbon::tomorrow()->setTime(12, 0);

        $meeting = Meeting::create([
            'title' => 'Faculty Research Meeting',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
        ]);

        $icsService = app(IcsCalendarService::class);
        $icsContent = $icsService->generate($meeting, 'PUBLISH', null, $this->user->email);

        $this->assertStringContainsString('METHOD:PUBLISH', $icsContent);
        $this->assertStringContainsString('ORGANIZER;', $icsContent);
        $this->assertStringContainsString('CN=Prof. Turing:MAILTO:turing@univ.edu', $icsContent);
        // Ensure organizer is NOT listed as an attendee to prevent Google Calendar "Unable to load event"
        $this->assertStringNotContainsString('ATTENDEE;CN=Prof. Turing', $icsContent);
    }

    public function test_ics_injects_sent_by_header_when_from_address_configured(): void
    {
        Setting::set('mail.from_address', 'mailer@univ.edu');

        $startsAt = Carbon::tomorrow()->setTime(14, 0);
        $endsAt = Carbon::tomorrow()->setTime(15, 0);

        $meeting = Meeting::create([
            'title' => 'Department Seminar',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
        ]);

        $icsService = app(IcsCalendarService::class);
        $icsContent = $icsService->generate($meeting, 'PUBLISH');

        $this->assertStringContainsString('ORGANIZER;SENT-BY="mailto:mailer@univ.edu";CN=Prof. Turing:MAILTO:turing@univ.edu', $icsContent);
    }

    public function test_template_renderer_generates_google_calendar_url_and_schema_org_json_ld(): void
    {
        $startsAt = Carbon::tomorrow()->setTime(9, 30);
        $endsAt = Carbon::tomorrow()->setTime(10, 30);

        $meeting = Meeting::create([
            'title' => 'Robotics Seminar',
            'description' => 'Autonomous systems discussion.',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
            'join_url' => 'https://zoom.us/j/999888777',
        ]);

        $deliveryService = app(\App\Domain\Communication\Services\MailDeliveryService::class);
        $delivery = $deliveryService->queueEmail(
            templateKey: 'meeting_confirmed',
            recipientEmail: $this->user->email,
            recipientName: $this->user->name,
            context: ['meeting' => $meeting],
            meeting: $meeting,
            sync: true
        );

        $this->assertNotNull($delivery);
        $this->assertStringContainsString('https://calendar.google.com/calendar/render?action=TEMPLATE', $delivery->body_html);
        $this->assertStringContainsString('https://outlook.live.com/calendar/0/deeplink/compose', $delivery->body_html);
        $this->assertStringContainsString('"@type": "EventReservation"', $delivery->body_html);
        $this->assertStringContainsString('"name": "Robotics Seminar"', $delivery->body_html);
    }
}
