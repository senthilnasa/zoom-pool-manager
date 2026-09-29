<?php

namespace Tests\Feature;

use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpaMeetingTemplatesAndProfilesTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.edu',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('super_admin');
    }

    public function test_templates_endpoint_self_heals_and_returns_templates_and_profiles(): void
    {
        // Database starts with 0 security profiles
        $this->assertEquals(0, SecurityProfile::count());
        $this->assertEquals(0, MeetingTemplate::count());

        $response = $this->actingAs($this->adminUser)->getJson('/spa/templates');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'templates',
                'profiles',
            ]);

        // Verify self-healing seeded standard profiles and templates
        $this->assertGreaterThan(0, SecurityProfile::count());
        $this->assertGreaterThan(0, MeetingTemplate::count());
        $this->assertNotEmpty($response->json('templates'));
        $this->assertNotEmpty($response->json('profiles'));
    }

    public function test_can_create_new_template_via_spa(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/spa/templates', [
            'name' => 'Executive Board Meeting',
            'code' => 'EXEC_BOARD',
            'description' => 'High-level institutional governance',
            'default_duration_minutes' => 90,
            'max_duration_minutes' => 180,
            'max_participants' => 30,
            'requires_approval' => true,
            'recording_mode' => 'cloud',
            'ai_companion_policy' => 'ALLOWED',
            'series_mode' => 'SINGLE_RESOURCE',
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('meeting_templates', [
            'name' => 'Executive Board Meeting',
            'code' => 'EXEC_BOARD',
        ]);
    }

    public function test_can_update_existing_template(): void
    {
        // First trigger seeding
        $this->actingAs($this->adminUser)->getJson('/spa/templates');
        $template = MeetingTemplate::firstOrFail();

        $response = $this->actingAs($this->adminUser)->postJson('/spa/templates', [
            'id' => $template->id,
            'name' => 'Updated Template Name',
            'code' => $template->code,
            'security_profile_id' => $template->security_profile_id,
            'default_duration_minutes' => 45,
            'max_duration_minutes' => 120,
            'max_participants' => 75,
            'requires_approval' => false,
            'recording_mode' => 'none',
            'ai_companion_policy' => 'ALLOWED',
            'series_mode' => 'SINGLE_RESOURCE',
            'is_active' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('meeting_templates', [
            'id' => $template->id,
            'name' => 'Updated Template Name',
            'default_duration_minutes' => 45,
        ]);
    }

    public function test_security_profiles_endpoint_self_heals(): void
    {
        $this->assertEquals(0, SecurityProfile::count());

        $response = $this->actingAs($this->adminUser)->getJson('/spa/security-profiles');

        $response->assertStatus(200);
        $this->assertGreaterThan(0, SecurityProfile::count());
        $this->assertNotEmpty($response->json());
    }

    public function test_can_create_and_update_security_profile(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/spa/security-profiles', [
            'name' => 'Strict Exam Lockdown',
            'code' => 'EXAM_LOCKDOWN',
            'is_default' => false,
            'settings' => [
                'waiting_room' => true,
                'passcode' => true,
                'join_before_host' => false,
                'mute_upon_entry' => true,
                'authenticated_users_only' => true,
                'allow_recording' => false,
                'ai_companion' => 'DISABLED',
            ],
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('security_profiles', [
            'code' => 'EXAM_LOCKDOWN',
        ]);
    }

    public function test_browser_redirects_route_to_spa(): void
    {
        $this->actingAs($this->adminUser)->get('/templates')
            ->assertRedirect('/app/templates');

        $this->actingAs($this->adminUser)->get('/admin/templates')
            ->assertRedirect('/app/templates');

        $this->actingAs($this->adminUser)->get('/workflows')
            ->assertRedirect('/app/workflows');

        $this->actingAs($this->adminUser)->get(route('workflows.index'))
            ->assertStatus(200);

        $this->actingAs($this->adminUser)->get('/security-profiles')
            ->assertRedirect('/app/security-profiles');

        $this->actingAs($this->adminUser)->get('/admin/security-profiles')
            ->assertRedirect('/app/security-profiles');
    }
}
