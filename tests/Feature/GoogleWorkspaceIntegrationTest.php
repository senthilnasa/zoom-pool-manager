<?php

namespace Tests\Feature;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

class GoogleWorkspaceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected User $standardUser;

    protected ResourcePool $pool;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $userRole = Role::create(['name' => 'User', 'guard_name' => 'web']);

        $permSettings = Permission::create(['name' => 'settings.manage', 'guard_name' => 'web']);
        $permMeeting = Permission::create(['name' => 'meeting.create', 'guard_name' => 'web']);

        $adminRole->givePermissionTo([$permSettings, $permMeeting]);
        $userRole->givePermissionTo($permMeeting);

        $department = Department::create(['name' => 'Academic Affairs', 'code' => 'ACAD']);

        $this->adminUser = User::create([
            'name' => 'System Admin',
            'email' => 'admin@krea.edu.in',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');

        $this->standardUser = User::create([
            'name' => 'Faculty User',
            'email' => 'faculty@krea.edu.in',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->standardUser->assignRole('User');

        // Create Zoom pool and resource for allocation
        $connection = ZoomConnection::create([
            'name' => 'Institutional Zoom',
            'account_id' => 'zoom-acc-test',
            'client_id' => 'zoom-client-test',
            'client_secret' => 'zoom-secret-test',
            'enabled' => true,
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm_user_gw_1',
            'email' => 'host1@krea.edu.in',
            'host_key' => '876543',
            'synced_at' => now(),
        ]);

        $resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'managed' => true,
            'status' => 'active',
            'participant_capacity' => 300,
            'priority' => 10,
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'General Faculty Pool',
            'code' => 'POOL_GW_FACULTY',
            'pool_strategy' => 'ROUND_ROBIN',
            'is_active' => true,
        ]);
        $this->pool->resources()->attach($resource->id, ['priority' => 1]);

        $this->apiToken = 'zpm_gw_test_token_secret_12345';
        Setting::set('google_workspace.enabled', true);
        Setting::set('google_workspace.server_url', 'https://zpm.krea.edu.in');
        Setting::set('google_workspace.api_token', $this->apiToken);
        Setting::set('google_workspace.default_pool_id', $this->pool->id);
        Setting::set('google_workspace.share_host_key', true);
    }

    public function test_spa_show_requires_admin_authorization(): void
    {
        // Unauthenticated
        $response = $this->getJson('/spa/settings/google-workspace');
        $response->assertStatus(401);

        // Standard user forbidden
        $response = $this->actingAs($this->standardUser)->getJson('/spa/settings/google-workspace');
        $response->assertStatus(403);

        // Admin success
        $response = $this->actingAs($this->adminUser)->getJson('/spa/settings/google-workspace');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'config' => [
                    'enabled',
                    'server_url',
                    'api_token',
                    'default_pool_id',
                    'default_duration_minutes',
                ],
                'pools',
                'templates',
            ]);
    }

    public function test_spa_update_settings(): void
    {
        $response = $this->actingAs($this->adminUser)->putJson('/spa/settings/google-workspace', [
            'enabled' => true,
            'server_url' => 'https://zoom-pool.krea.edu.in',
            'api_token' => 'zpm_gw_updated_token_999',
            'allowed_domains' => 'krea.edu.in,alumni.krea.edu.in',
            'default_pool_id' => $this->pool->id,
            'default_template_id' => null,
            'default_duration_minutes' => 45,
            'share_host_key' => false,
            'email_template' => '<div>Custom template {meeting_title}</div>',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'config' => [
                    'server_url' => 'https://zoom-pool.krea.edu.in',
                    'api_token' => 'zpm_gw_updated_token_999',
                    'allowed_domains' => 'krea.edu.in,alumni.krea.edu.in',
                    'default_duration_minutes' => 45,
                    'share_host_key' => false,
                ],
            ]);

        $this->assertEquals('https://zoom-pool.krea.edu.in', Setting::get('google_workspace.server_url'));
        $this->assertEquals('zpm_gw_updated_token_999', Setting::get('google_workspace.api_token'));
    }

    public function test_spa_test_connection(): void
    {
        $response = $this->actingAs($this->adminUser)->postJson('/spa/settings/google-workspace/test');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_spa_download_addon_package_zip(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/spa/settings/google-workspace/addon/download');
        $response->assertStatus(200);
        $this->assertEquals('application/zip', $response->headers->get('content-type'));

        $zipFile = $response->getFile()->getPathname();
        $this->assertFileExists($zipFile);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipFile));
        $this->assertIsString($zip->getFromName('appsscript.json'));
        $this->assertIsString($zip->getFromName('Code.gs'));
        $this->assertIsString($zip->getFromName('README.md'));

        $codeContent = $zip->getFromName('Code.gs');
        $this->assertStringContainsString('https://zpm.krea.edu.in', $codeContent);
        $this->assertStringContainsString($this->apiToken, $codeContent);

        $zip->close();
    }

    public function test_api_options_endpoint(): void
    {
        // Unauthenticated
        $this->getJson('/api/v1/integrations/google-workspace/options')
            ->assertStatus(401);

        // Authenticated with token
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
        ])->getJson('/api/v1/integrations/google-workspace/options');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'enabled',
                'default_pool_id',
                'pools',
                'templates',
            ]);
    }

    public function test_api_book_calendar_conference(): void
    {
        $startsAt = Carbon::now()->addHours(3)->toIso8601String();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
        ])->postJson('/api/v1/integrations/google-workspace/book-conference', [
            'user_email' => 'dr.sharma@krea.edu.in',
            'user_name' => 'Dr. Rajesh Sharma',
            'title' => 'Dean Advisory Council Meeting',
            'calendar_event_id' => 'cal_evt_101',
            'starts_at' => $startsAt,
            'duration_minutes' => 60,
            'invitees' => ['provost@krea.edu.in'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'meeting' => [
                    'id',
                    'public_id',
                    'title',
                    'starts_at',
                    'ends_at',
                ],
                'conference_data' => [
                    'conference_id',
                    'entry_points',
                    'notes',
                ],
                'email_snippets' => [
                    'html',
                    'plain_text',
                ],
            ]);

        $meetingId = $response->json('meeting.id');
        $meeting = Meeting::find($meetingId);
        $this->assertNotNull($meeting);
        $this->assertEquals('Dean Advisory Council Meeting', $meeting->title);
        $this->assertEquals('google_workspace', $meeting->source);
        $this->assertEquals('google_calendar', $meeting->custom_fields['booking_channel'] ?? null);
        $this->assertEquals('cal_evt_101', $meeting->custom_fields['google_calendar_event_id'] ?? null);

        // Verify Host Key PIN was included
        $this->assertNotEmpty($meeting->host_key);
        $this->assertStringContainsString('Host Key: 876543', $response->json('conference_data.notes'));
    }

    public function test_api_book_gmail_compose_link(): void
    {
        $startsAt = Carbon::now()->addHours(3)->toIso8601String();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
        ])->postJson('/api/v1/integrations/google-workspace/book-link', [
            'user_email' => 'admissions@krea.edu.in',
            'user_name' => 'Admissions Team',
            'title' => 'Candidate Interview',
            'starts_at' => $startsAt,
            'duration_minutes' => 30,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'meeting',
                'email_snippets' => [
                    'html',
                    'plain_text',
                ],
            ]);

        $this->assertStringContainsString('Candidate Interview', $response->json('email_snippets.html'));
        $this->assertStringContainsString('Join Zoom Meeting', $response->json('email_snippets.html'));
    }

    public function test_spa_regenerate_token(): void
    {
        $oldToken = $this->apiToken;

        $response = $this->actingAs($this->adminUser)->postJson('/spa/settings/google-workspace/regenerate-token');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $newToken = $response->json('api_token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken);
        $this->assertEquals($newToken, Setting::get('google_workspace.api_token'));

        // Verify Audit Log entry was generated
        $audit = AuditLog::where('event', 'google_workspace.token_regenerated')->first();
        $this->assertNotNull($audit);
        $this->assertEquals($this->adminUser->id, $audit->actor_user_id);
    }

    public function test_allowed_domains_restriction_rejects_unauthorized_domain(): void
    {
        Setting::set('google_workspace.allowed_domains', 'krea.edu.in,myschool.edu');

        $startsAt = Carbon::now()->addHours(3)->toIso8601String();

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->apiToken}",
        ])->postJson('/api/v1/integrations/google-workspace/book-conference', [
            'user_email' => 'hacker@externaldomain.com',
            'title' => 'Unauthorized Meeting Attempt',
            'starts_at' => $startsAt,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => 'Validation / Policy Rejection',
            ]);

        $this->assertStringContainsString('is not permitted by organizational security policy', $response->json('message'));
    }
}
