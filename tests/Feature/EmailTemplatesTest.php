<?php

namespace Tests\Feature;

use App\Domain\Communication\Models\EmailTemplate;
use App\Domain\Communication\Services\EmailTemplateRegistry;
use App\Domain\Mail\Drivers\LogMailProvider;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $regularUser;

    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Setting::set('mail.provider', 'log');
        LogMailProvider::reset();

        $this->department = Department::create([
            'name' => 'Information Technology',
            'code' => 'IT',
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@zoompool.test',
            'password' => bcrypt('password123'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->admin->assignRole('it_admin');

        $this->regularUser = User::create([
            'name' => 'Regular User',
            'email' => 'user@zoompool.test',
            'password' => bcrypt('password123'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->regularUser->assignRole('faculty');
    }

    public function test_non_admin_cannot_access_email_templates(): void
    {
        $response = $this->actingAs($this->regularUser)->get(route('admin.templates.index'));
        $response->assertStatus(403);
    }

    public function test_admin_can_view_and_auto_seed_all_eleven_templates(): void
    {
        $this->assertEquals(0, EmailTemplate::count());

        $response = $this->actingAs($this->admin)->getJson(route('admin.templates.index'));
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertArrayHasKey('templates', $data);
        $this->assertArrayHasKey('categories', $data);
        $this->assertCount(11, $data['templates']);

        $this->assertDatabaseHas('email_templates', [
            'key' => 'meeting_attendance_report',
            'category' => 'attendance',
        ]);
        $this->assertDatabaseHas('email_templates', [
            'key' => 'recording_ready',
            'category' => 'recordings',
        ]);
    }

    public function test_admin_can_view_single_template_details_and_defaults(): void
    {
        EmailTemplateRegistry::seedMissingTemplates();
        $template = EmailTemplate::where('key', 'meeting_confirmed')->firstOrFail();

        $response = $this->actingAs($this->admin)->getJson(route('admin.templates.show', $template->public_id));
        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals('meeting_confirmed', $json['template']['key']);
        $this->assertNotNull($json['default']);
        $this->assertArrayHasKey('available_variables', $json['default']);
    }

    public function test_admin_can_update_email_template(): void
    {
        EmailTemplateRegistry::seedMissingTemplates();
        $template = EmailTemplate::where('key', 'meeting_confirmed')->firstOrFail();

        $response = $this->actingAs($this->admin)->putJson(route('admin.templates.update', $template->public_id), [
            'name' => 'Custom Confirmed Name',
            'category' => 'bookings',
            'description' => 'Custom description here',
            'subject_template' => 'Custom Subject for {{ meeting_title }}',
            'body_html_template' => '<p>Hello {{ user_name }}, meeting is confirmed!</p>',
            'body_text_template' => 'Hello {{ user_name }}, meeting is confirmed!',
            'is_active' => false,
        ]);

        $response->assertStatus(200);
        $template->refresh();

        $this->assertEquals('Custom Confirmed Name', $template->name);
        $this->assertEquals('Custom Subject for {{ meeting_title }}', $template->subject_template);
        $this->assertFalse((bool) $template->is_active);
    }

    public function test_admin_can_preview_template_with_mock_data(): void
    {
        EmailTemplateRegistry::seedMissingTemplates();
        $template = EmailTemplate::where('key', 'meeting_confirmed')->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson(route('admin.templates.preview', $template->public_id), [
            'subject_template' => 'Meeting: {{ meeting.title }} (PIN: {{ host_key_pin }})',
            'body_html_template' => '<h1>{{ meeting_title }}</h1><p>Join: {{ join_url }}</p>',
            'body_text_template' => 'Meeting: {{ meeting_title }}\nJoin: {{ join_url }}',
        ]);

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertStringContainsString('Advanced Robotics', $json['subject']);
        $this->assertStringContainsString('618492', $json['subject']);
        $this->assertStringContainsString('Advanced Robotics', $json['html']);
    }

    public function test_admin_can_reset_single_template_to_default(): void
    {
        EmailTemplateRegistry::seedMissingTemplates();
        $template = EmailTemplate::where('key', 'meeting_confirmed')->firstOrFail();

        $template->update([
            'subject_template' => 'Tampered Subject',
            'body_html_template' => '<p>Tampered HTML</p>',
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('admin.templates.reset', $template->public_id));
        $response->assertStatus(200);

        $template->refresh();
        $this->assertStringNotContainsString('Tampered Subject', $template->subject_template);
        $this->assertStringContainsString('Confirmed', $template->subject_template);
    }

    public function test_admin_can_reset_all_templates_to_default(): void
    {
        EmailTemplateRegistry::seedMissingTemplates();
        EmailTemplate::query()->update(['subject_template' => 'All Tampered']);

        $response = $this->actingAs($this->admin)->postJson(route('admin.templates.reset-all'));
        $response->assertStatus(200);

        $this->assertDatabaseMissing('email_templates', [
            'subject_template' => 'All Tampered',
        ]);
    }

    public function test_admin_can_send_test_email(): void
    {
        EmailTemplateRegistry::seedMissingTemplates();
        $template = EmailTemplate::where('key', 'recording_ready')->firstOrFail();

        $response = $this->actingAs($this->admin)->postJson(route('admin.templates.send-test', $template->public_id), [
            'recipient_email' => 'test-recipient@example.com',
            'subject_template' => 'Custom Test Subject {{ recording.topic }}',
            'body_html_template' => '<p>Test recording url: {{ recording.share_url }}</p>',
            'body_text_template' => 'Test recording url: {{ recording.share_url }}',
        ]);

        $response->assertStatus(200);
        $sentMails = LogMailProvider::getSentMails();
        $this->assertNotEmpty($sentMails);

        $lastMail = end($sentMails);
        $this->assertEquals('test-recipient@example.com', $lastMail['toEmail']);
        $this->assertStringContainsString('[TEST]', $lastMail['subject']);
        $this->assertStringContainsString('https://krea-edu-in.zoom.us/rec/share/', $lastMail['htmlBody']);
    }
}
