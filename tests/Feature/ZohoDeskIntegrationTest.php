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
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use ZipArchive;

class ZohoDeskIntegrationTest extends TestCase
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

        $department = Department::create(['name' => 'IT Services', 'code' => 'IT']);

        $this->adminUser = User::create([
            'name' => 'System Admin',
            'email' => 'admin@krea.edu.in',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');

        $this->standardUser = User::create([
            'name' => 'Student User',
            'email' => 'student@krea.edu.in',
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
            'zoom_user_id' => 'zm_test_host_1',
            'email' => 'host1@krea.edu.in',
            'host_key' => '654321',
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
            'name' => 'Campus General Pool',
            'code' => 'POOL_CAMPUS',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
        ]);
        $this->pool->resources()->attach($resource->id);

        $this->apiToken = 'zpm_zd_test_token_1234567890abcdef';
        Setting::set('zoho_desk.api_token', $this->apiToken);
        Setting::set('zoho_desk.default_pool_id', $this->pool->id);
        Setting::set('zoho_desk.dc', 'in');
        Setting::set('zoho_desk.org_id', '600123456');
    }

    public function test_spa_show_requires_admin_authorization(): void
    {
        $response = $this->actingAs($this->standardUser)->getJson('/spa/settings/zoho-desk');
        $response->assertStatus(403);

        $adminResponse = $this->actingAs($this->adminUser)->getJson('/spa/settings/zoho-desk');
        $adminResponse->assertStatus(200);
        $adminResponse->assertJsonStructure([
            'config' => [
                'enabled',
                'server_url',
                'api_token',
                'dc',
                'org_id',
                'client_id',
                'has_client_secret',
                'has_refresh_token',
                'has_agent_token',
                'default_pool_id',
                'default_duration_minutes',
                'default_is_public',
                'auto_close_ticket',
                'ticket_close_status',
                'comment_template',
                'data_centers',
            ],
            'pools',
            'templates',
        ]);
    }

    public function test_spa_update_settings(): void
    {
        $payload = [
            'enabled' => true,
            'server_url' => 'https://zoom.krea.edu.in',
            'dc' => 'in',
            'org_id' => '700987654',
            'client_id' => '1000.TESTCLIENTID',
            'client_secret' => 'SECRET_XYZ123',
            'refresh_token' => 'REFRESH_ABC987',
            'default_pool_id' => $this->pool->id,
            'default_duration_minutes' => 90,
            'default_is_public' => true,
            'auto_close_ticket' => true,
            'ticket_close_status' => 'Resolved',
            'comment_template' => 'Custom Meeting for {requester_name}: {join_url}',
        ];

        $response = $this->actingAs($this->adminUser)->putJson('/spa/settings/zoho-desk', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'config' => [
                'server_url' => 'https://zoom.krea.edu.in',
                'dc' => 'in',
                'org_id' => '700987654',
                'client_id' => '1000.TESTCLIENTID',
                'has_client_secret' => true,
                'has_refresh_token' => true,
                'default_duration_minutes' => 90,
                'ticket_close_status' => 'Resolved',
            ],
        ]);

        $this->assertEquals('700987654', Setting::get('zoho_desk.org_id'));
        $this->assertEquals('Resolved', Setting::get('zoho_desk.ticket_close_status'));
    }

    public function test_spa_test_connection_with_mocked_zoho_apis(): void
    {
        Http::fake([
            'https://accounts.zoho.in/oauth/v2/token' => Http::response([
                'access_token' => 'mock_zoho_access_token_123',
                'expires_in' => 3600,
            ], 200),
            'https://desk.zoho.in/api/v1/organizations' => Http::response([
                'data' => [
                    [
                        'id' => '600123456',
                        'companyName' => 'Krea University Helpdesk',
                    ],
                ],
            ], 200),
        ]);

        $payload = [
            'dc' => 'in',
            'client_id' => '1000.TESTID',
            'client_secret' => 'TESTSECRET',
            'refresh_token' => 'TESTREFRESH',
        ];

        $response = $this->actingAs($this->adminUser)->postJson('/spa/settings/zoho-desk/test', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'org_name' => 'Krea University Helpdesk',
            'org_id' => '600123456',
        ]);
    }

    public function test_spa_download_extension_package_zip(): void
    {
        $response = $this->actingAs($this->adminUser)->get('/spa/settings/zoho-desk/extension/download');

        $response->assertStatus(200);
        $this->assertEquals('application/zip', $response->headers->get('content-type'));

        $zipFile = $response->getFile()->getPathname();
        $this->assertFileExists($zipFile);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipFile));
        $this->assertIsString($zip->getFromName('manifest.json'));
        $this->assertIsString($zip->getFromName('app/index.html'));
        $this->assertIsString($zip->getFromName('app/js/extension.js'));
        $this->assertIsString($zip->getFromName('app/css/style.css'));
        $this->assertIsString($zip->getFromName('README.md'));

        $extensionJs = $zip->getFromName('app/js/extension.js');
        $this->assertStringContainsString($this->apiToken, $extensionJs);

        $zip->close();
    }

    public function test_api_options_endpoint(): void
    {
        // 1. Unauthorized without token
        $unauth = $this->getJson('/api/v1/integrations/zoho-desk/options');
        $unauth->assertStatus(401);

        // 2. Authorized via X-API-KEY header
        $authRes = $this->withHeaders([
            'X-API-KEY' => $this->apiToken,
        ])->getJson('/api/v1/integrations/zoho-desk/options');

        $authRes->assertStatus(200);
        $authRes->assertJson([
            'success' => true,
            'default_pool_id' => $this->pool->id,
            'default_duration_minutes' => 60,
            'default_is_public' => true,
        ]);
        $this->assertNotEmpty($authRes->json('pools'));
    }

    public function test_api_book_and_reply_creates_meeting_on_behalf_and_posts_to_zoho_desk(): void
    {
        Http::fake([
            'https://accounts.zoho.in/oauth/v2/token' => Http::response([
                'access_token' => 'mock_zoho_token_active',
                'expires_in' => 3600,
            ], 200),
            'https://desk.zoho.in/api/v1/tickets/90123/comments' => Http::response([
                'id' => 'comment_999888',
                'content' => 'Scheduled meeting',
            ], 200),
            'https://desk.zoho.in/api/v1/tickets/90123' => Http::response([
                'id' => '90123',
                'status' => 'Closed',
            ], 200),
        ]);

        Setting::set('zoho_desk.client_id', '1000.TEST');
        Setting::set('zoho_desk.client_secret', 'TESTSECRET');
        Setting::set('zoho_desk.refresh_token', 'TESTREFRESH');
        Setting::set('zoho_desk.org_id', '600123456');

        $startsAt = Carbon::now()->addHours(3)->setMinute(0)->setSecond(0);

        $payload = [
            'ticket_id' => '90123',
            'ticket_number' => 'TKT-5544',
            'ticket_subject' => 'Need Zoom link for Faculty Committee',
            'ticket_email' => 'prof.sharma@krea.edu.in',
            'ticket_contact_name' => 'Prof. Sharma',
            'title' => 'Faculty Committee Review Session',
            'starts_at' => $startsAt->toIso8601String(),
            'duration_minutes' => 60,
            'pool_id' => $this->pool->id,
            'post_to_ticket' => true,
            'is_public' => true,
            'close_ticket' => true,
            'share_host_key' => true,
        ];

        $response = $this->withHeaders([
            'X-API-KEY' => $this->apiToken,
        ])->postJson('/api/v1/integrations/zoho-desk/book-and-reply', $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'ticket_comment_posted' => true,
            'ticket_closed' => true,
        ]);

        // Assert user was created on-the-fly for the ticket requester
        $createdUser = User::where('email', 'prof.sharma@krea.edu.in')->first();
        $this->assertNotNull($createdUser);
        $this->assertEquals('Prof. Sharma', $createdUser->name);

        // Assert meeting was created with ticket user as owner and source = zoho_desk
        $meeting = Meeting::where('owner_user_id', $createdUser->id)->first();
        $this->assertNotNull($meeting);
        $this->assertEquals('Faculty Committee Review Session', $meeting->title);
        $this->assertEquals('zoho_desk', $meeting->source);
        $this->assertEquals('scheduled', $meeting->status);
        $this->assertNotEmpty($meeting->zoom_meeting_id);
        $this->assertNotEmpty($meeting->join_url);
        $this->assertEquals('654321', $meeting->host_key);

        // Assert comment contains required meeting details
        $commentText = $response->json('comment_text');
        $this->assertStringContainsString('Faculty Committee Review Session', $commentText);
        $this->assertStringContainsString($meeting->join_url, $commentText);
        $this->assertStringContainsString($meeting->zoom_meeting_id, $commentText);
        $this->assertStringContainsString($meeting->passcode, $commentText);
        $this->assertStringContainsString('654321', $commentText);
        $this->assertStringContainsString('Prof. Sharma', $commentText);

        // Assert audit log was recorded
        $audit = AuditLog::where('event', 'zoho_desk.meeting_booked')->first();
        $this->assertNotNull($audit);
    }
}
