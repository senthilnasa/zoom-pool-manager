<?php

namespace Tests\Feature;

use App\Domain\Auth\Models\DirectorySyncConfig;
use App\Domain\Auth\Services\DirectorySyncService;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduledJobsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Super Administrator', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Standard User', 'guard_name' => 'web']);

        $this->admin = User::create([
            'name' => 'IT Admin',
            'email' => 'admin.jobs@univ.edu',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('Super Administrator');
    }

    public function test_directory_sync_self_heals_missing_target_role(): void
    {
        // Delete a custom role if it exists
        Role::where('name', 'Custom New Department Staff')->delete();

        $config = DirectorySyncConfig::create([
            'name' => 'LDAP Active Directory Test',
            'provider_type' => 'ldap_active_directory',
            'is_active' => true,
            'default_role' => 'Custom New Department Staff',
            'ldap_host' => '127.0.0.1',
        ]);

        $service = app(DirectorySyncService::class);

        // Before our fix, calling sync would crash if the role didn't exist
        // Now it self-heals by calling Role::firstOrCreate(['name' => $targetRole, 'guard_name' => 'web'])
        // Even if LDAP connection fails, the role lookup/assignment logic does not crash with RoleDoesNotExist
        $result = $service->sync($config);

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);

        // Ensure the custom role was safely auto-created in the database
        $this->assertTrue(Role::where('name', 'Custom New Department Staff')->where('guard_name', 'web')->exists());
    }

    public function test_get_scheduled_jobs_api_returns_all_catalogue_items(): void
    {
        $res = $this->actingAs($this->admin)->getJson('/spa/settings/jobs');

        $res->assertStatus(200);
        $res->assertJsonStructure([
            'jobs' => [
                '*' => [
                    'key',
                    'name',
                    'command',
                    'description',
                    'category',
                    'enabled',
                    'cadence',
                    'default_cadence',
                    'last_run_at',
                    'last_status',
                    'last_output',
                ],
            ],
            'cadence_options',
            'daemon_status' => [
                'server_time',
                'active_jobs_count',
                'total_jobs_count',
            ],
        ]);

        $jobs = $res->json('jobs');
        $this->assertCount(10, $jobs);

        $keys = collect($jobs)->pluck('key')->all();
        $this->assertContains('meetings_reconcile', $keys);
        $this->assertContains('directory_sync', $keys);
        $this->assertContains('recordings_sync', $keys);
        $this->assertContains('attendance_sync', $keys);
    }

    public function test_update_scheduled_job_cadence_and_enabled_toggle(): void
    {
        // 1. Change cadence of meetings_reconcile to 1m
        $updateRes = $this->actingAs($this->admin)->putJson('/spa/settings/jobs/meetings_reconcile', [
            'cadence' => '1m',
            'enabled' => false,
        ]);

        $updateRes->assertStatus(200);
        $updateRes->assertJson([
            'success' => true,
            'job' => [
                'key' => 'meetings_reconcile',
                'cadence' => '1m',
                'enabled' => false,
            ],
        ]);

        // 2. Fetch jobs list and verify changes persisted
        $listRes = $this->actingAs($this->admin)->getJson('/spa/settings/jobs');
        $job = collect($listRes->json('jobs'))->firstWhere('key', 'meetings_reconcile');

        $this->assertEquals('1m', $job['cadence']);
        $this->assertFalse($job['enabled']);
    }

    public function test_run_scheduled_job_on_demand_returns_captured_output(): void
    {
        // Run health check on demand
        $runRes = $this->actingAs($this->admin)->postJson('/spa/settings/jobs/health_check/run');

        $runRes->assertStatus(200);
        $runRes->assertJsonStructure([
            'success',
            'command',
            'output',
            'exit_code',
            'executed_at',
        ]);

        $this->assertEquals('zpm:health:check', $runRes->json('command'));
        $this->assertNotEmpty($runRes->json('output'));

        // Verify the job run telemetry is updated in list
        $listRes = $this->actingAs($this->admin)->getJson('/spa/settings/jobs');
        $job = collect($listRes->json('jobs'))->firstWhere('key', 'health_check');

        $this->assertNotNull($job['last_run_at']);
        $this->assertNotEmpty($job['last_output']);
    }

    public function test_sync_directory_command_force_flag_and_cadence(): void
    {
        $config = DirectorySyncConfig::create([
            'name' => 'Scheduled Test Directory',
            'provider_type' => 'google_workspace',
            'is_active' => true,
            'sync_interval_minutes' => 60,
            'last_synced_at' => now(), // synced just now
        ]);

        // 1. Run without force: should skip because interval (60m) has not elapsed
        $this->artisan('zpm:directory:sync')
            ->expectsOutputToContain('Skipping [Scheduled Test Directory]')
            ->assertSuccessful();

        // 2. Run with --force: should attempt sync immediately
        $this->artisan('zpm:directory:sync --force')
            ->expectsOutputToContain('Executing sync for connector: [Scheduled Test Directory]')
            ->assertSuccessful();
    }
}
