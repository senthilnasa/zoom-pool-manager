<?php

namespace Tests\Feature;

use App\Domain\Operations\Models\Alert;
use App\Domain\Operations\Services\OperationsAlertService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OperationsAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::create(['name' => 'IT Administrator', 'guard_name' => 'web']);

        $department = Department::create(['name' => 'IT Operations', 'code' => 'IT']);

        $this->adminUser = User::create([
            'name' => 'Super Ops',
            'email' => 'superops@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');
    }

    public function test_alert_service_triggers_and_deduplicates_repeated_anomalies(): void
    {
        $service = app(OperationsAlertService::class);

        // First trigger
        $alert1 = $service->triggerAlert(
            'zoom.connection_failed',
            'critical',
            'Zoom Connection Down',
            'OAuth token refresh rejected with HTTP 401',
            ['connection_id' => 1]
        );

        $this->assertEquals(1, $alert1->count);
        $this->assertEquals(1, Alert::where('key', 'zoom.connection_failed')->count());

        // Second trigger within 5 minutes (deduplication)
        $alert2 = $service->triggerAlert(
            'zoom.connection_failed',
            'critical',
            'Zoom Connection Down',
            'OAuth token refresh rejected with HTTP 401',
            ['connection_id' => 1]
        );

        $this->assertEquals($alert1->id, $alert2->id);
        $this->assertEquals(2, $alert2->count);
        $this->assertEquals(1, Alert::where('key', 'zoom.connection_failed')->count());
    }

    public function test_alert_service_resolves_alert_and_records_audit(): void
    {
        $service = app(OperationsAlertService::class);

        $service->triggerAlert(
            'storage.low_disk',
            'warning',
            'Low Disk Space',
            'Free disk space is below 5 GB'
        );

        $resolvedAlert = $service->resolveAlert('storage.low_disk', 'Disk space cleared');

        $this->assertNotNull($resolvedAlert);
        $this->assertNotNull($resolvedAlert->resolved_at);
        $this->assertTrue($resolvedAlert->isResolved());
    }

    public function test_check_anomalies_detects_inactive_scheduler(): void
    {
        $service = app(OperationsAlertService::class);

        // Set last heartbeat to 45 minutes ago
        Setting::set('scheduler.last_heartbeat_at', Carbon::now()->subMinutes(45)->toIso8601String());

        $alerts = $service->checkAnomalies();

        $this->assertNotEmpty($alerts);
        $this->assertDatabaseHas('alerts', [
            'key' => 'scheduler.inactivity',
            'severity' => 'critical',
        ]);
    }

    public function test_alerts_web_interface_and_manual_resolve(): void
    {
        $alert = Alert::create([
            'key' => 'manual.test_alert',
            'severity' => 'warning',
            'title' => 'Test Warning',
            'message' => 'Manual test alert',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'count' => 1,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.alerts'));

        $response->assertStatus(200);
        $response->assertSee('Test Warning');

        // Resolve via web
        $resolveResponse = $this->actingAs($this->adminUser)
            ->post(route('admin.alerts.resolve', $alert->public_id));

        $resolveResponse->assertRedirect();
        $alert->refresh();
        $this->assertTrue($alert->isResolved());
    }
}
