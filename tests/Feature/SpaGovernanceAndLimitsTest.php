<?php

namespace Tests\Feature;

use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\Quota;
use App\Domain\Workflow\Models\WorkflowRule;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TemplatesAndSecurityProfilesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SpaGovernanceAndLimitsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $faculty;
    protected Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(TemplatesAndSecurityProfilesSeeder::class);

        $this->department = Department::create([
            'name' => 'Data Science',
            'code' => 'DS',
        ]);

        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@zoompool.test',
            'password' => bcrypt('secret'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->admin->assignRole('super_admin');

        $this->faculty = User::create([
            'name' => 'Prof Scholar',
            'email' => 'scholar@zoompool.test',
            'password' => bcrypt('secret'),
            'department_id' => $this->department->id,
            'is_active' => true,
        ]);
        $this->faculty->assignRole('faculty');
    }

    public function test_spa_workflows_endpoints(): void
    {
        // 1. Create rule via SPA
        $storeRes = $this->actingAs($this->admin)->postJson('/spa/workflows', [
            'name' => 'Block Night Webinars',
            'priority' => 15,
            'conditions' => [
                'meeting_type' => 'webinar',
                'duration_min' => 120,
            ],
            'actions' => [
                'reject' => 'Webinars cannot exceed 2 hours without VP approval.',
            ],
            'is_enabled' => true,
        ]);

        $storeRes->assertOk()->assertJson(['success' => true]);
        $rule = WorkflowRule::where('name', 'Block Night Webinars')->firstOrFail();

        // 2. Fetch index via SPA
        $indexRes = $this->actingAs($this->admin)->getJson('/spa/workflows');
        $indexRes->assertOk();
        $this->assertNotEmpty($indexRes->json('rules'));
        $this->assertNotEmpty($indexRes->json('departments'));

        // 3. Toggle via SPA
        $toggleRes = $this->actingAs($this->admin)->postJson("/spa/workflows/{$rule->public_id}/toggle");
        $toggleRes->assertOk()->assertJson(['success' => true]);
        $this->assertFalse($rule->fresh()->is_enabled);

        // 4. Simulate via SPA (accepts duration_minutes without requiring starts_at/ends_at)
        $simRes = $this->actingAs($this->admin)->postJson('/spa/workflows/simulate', [
            'meeting_type' => 'webinar',
            'duration_minutes' => 150,
            'participant_count' => 80,
        ]);
        $simRes->assertOk();
        $this->assertArrayHasKey('matched_count', $simRes->json());
        $this->assertArrayHasKey('is_auto_approved', $simRes->json());

        // 5. Delete via SPA
        $delRes = $this->actingAs($this->admin)->deleteJson("/spa/workflows/{$rule->public_id}");
        $delRes->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('workflow_rules', ['id' => $rule->id]);
    }

    public function test_spa_quotas_endpoints(): void
    {
        // 1. Store quota via SPA
        $storeRes = $this->actingAs($this->admin)->postJson('/spa/quotas', [
            'scope_type' => 'department',
            'scope_id' => $this->department->id,
            'max_meetings_per_month' => 25,
            'max_hours_per_month' => 50,
            'is_active' => true,
        ]);
        $storeRes->assertOk()->assertJson(['success' => true]);

        $quota = Quota::where('scope_type', 'department')
            ->where('scope_id', $this->department->id)
            ->firstOrFail();

        // 2. Fetch index via SPA
        $indexRes = $this->actingAs($this->admin)->getJson('/spa/quotas');
        $indexRes->assertOk();
        $this->assertNotEmpty($indexRes->json('quotas.data'));
        $firstItem = $indexRes->json('quotas.data.0');
        $this->assertArrayHasKey('meetings_pct', $firstItem);
        $this->assertArrayHasKey('hours_pct', $firstItem);
        $this->assertArrayHasKey('target_name', $firstItem);

        // 3. Toggle quota via SPA
        $toggleRes = $this->actingAs($this->admin)->postJson("/spa/quotas/{$quota->public_id}/toggle");
        $toggleRes->assertOk()->assertJson(['success' => true, 'is_active' => false]);
        $this->assertFalse($quota->fresh()->is_active);

        // 4. Delete quota via SPA
        $delRes = $this->actingAs($this->admin)->deleteJson("/spa/quotas/{$quota->public_id}");
        $delRes->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseMissing('quotas', ['id' => $quota->id]);
    }

    public function test_spa_delegations_endpoints(): void
    {
        // 1. Store delegation via SPA
        $storeRes = $this->actingAs($this->admin)->postJson('/spa/delegations', [
            'delegate_user_id' => $this->faculty->id,
            'starts_at' => Carbon::now()->toDateString(),
            'ends_at' => Carbon::now()->addDays(7)->toDateString(),
        ]);
        $storeRes->assertOk()->assertJson(['success' => true]);

        $delegation = ApprovalDelegation::where('user_id', $this->admin->id)
            ->where('delegate_user_id', $this->faculty->id)
            ->firstOrFail();

        // 2. Index via SPA
        $indexRes = $this->actingAs($this->admin)->getJson('/spa/delegations');
        $indexRes->assertOk();
        $this->assertNotEmpty($indexRes->json('my_delegations'));
        $this->assertNotEmpty($indexRes->json('eligible_delegates'));

        // 3. Delete / revoke delegation via SPA
        $delRes = $this->actingAs($this->admin)->deleteJson("/spa/delegations/{$delegation->public_id}");
        $delRes->assertOk()->assertJson(['success' => true]);
        $this->assertFalse($delegation->fresh()->is_active);
    }
}
