<?php

namespace Tests\Feature;

use App\Domain\Api\Models\ApiKey;
use App\Domain\Api\Services\ApiKeyService;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiKeyAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected ApiKeyService $apiKeyService;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'api.manage', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $department = Department::create(['name' => 'IT Operations', 'code' => 'IT']);

        $this->user = User::create([
            'name' => 'API Admin',
            'email' => 'api-admin@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('Super Administrator');

        $this->apiKeyService = app(ApiKeyService::class);
    }

    public function test_api_endpoint_rejects_missing_authorization_header(): void
    {
        $response = $this->getJson('/api/v1/pools');

        $response->assertStatus(401);
        $response->assertJson([
            'error' => 'Unauthorized',
        ]);
    }

    public function test_api_endpoint_rejects_invalid_bearer_token(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_12345',
        ])->getJson('/api/v1/pools');

        $response->assertStatus(401);
    }

    public function test_api_endpoint_accepts_valid_bearer_token(): void
    {
        $result = $this->apiKeyService->createKey(
            user: $this->user,
            name: 'Integration Client',
            scopes: ['pools:read'],
            rateLimit: 60
        );

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$result['plainTextToken']}",
        ])->getJson('/api/v1/pools');

        $response->assertStatus(200);
        $response->assertJsonStructure(['pools']);
    }

    public function test_api_endpoint_rejects_key_lacking_required_scope(): void
    {
        $result = $this->apiKeyService->createKey(
            user: $this->user,
            name: 'Restricted Client',
            scopes: ['availability:read'], // missing pools:read
            rateLimit: 60
        );

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$result['plainTextToken']}",
        ])->getJson('/api/v1/pools');

        $response->assertStatus(403);
        $response->assertJson([
            'error' => 'Forbidden',
            'message' => 'API key lacks required scope: pools:read',
        ]);
    }

    public function test_api_endpoint_rejects_revoked_key(): void
    {
        $result = $this->apiKeyService->createKey(
            user: $this->user,
            name: 'Soon Revoked',
            scopes: ['pools:read'],
            rateLimit: 60
        );

        $this->apiKeyService->revokeKey($result['key']);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$result['plainTextToken']}",
        ])->getJson('/api/v1/pools');

        $response->assertStatus(401);
    }

    public function test_api_key_rate_limiting_enforces_limit(): void
    {
        $result = $this->apiKeyService->createKey(
            user: $this->user,
            name: 'Rate Limited Client',
            scopes: ['pools:read'],
            rateLimit: 2 // Max 2 per minute
        );

        RateLimiter::clear("api_key:{$result['key']->id}");

        $headers = ['Authorization' => "Bearer {$result['plainTextToken']}"];

        $res1 = $this->withHeaders($headers)->getJson('/api/v1/pools');
        $res1->assertStatus(200);

        $res2 = $this->withHeaders($headers)->getJson('/api/v1/pools');
        $res2->assertStatus(200);

        $res3 = $this->withHeaders($headers)->getJson('/api/v1/pools');
        $res3->assertStatus(429);
        $res3->assertJson(['error' => 'Too Many Requests']);
    }

    public function test_admin_can_generate_and_revoke_api_key_via_web_interface(): void
    {
        $response = $this->actingAs($this->user)
            ->post(route('admin.api-keys.store'), [
                'name' => 'LMS Service Account',
                'scopes' => ['meetings:read', 'meetings:write'],
                'rate_limit' => 120,
            ]);

        $response->assertRedirect(route('admin.api-keys.index'));
        $response->assertSessionHas('plainTextToken');

        $key = ApiKey::where('name', 'LMS Service Account')->first();
        $this->assertNotNull($key);
        $this->assertNull($key->revoked_at);

        // Revoke
        $revokeResponse = $this->actingAs($this->user)
            ->post(route('admin.api-keys.revoke', $key->public_id));

        $revokeResponse->assertRedirect(route('admin.api-keys.index'));
        $key->refresh();
        $this->assertNotNull($key->revoked_at);
    }
}
