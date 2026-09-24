<?php

namespace Tests\Feature;

use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
