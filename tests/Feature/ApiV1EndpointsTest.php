<?php

namespace Tests\Feature;

use App\Domain\Api\Services\ApiKeyService;
use App\Domain\Scheduling\Models\BookingPolicy;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ApiV1EndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected ResourcePool $pool;

    protected ZoomResource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'meetings.create', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $department = Department::create(['name' => 'Computer Science', 'code' => 'CS']);

        $this->user = User::create([
            'name' => 'API Dev User',
            'email' => 'apidev@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->user->assignRole('Super Administrator');

        BookingPolicy::create([
            'name' => 'Default Organization Policy',
            'scope_type' => 'organization',
            'scope_id' => null,
            'min_notice_hours' => 0,
            'max_advance_days' => 60,
            'buffer_minutes' => 10,
            'is_active' => true,
        ]);

        $connection = ZoomConnection::create([
            'name' => 'API Account',
            'account_id' => 'acc_api',
            'client_id' => 'cli_api',
            'client_secret' => 'sec_api',
            'status' => 'active',
            'enabled' => true,
        ]);

        $zoomUser = ZoomUser::create([
            'connection_id' => $connection->id,
            'zoom_user_id' => 'zu_api_1',
            'email' => 'host_api@univ.edu',
            'user_type' => 2,
            'synced_at' => now(),
        ]);

        $this->resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'managed' => true,
            'participant_capacity' => 300,
        ]);

        $this->pool = ResourcePool::create([
            'name' => 'General Classrooms Pool',
            'code' => 'gen_classrooms',
            'description' => 'Available for academic sessions',
            'pool_strategy' => 'least_hours_today',
            'is_active' => true,
        ]);

        $this->pool->resources()->attach($this->resource->id, ['priority' => 1]);

        $apiKeyService = app(ApiKeyService::class);
        $result = $apiKeyService->createKey(
            user: $this->user,
            name: 'Master API Key',
            scopes: ['*'],
            rateLimit: 100
        );

        $this->token = $result['plainTextToken'];
    }

    public function test_availability_check_returns_available_status(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/availability?'.http_build_query([
            'starts_at' => now()->addHours(5)->toIso8601String(),
            'ends_at' => now()->addHours(6)->toIso8601String(),
            'participant_count' => 50,
            'pool_id' => $this->pool->id,
        ]));

        $response->assertStatus(200);
        $response->assertJson([
            'available' => true,
            'available_resource_count' => 1,
        ]);
    }

    public function test_booking_meeting_via_api_and_idempotency_key_caching(): void
    {
        $payload = [
            'title' => 'CS101 Intro to Algorithms',
            'description' => 'Automated lecture booking via API',
            'starts_at' => now()->addHours(4)->toIso8601String(),
            'ends_at' => now()->addHours(5)->toIso8601String(),
            'participant_count' => 45,
            'preferred_pool_id' => $this->pool->id,
        ];

        $idempotencyKey = 'req_idemp_123456';

        // First Request
        $response1 = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/meetings', $payload);

        $response1->assertStatus(201);
        $response1->assertJsonStructure([
            'message',
            'meeting' => ['public_id', 'title', 'status', 'starts_at', 'ends_at'],
        ]);

        $meetingPublicId = $response1->json('meeting.public_id');
        $this->assertNotNull($meetingPublicId);

        // Second duplicate Request with same Idempotency-Key
        $response2 = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/meetings', $payload);

        $response2->assertStatus(201);
        $response2->assertHeader('X-Cache', 'HIT-IDEMPOTENT');
        $this->assertEquals($meetingPublicId, $response2->json('meeting.public_id'));

        // Third Request with same Idempotency-Key but modified payload -> 422 Conflict
        $differentPayload = $payload;
        $differentPayload['title'] = 'Tampered Title';

        $response3 = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/meetings', $differentPayload);

        $response3->assertStatus(422);
        $response3->assertJson([
            'error' => 'Unprocessable Entity',
        ]);
    }

    public function test_meeting_index_show_and_cancel_endpoints(): void
    {
        // 1. Create meeting
        $createRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson('/api/v1/meetings', [
            'title' => 'Department Seminar',
            'starts_at' => now()->addHours(6)->toIso8601String(),
            'ends_at' => now()->addHours(7)->toIso8601String(),
            'participant_count' => 20,
            'preferred_pool_id' => $this->pool->id,
        ]);

        $createRes->assertStatus(201);
        $publicId = $createRes->json('meeting.public_id');

        // 2. Index meetings
        $listRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/meetings');

        $listRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        // 3. Show meeting
        $showRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson("/api/v1/meetings/{$publicId}");

        $showRes->assertStatus(200);
        $showRes->assertJson([
            'meeting' => [
                'public_id' => $publicId,
                'title' => 'Department Seminar',
            ],
        ]);

        // 4. Cancel meeting
        $cancelRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->postJson("/api/v1/meetings/{$publicId}/cancel", [
            'reason' => 'Guest speaker rescheduled',
        ]);

        $cancelRes->assertStatus(200);
        $cancelRes->assertJson([
            'meeting' => [
                'public_id' => $publicId,
                'status' => 'cancelled',
            ],
        ]);
    }

    public function test_pools_endpoint_returns_active_pools(): void
    {
        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->token}",
        ])->getJson('/api/v1/pools');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'pools' => [
                '*' => ['public_id', 'name', 'strategy', 'resources_count', 'resources'],
            ],
        ]);
    }
}
