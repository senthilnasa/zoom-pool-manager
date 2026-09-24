<?php

namespace Tests\Feature;

use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GeneralSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Standard User', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin.settings@univ.edu',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');

        $this->user = User::create([
            'name' => 'Standard Student',
            'email' => 'student@univ.edu',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->user->assignRole('Standard User');
    }

    public function test_guest_cannot_access_general_settings(): void
    {
        $response = $this->getJson('/spa/settings/general');
        $response->assertUnauthorized();
    }

    public function test_non_admin_cannot_access_general_settings(): void
    {
        $response = $this->actingAs($this->user)->getJson('/spa/settings/general');
        $response->assertForbidden();
    }

    public function test_admin_can_retrieve_general_settings(): void
    {
        Setting::set('org.name', 'Global Polytechnic University');
        Setting::set('org.min_notice_hours', 4);

        $response = $this->actingAs($this->admin)->getJson('/spa/settings/general');
        $response->assertOk();

        $data = $response->json('settings');
        $this->assertEquals('Global Polytechnic University', $data['org_name']);
        $this->assertEquals(4, $data['org_min_notice_hours']);
    }

    public function test_admin_can_update_general_settings(): void
    {
        $payload = [
            'org_name' => 'Krea Higher Institute of Technology',
            'org_support_email' => 'support@krea.edu.in',
            'org_website' => 'https://zoom.krea.edu.in',
            'org_timezone' => 'Asia/Kolkata',

            'org_min_buffer_minutes' => 15,
            'org_default_buffer_minutes' => 15,
            'org_min_notice_hours' => 3,
            'org_max_advance_days' => 45,
            'org_max_duration_minutes' => 240,
            'host_lead_minutes' => 20,

            'org_ai_companion_policy' => 'ALLOWED',
            'org_default_recording_mode' => 'cloud',
        ];

        $response = $this->actingAs($this->admin)->putJson('/spa/settings/general', $payload);
        $response->assertOk();
        $response->assertJson(['message' => 'General institutional settings updated successfully.']);

        $this->assertEquals('Krea Higher Institute of Technology', Setting::get('org.name'));
        $this->assertEquals('support@krea.edu.in', Setting::get('org.support_email'));
        $this->assertEquals('Asia/Kolkata', Setting::get('org.timezone'));
        $this->assertEquals(15, Setting::get('org.min_buffer_minutes'));
        $this->assertEquals(3, Setting::get('org.min_notice_hours'));
        $this->assertEquals(45, Setting::get('org.max_advance_days'));
        $this->assertEquals(240, Setting::get('org.max_duration_minutes'));
        $this->assertEquals(20, Setting::get('host.lead_minutes'));
        $this->assertEquals('ALLOWED', Setting::get('org.ai_companion_policy'));
        $this->assertEquals('cloud', Setting::get('org.default_recording_mode'));
    }

    public function test_validation_fails_on_invalid_settings(): void
    {
        $payload = [
            'org_name' => '', // required
            'org_support_email' => 'not-an-email',
        ];

        $response = $this->actingAs($this->admin)->putJson('/spa/settings/general', $payload);
        $response->assertStatus(422);
    }

    public function test_admin_can_update_logo_and_legal_policies(): void
    {
        $payload = [
            'org_name' => 'Krea University',
            'org_logo_url' => 'https://krea.edu.in/custom-logo.png',
            'org_support_email' => 'tech@krea.edu.in',
            'org_website' => 'https://krea.edu.in',
            'org_timezone' => 'Asia/Kolkata',

            'privacy_policy_type' => 'custom',
            'privacy_policy_content' => '<h2>Krea Privacy Policy</h2><p>Data is protected.</p>',
            'terms_type' => 'url',
            'terms_url' => 'https://krea.edu.in/terms-of-service',

            'org_min_buffer_minutes' => 10,
            'org_default_buffer_minutes' => 10,
            'org_min_notice_hours' => 2,
            'org_max_advance_days' => 90,
            'org_max_duration_minutes' => 480,
            'host_lead_minutes' => 15,

            'org_ai_companion_policy' => 'ALLOWED',
            'org_default_recording_mode' => 'none',
        ];

        $response = $this->actingAs($this->admin)->putJson('/spa/settings/general', $payload);
        $response->assertOk();

        $this->assertEquals('Krea University', Setting::get('org.name'));
        $this->assertEquals('https://krea.edu.in/custom-logo.png', Setting::get('org.logo_url'));
        $this->assertEquals('custom', Setting::get('legal.privacy_policy_type'));
        $this->assertEquals('<h2>Krea Privacy Policy</h2><p>Data is protected.</p>', Setting::get('legal.privacy_policy_content'));
        $this->assertEquals('url', Setting::get('legal.terms_type'));
        $this->assertEquals('https://krea.edu.in/terms-of-service', Setting::get('legal.terms_url'));
    }

    public function test_admin_can_upload_and_delete_logo(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->create('test_logo.png', 50, 'image/png');

        // Upload logo
        $response = $this->actingAs($this->admin)->postJson('/spa/settings/general/logo', [
            'logo' => $file,
        ]);
        $response->assertOk();
        $this->assertNotEmpty($response->json('logo_url'));
        $this->assertEquals($response->json('logo_url'), Setting::get('org.logo_url'));

        // Delete logo
        $delResponse = $this->actingAs($this->admin)->deleteJson('/spa/settings/general/logo');
        $delResponse->assertOk();
        $this->assertEmpty(Setting::get('org.logo_url'));
    }

    public function test_non_admin_cannot_upload_logo(): void
    {
        $file = UploadedFile::fake()->create('test_logo.png', 50, 'image/png');
        $response = $this->actingAs($this->user)->postJson('/spa/settings/general/logo', [
            'logo' => $file,
        ]);
        $response->assertForbidden();
    }

    public function test_public_legal_and_branding_routes(): void
    {
        Setting::set('org.name', 'Krea Institute');
        Setting::set('legal.privacy_policy_type', 'custom');
        Setting::set('legal.privacy_policy_content', '<p>Our privacy policy content.</p>');
        Setting::set('legal.terms_type', 'url');
        Setting::set('legal.terms_url', 'https://example.com/external-terms');

        // Test public branding endpoint
        $brandingRes = $this->getJson('/spa/branding');
        $brandingRes->assertOk();
        $this->assertEquals('Krea Institute', $brandingRes->json('org_name'));

        // Test public legal API document
        $privacyApi = $this->getJson('/spa/legal/privacy');
        $privacyApi->assertOk();
        $this->assertEquals('custom', $privacyApi->json('type'));
        $this->assertEquals('<p>Our privacy policy content.</p>', $privacyApi->json('content'));

        // Test web view for custom privacy policy
        $privacyWeb = $this->get('/privacy-policy');
        $privacyWeb->assertOk();
        $privacyWeb->assertSee('Our privacy policy content.', false);
        $privacyWeb->assertSee('Krea Institute');

        // Test redirect for external terms
        $termsWeb = $this->get('/terms-of-service');
        $termsWeb->assertRedirect('https://example.com/external-terms');
    }
}
