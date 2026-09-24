<?php

namespace Tests\Feature;

use App\Domain\Attendance\Models\MeetingAttendance;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SpaModernizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Standard User', 'guard_name' => 'web']);
    }

    public function test_spa_shell_renders_vue_entry_and_anti_fouc_script(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
            'theme' => 'system',
        ]);
        $user->assignRole('Standard User');

        $response = $this->actingAs($user)->get('/app');

        $response->assertStatus(200);
        $response->assertSee('<div id="app"', false);
        $response->assertSee('localStorage.getItem(\'zpm_theme\')', false);
        $response->assertSee('window.__ZPM__', false);
        $response->assertSee('favicon.svg', false);
        $response->assertSee('favicon.ico', false);
        $response->assertSee('Made with ❤️ by', false);
        $response->assertSee('https://github.com/senthilnasa', false);
    }

    public function test_spa_shell_renders_on_nested_wildcard_routes(): void
    {
        $user = User::create([
            'name' => 'Recordings User',
            'email' => 'recordings@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $user->assignRole('Standard User');

        // Test wildcard route /app/recordings which was failing with TypeError
        $response = $this->actingAs($user)->get('/app/recordings');
        $response->assertStatus(200);
        $response->assertSee('<div id="app"', false);

        // Test other wildcard routes
        $response2 = $this->actingAs($user)->get('/app/dashboard');
        $response2->assertStatus(200);

        $response3 = $this->actingAs($user)->get('/app/meetings/create');
        $response3->assertStatus(200);
    }

    public function test_spa_auth_me_endpoint_returns_user_payload(): void
    {
        $user = User::create([
            'name' => 'Jane Admin',
            'email' => 'jane@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
            'theme' => 'dark',
        ]);
        $user->assignRole('Super Administrator');

        $response = $this->actingAs($user)->getJson('/spa/auth/me');

        $response->assertStatus(200);
        $response->assertJson([
            'authenticated' => true,
            'user' => [
                'name' => 'Jane Admin',
                'email' => 'jane@univ.edu',
                'theme' => 'dark',
                'is_admin' => true,
            ],
        ]);
    }

    public function test_spa_auth_theme_endpoint_persists_theme_preference(): void
    {
        $user = User::create([
            'name' => 'Theme Tester',
            'email' => 'theme@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
            'theme' => 'system',
        ]);
        $user->assignRole('Standard User');

        $response = $this->actingAs($user)->postJson('/spa/auth/theme', [
            'theme' => 'dark',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'theme' => 'dark',
        ]);

        $this->assertEquals('dark', $user->fresh()->theme);

        // Switch to light
        $responseLight = $this->actingAs($user)->postJson('/spa/auth/theme', [
            'theme' => 'light',
        ]);
        $responseLight->assertStatus(200);
        $this->assertEquals('light', $user->fresh()->theme);

        // Switch to system default
        $responseSystem = $this->actingAs($user)->postJson('/spa/auth/theme', [
            'theme' => 'system',
        ]);
        $responseSystem->assertStatus(200);
        $this->assertEquals('system', $user->fresh()->theme);

        // Invalid theme should fail validation
        $responseInvalid = $this->actingAs($user)->postJson('/spa/auth/theme', [
            'theme' => 'invalid_theme',
        ]);
        $responseInvalid->assertStatus(422);
    }

    public function test_spa_dashboard_stats_endpoint(): void
    {
        $user = User::create([
            'name' => 'Stats User',
            'email' => 'stats@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $user->assignRole('Standard User');

        $response = $this->actingAs($user)->getJson('/spa/dashboard/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'stats' => [
                'meetings_today',
                'active_meetings',
                'upcoming_meetings',
                'total_pools',
                'total_licenses',
                'active_licenses',
                'pending_approvals',
            ],
            'recent_meetings',
            'pools',
            'demo_mode',
            'app_version',
        ]);
    }

    public function test_spa_zoom_settings_and_test_connection(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin.zoom@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Administrator');

        // Initial show should return safe config
        $response = $this->actingAs($admin)->getJson('/spa/settings/zoom');
        $response->assertStatus(200);

        // Update settings
        $updateResponse = $this->actingAs($admin)->postJson('/spa/settings/zoom', [
            'name' => 'Primary Production Zoom',
            'account_id' => 'test_acc_123',
            'client_id' => 'test_client_id_456',
            'client_secret' => 'super_secret_test',
            'webhook_secret_token' => 'webhook_secret_123',
            'enabled' => true,
        ]);

        $updateResponse->assertStatus(200);
        $updateResponse->assertJson([
            'success' => true,
            'config' => [
                'account_id' => 'test_acc_123',
                'client_id' => 'test_client_id_456',
                'has_client_secret' => true,
                'has_webhook_secret' => true,
            ],
        ]);

        // In Demo mode, test connection should return simulated success
        config(['app.demo' => true]);
        $testResponse = $this->actingAs($admin)->postJson('/spa/settings/zoom/test');
        $testResponse->assertStatus(200);
        $testResponse->assertJson([
            'success' => true,
            'status' => 'connected',
            'message' => 'Demo Connection verified successfully (Simulated Zoom Provider).',
        ]);
    }

    public function test_spa_sync_zoom_users_and_assign_to_resource_pools(): void
    {
        $admin = User::create([
            'name' => 'Pool Admin',
            'email' => 'admin.pool@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Administrator');

        config(['app.demo' => true]);

        // Configure zoom connection first
        $this->actingAs($admin)->postJson('/spa/settings/zoom', [
            'name' => 'Demo University Zoom',
            'account_id' => 'demo_account_123',
            'client_id' => 'demo_client_123',
            'client_secret' => 'demo_secret_123',
            'enabled' => true,
        ]);

        // 1. Sync users from Zoom
        $syncResponse = $this->actingAs($admin)->postJson('/spa/resources/sync-from-zoom');
        $syncResponse->assertStatus(200);
        $syncResponse->assertJson([
            'success' => true,
            'count' => 5,
        ]);

        // 2. Fetch resources
        $resourcesResponse = $this->actingAs($admin)->getJson('/spa/resources');
        $resourcesResponse->assertStatus(200);
        $resources = $resourcesResponse->json();
        $this->assertCount(5, $resources);

        $firstResourceId = $resources[0]['id'];
        $secondResourceId = $resources[1]['id'];

        // 3. Create a resource pool with assigned resources
        $poolResponse = $this->actingAs($admin)->postJson('/spa/pools', [
            'name' => 'Classroom Standard Pool',
            'code' => 'POOL_CLASS_STD',
            'description' => 'Dedicated pool for undergraduate daily classes',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
            'resource_ids' => [$firstResourceId, $secondResourceId],
        ]);
        $poolResponse->assertStatus(200);
        $poolId = $poolResponse->json('pool.id');

        // 4. Verify pool has 2 resources
        $poolsResponse = $this->actingAs($admin)->getJson('/spa/pools');
        $poolsResponse->assertStatus(200);
        $pools = $poolsResponse->json();
        $this->assertNotEmpty($pools);
        $createdPool = collect($pools)->firstWhere('id', $poolId);
        $this->assertEquals(2, $createdPool['resources_count']);

        // 5. Assign resource to pools via direct endpoint
        $assignResponse = $this->actingAs($admin)->postJson("/spa/resources/{$firstResourceId}/pools", [
            'pool_ids' => [$poolId],
        ]);
        $assignResponse->assertStatus(200);
        $this->assertTrue($assignResponse->json('success'));
    }

    public function test_spa_cloud_recordings_endpoints(): void
    {
        $admin = User::create([
            'name' => 'Recording Admin',
            'email' => 'recadmin@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Administrator');

        // 1. Register manual recording
        $storeResponse = $this->actingAs($admin)->postJson('/spa/recordings', [
            'topic' => 'AI Ethics Seminar',
            'zoom_meeting_id' => '98765432101',
            'play_url' => 'https://zoom.us/rec/play/sample123',
            'duration_minutes' => 90,
            'file_size_bytes' => 209715200,
        ]);
        $storeResponse->assertStatus(201);
        $storeResponse->assertJson(['success' => true]);

        // 2. Fetch recordings list
        $listResponse = $this->actingAs($admin)->getJson('/spa/recordings');
        $listResponse->assertStatus(200);
        $recordings = $listResponse->json('data');
        $this->assertNotEmpty($recordings);
        $this->assertEquals('AI Ethics Seminar', $recordings[0]['topic']);

        // 3. Trigger sync recordings
        $syncResponse = $this->actingAs($admin)->postJson('/spa/recordings/sync');
        $syncResponse->assertStatus(200);
        $this->assertTrue($syncResponse->json('success'));
    }

    public function test_spa_attendance_endpoints(): void
    {
        $admin = User::create([
            'name' => 'Attendance Admin',
            'email' => 'attendadmin@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Administrator');

        $meeting = Meeting::create([
            'title' => 'Calculus I Lecture',
            'description' => 'Derivatives and integrals',
            'owner_user_id' => $admin->id,
            'requester_user_id' => $admin->id,
            'zoom_meeting_id' => '95123456789',
            'status' => 'ended',
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
        ]);

        MeetingAttendance::create([
            'meeting_id' => $meeting->id,
            'zoom_meeting_id' => $meeting->zoom_meeting_id,
            'participant_name' => 'Alice Student',
            'participant_email' => 'alice@univ.edu',
            'join_time' => now()->subHours(2),
            'leave_time' => now()->subHour(),
            'duration_minutes' => 60,
            'attendance_percentage' => 100,
            'status' => 'present',
            'device' => 'MacBook Pro',
        ]);

        // 1. Fetch attendance overview
        $listResponse = $this->actingAs($admin)->getJson('/spa/attendance');
        $listResponse->assertStatus(200);
        $meetingsData = $listResponse->json('data');
        $this->assertNotEmpty($meetingsData);
        $this->assertEquals('Calculus I Lecture', $meetingsData[0]['title']);
        $this->assertEquals(1, $meetingsData[0]['attendances_count']);

        // 2. Fetch attendance details for meeting
        $detailsResponse = $this->actingAs($admin)->getJson("/spa/attendance/{$meeting->public_id}");
        $detailsResponse->assertStatus(200);
        $detailsResponse->assertJson([
            'stats' => [
                'total_participants' => 1,
                'avg_attendance_percentage' => 100,
                'total_present' => 1,
                'total_partial' => 0,
            ],
        ]);
        $this->assertCount(1, $detailsResponse->json('attendances'));
        $this->assertEquals('Alice Student', $detailsResponse->json('attendances.0.participant_name'));

        // 3. Export CSV
        $exportResponse = $this->actingAs($admin)->get("/spa/attendance/{$meeting->public_id}/export");
        $exportResponse->assertStatus(200);
        $this->assertTrue(str_contains($exportResponse->headers->get('content-disposition') ?? '', 'attachment'));

        // 4. Sync attendance
        $syncResponse = $this->actingAs($admin)->postJson('/spa/attendance/sync', [
            'meeting_id' => $meeting->id,
        ]);
        $syncResponse->assertStatus(200);
    }

    public function test_spa_meetings_endpoint_supports_sorting_and_pagination(): void
    {
        $admin = User::create([
            'name' => 'Meetings Admin',
            'email' => 'admin.meetings@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Administrator');

        // Create ZoomConnection, ZoomUser and ZoomResource
        $connection = ZoomConnection::create([
            'name' => 'Main Account',
            'account_id' => 'zoom-acc-test',
            'client_id' => 'zoom-client-test',
            'client_secret' => 'zoom-secret-test',
            'enabled' => true,
            'status' => 'active',
        ]);
        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zm-user-test-1',
            'email' => 'host1@univ.edu',
            'first_name' => 'Host',
            'last_name' => 'One',
            'user_type' => 2,
            'status' => 'active',
            'synced_at' => now(),
        ]);
        $resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'managed' => true,
            'status' => 'active',
        ]);

        // Create 15 meetings with varying titles, assigning resource to first meeting
        for ($i = 1; $i <= 15; $i++) {
            Meeting::create([
                'title' => sprintf('Meeting %02d', $i),
                'owner_user_id' => $admin->id,
                'requester_user_id' => $admin->id,
                'zoom_resource_id' => $i === 1 ? $resource->id : null,
                'starts_at' => now()->addDays($i)->setTime(10, 0),
                'ends_at' => now()->addDays($i)->setTime(11, 0),
                'status' => 'scheduled',
                'participant_count' => $i * 5,
            ]);
        }

        // Test pagination: per_page = 10
        $response10 = $this->actingAs($admin)->getJson('/spa/meetings?per_page=10&page=1');
        $response10->assertStatus(200);
        $response10->assertJsonPath('per_page', 10);
        $response10->assertJsonPath('total', 15);
        $response10->assertJsonPath('last_page', 2);
        $this->assertCount(10, $response10->json('data'));

        // Test page 2
        $responsePage2 = $this->actingAs($admin)->getJson('/spa/meetings?per_page=10&page=2');
        $responsePage2->assertStatus(200);
        $this->assertCount(5, $responsePage2->json('data'));

        // Test sorting: sort_by=title&sort_dir=desc
        $responseDesc = $this->actingAs($admin)->getJson('/spa/meetings?per_page=10&sort_by=title&sort_dir=desc');
        $responseDesc->assertStatus(200);
        $this->assertEquals('Meeting 15', $responseDesc->json('data.0.title'));

        // Test sorting: sort_by=title&sort_dir=asc
        $responseAsc = $this->actingAs($admin)->getJson('/spa/meetings?per_page=10&sort_by=title&sort_dir=asc');
        $responseAsc->assertStatus(200);
        $this->assertEquals('Meeting 01', $responseAsc->json('data.0.title'));
        $this->assertEquals('Host One', $responseAsc->json('data.0.zoom_resource.name'));
    }

    public function test_spa_meetings_export_excel_and_pdf(): void
    {
        $admin = User::create([
            'name' => 'Export Admin',
            'email' => 'admin.export@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $admin->assignRole('Super Administrator');

        Meeting::create([
            'title' => 'Exportable Strategy Session',
            'owner_user_id' => $admin->id,
            'requester_user_id' => $admin->id,
            'starts_at' => now()->addDay()->setTime(14, 0),
            'ends_at' => now()->addDay()->setTime(15, 0),
            'status' => 'scheduled',
            'participant_count' => 20,
        ]);

        // 1. Export Excel / Spreadsheet
        $excelResponse = $this->actingAs($admin)->get('/spa/meetings/export?format=xlsx');
        $excelResponse->assertStatus(200);
        $this->assertStringContainsString('spreadsheetml.sheet', $excelResponse->headers->get('content-type') ?? '');
        $this->assertStringContainsString('Exportable Strategy Session', $excelResponse->streamedContent());

        // 2. Export PDF printable document
        $pdfResponse = $this->actingAs($admin)->get('/spa/meetings/export?format=pdf');
        $pdfResponse->assertStatus(200);
        $pdfResponse->assertSee('Scheduled Meetings Report');
        $pdfResponse->assertSee('Exportable Strategy Session');
        $pdfResponse->assertSee('window.print()');
    }
}
