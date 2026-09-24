<?php

namespace Tests\Feature;

use App\Domain\Communication\Models\NotificationPreference;
use App\Domain\Communication\Services\MailDeliveryService;
use App\Domain\Communication\Services\NotificationCenterService;
use App\Domain\Communication\Services\TemplateRenderer;
use App\Domain\Mail\Drivers\LogMailProvider;
use App\Domain\Mail\Services\MailProviderManager;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Carbon\Carbon;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $admin;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TemplatesAndSecurityProfilesSeeder::class);
        $this->seed(EmailTemplatesSeeder::class);

        Setting::set('mail.provider', 'log');

        $this->department = Department::create([
            'name' => 'Physics',
            'code' => 'PHYS',
        ]);

        $this->user = User::create([
            'name' => 'Dr. Richard Feynman',
            'email' => 'feynman@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('faculty');

        $this->admin = User::create([
            'name' => 'IT Admin User',
            'email' => 'admin@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->admin->assignRole('it_admin');

        LogMailProvider::reset();
    }

    public function test_mail_provider_manager_resolves_configured_driver(): void
    {
        $manager = app(MailProviderManager::class);

        Setting::set('mail.provider', 'log');
        $this->assertEquals('log', $manager->getProvider()->getDriverName());

        Setting::set('mail.provider', 'smtp');
        $this->assertEquals('smtp', $manager->getProvider()->getDriverName());
    }

    public function test_deduplicated_mail_queue_prevents_duplicate_deliveries(): void
    {
        $mailService = app(MailDeliveryService::class);

        $startsAt = Carbon::tomorrow()->setTime(11, 0);
        $endsAt = Carbon::tomorrow()->setTime(12, 0);

        $meeting = Meeting::create([
            'title' => 'Quantum Electrodynamics',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'participant_count' => 20,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
        ]);

        // 1. Send first email synchronously
        $delivery1 = $mailService->queueEmail(
            templateKey: 'meeting_confirmed',
            recipientEmail: $this->user->email,
            recipientName: $this->user->name,
            context: ['meeting' => $meeting],
            meeting: $meeting,
            eventId: "meeting_confirmed_{$meeting->id}",
            sync: true
        );

        $this->assertNotNull($delivery1);
        $this->assertEquals('sent', $delivery1->fresh()->status);
        $this->assertCount(1, LogMailProvider::$sentMessages);

        // 2. Dispatch same event again with identical dedupe key
        $delivery2 = $mailService->queueEmail(
            templateKey: 'meeting_confirmed',
            recipientEmail: $this->user->email,
            recipientName: $this->user->name,
            context: ['meeting' => $meeting],
            meeting: $meeting,
            eventId: "meeting_confirmed_{$meeting->id}",
            sync: true
        );

        // Should return existing delivery and NOT send another message
        $this->assertEquals($delivery1->id, $delivery2->id);
        $this->assertCount(1, LogMailProvider::$sentMessages);
    }

    public function test_template_renderer_safely_interpolates_and_protects_secrets(): void
    {
        $renderer = app(TemplateRenderer::class);

        $meeting = Meeting::create([
            'title' => 'Confidential Quantum Lab',
            'starts_at' => Carbon::tomorrow()->setTime(10, 0),
            'ends_at' => Carbon::tomorrow()->setTime(11, 0),
            'participant_count' => 5,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
        ]);

        $subject = 'Meeting {{meeting.title}} for {{recipient.name}}';
        $html = '<p>Hello {{recipient.name}}</p><script>alert("hack")</script><p>Host URL: {{meeting.start_url}}</p>';
        $text = 'Meeting: {{meeting.title}}';

        $rendered = $renderer->render($subject, $html, $text, [
            'meeting' => $meeting,
            'recipient' => $this->user,
        ]);

        // Subject interpolated
        $this->assertEquals('Meeting Confidential Quantum Lab for Dr. Richard Feynman', $rendered['subject']);

        // Script stripped
        $this->assertStringNotContainsString('<script>', $rendered['html']);

        // start_url must NOT expose actual Zoom host launch URL
        $this->assertStringNotContainsString('zoom.us/s/', $rendered['html']);
        $this->assertStringContainsString(route('meetings.show', $meeting->public_id), $rendered['html']);
    }

    public function test_in_app_notification_center_lifecycle(): void
    {
        $center = app(NotificationCenterService::class);

        $this->assertEquals(0, $center->getUnreadCount($this->user));

        // Send notification
        $notification = $center->notify(
            user: $this->user,
            type: 'meeting_confirmed',
            title: 'Your Quantum Class is Booked',
            message: 'Resource allocated successfully.',
            data: ['meeting_id' => '01test123']
        );

        $this->assertNotNull($notification);
        $this->assertEquals(1, $center->getUnreadCount($this->user));

        // Mark as read
        $marked = $center->markAsRead($this->user, $notification->id);
        $this->assertTrue($marked);
        $this->assertEquals(0, $center->getUnreadCount($this->user));
    }

    public function test_notification_preference_blocks_disabling_security_alerts(): void
    {
        $center = app(NotificationCenterService::class);

        // Explicitly set preference to disabled for security_alert
        NotificationPreference::create([
            'user_id' => $this->user->id,
            'channel' => 'in_app',
            'notification_type' => 'security_alert',
            'enabled' => false,
        ]);

        // Critical notifications cannot be disabled
        $this->assertTrue($center->isChannelEnabled($this->user, 'in_app', 'security_alert'));

        // Non-critical notification CAN be disabled
        NotificationPreference::create([
            'user_id' => $this->user->id,
            'channel' => 'in_app',
            'notification_type' => 'general_announcement',
            'enabled' => false,
        ]);

        $this->assertFalse($center->isChannelEnabled($this->user, 'in_app', 'general_announcement'));
    }

    public function test_send_meeting_reminders_command(): void
    {
        // Meeting starting in 10 minutes
        $meeting = Meeting::create([
            'title' => 'Imminent Physics Review',
            'starts_at' => Carbon::now()->addMinutes(10),
            'ends_at' => Carbon::now()->addMinutes(70),
            'participant_count' => 12,
            'requester_user_id' => $this->user->id,
            'owner_user_id' => $this->user->id,
            'department_id' => $this->department->id,
            'status' => 'scheduled',
        ]);

        $this->artisan('zpm:notifications:send-reminders')
            ->assertSuccessful();

        $this->assertDatabaseHas('email_deliveries', [
            'meeting_id' => $meeting->id,
            'template_key' => 'start_reminder',
            'recipient_email' => $this->user->email,
        ]);
    }

    public function test_mail_admin_web_routes(): void
    {
        // 1. Mail Settings page
        $res = $this->actingAs($this->admin)->get(route('admin.mail.index'));
        $res->assertOk();

        // 2. Email Templates page
        $res = $this->actingAs($this->admin)->get(route('admin.templates.index'));
        $res->assertOk();

        // 3. Test connection endpoint
        $res = $this->actingAs($this->admin)->postJson(route('admin.mail.test-connection'));
        $res->assertOk();
        $res->assertJson(['success' => true]);

        // 4. Test send endpoint
        $res = $this->actingAs($this->admin)->postJson(route('admin.mail.test-send'), [
            'test_email' => 'test@example.com',
        ]);
        $res->assertOk();
        $res->assertJson(['success' => true]);

        // 5. Deliveries outbox page
        $res = $this->actingAs($this->admin)->get(route('admin.mail.deliveries'));
        $res->assertOk();

        // 6. User Notifications page
        $res = $this->actingAs($this->user)->get(route('notifications.index'));
        $res->assertOk();
    }

    public function test_mail_settings_update_accepts_current_provider_payload(): void
    {
        $payload = [
            'current_provider' => 'smtp',
            'from_address' => 'noreply@krea.edu.in',
            'from_name' => 'IT Team Noreply',
            'reply_to' => '',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'firewall.ttk@krea.edu.in',
            'smtp_password' => 'secret123',
        ];

        $res = $this->actingAs($this->admin)->postJson(route('admin.mail.update'), $payload);
        $res->assertOk();
        $res->assertJson(['success' => true]);

        $this->assertSame('smtp', Setting::get('mail.provider'));
        $this->assertSame('noreply@krea.edu.in', Setting::get('mail.from_address'));
        $this->assertSame('smtp.gmail.com', Setting::get('mail.smtp_host'));
        $this->assertSame(465, (int) Setting::get('mail.smtp_port'));
        $this->assertSame('ssl', Setting::get('mail.smtp_encryption'));

        // Verify index JSON returns both provider and current_provider
        $indexRes = $this->actingAs($this->admin)->getJson(route('admin.mail.index'));
        $indexRes->assertOk();
        $indexRes->assertJsonFragment([
            'provider' => 'smtp',
            'current_provider' => 'smtp',
            'from_address' => 'noreply@krea.edu.in',
        ]);
    }
}
