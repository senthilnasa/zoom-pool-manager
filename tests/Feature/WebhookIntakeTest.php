<?php

namespace Tests\Feature;

use App\Domain\HostControl\Contracts\MeetingHostProviderInterface;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Webhooks\Jobs\ProcessZoomWebhookJob;
use App\Domain\Webhooks\Models\ZoomWebhookEvent;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Models\ZoomUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WebhookIntakeTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected ZoomConnection $connection;

    protected string $webhookSecret = 'secret_token_12345';

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Seed roles & permissions
        $role = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'settings.view', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);

        $department = Department::create([
            'name' => 'Engineering',
            'code' => 'ENG',
        ]);

        $this->adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');

        $this->connection = ZoomConnection::create([
            'name' => 'Main University Account',
            'account_id' => 'acc_123',
            'client_id' => 'cli_123',
            'client_secret' => 'sec_123',
            'webhook_secret_token' => $this->webhookSecret,
            'status' => 'active',
            'enabled' => true,
        ]);
    }

    public function test_zoom_url_validation_handshake_returns_encrypted_token(): void
    {
        $plainToken = 'test_plain_token_xyz';
        $expectedEncrypted = hash_hmac('sha256', $plainToken, $this->webhookSecret);

        $response = $this->postJson(route('webhooks.zoom', $this->connection->public_id), [
            'event' => 'endpoint.url_validation',
            'payload' => [
                'plainToken' => $plainToken,
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'plainToken' => $plainToken,
            'encryptedToken' => $expectedEncrypted,
        ]);
    }

    public function test_inbound_webhook_verifies_hmac_and_queues_job(): void
    {
        Queue::fake();

        $payload = [
            'event' => 'meeting.started',
            'event_ts' => time(),
            'payload' => [
                'object' => [
                    'id' => '9988776655',
                    'uuid' => 'zoom-uuid-1234',
                ],
            ],
        ];

        $rawBody = json_encode($payload);
        $timestamp = (string) time();
        $message = "v0:{$timestamp}:{$rawBody}";
        $signature = 'v0='.hash_hmac('sha256', $message, $this->webhookSecret);

        $response = $this->call(
            'POST',
            route('webhooks.zoom', $this->connection->public_id),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_ZM_SIGNATURE' => $signature,
                'HTTP_X_ZM_REQUEST_TIMESTAMP' => $timestamp,
            ],
            $rawBody
        );

        $response->assertStatus(200);
        $response->assertJson(['status' => 'queued']);

        $this->assertDatabaseHas('zoom_webhook_events', [
            'event_type' => 'meeting.started',
            'signature_valid' => 1,
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessZoomWebhookJob::class);
    }

    public function test_inbound_webhook_rejects_invalid_signature(): void
    {
        $payload = ['event' => 'meeting.started', 'event_ts' => time()];
        $rawBody = json_encode($payload);
        $timestamp = (string) time();

        $response = $this->call(
            'POST',
            route('webhooks.zoom', $this->connection->public_id),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_ZM_SIGNATURE' => 'v0=invalid_signature',
                'HTTP_X_ZM_REQUEST_TIMESTAMP' => $timestamp,
            ],
            $rawBody
        );

        $response->assertStatus(401);
    }

    public function test_inbound_webhook_rejects_expired_timestamp(): void
    {
        $payload = ['event' => 'meeting.started', 'event_ts' => time() - 400];
        $rawBody = json_encode($payload);
        $oldTimestamp = (string) (time() - 400); // More than 5 minutes old
        $message = "v0:{$oldTimestamp}:{$rawBody}";
        $signature = 'v0='.hash_hmac('sha256', $message, $this->webhookSecret);

        $response = $this->call(
            'POST',
            route('webhooks.zoom', $this->connection->public_id),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_ZM_SIGNATURE' => $signature,
                'HTTP_X_ZM_REQUEST_TIMESTAMP' => $oldTimestamp,
            ],
            $rawBody
        );

        $response->assertStatus(401);
    }

    public function test_replay_attack_prevention_acknowledges_without_duplicate(): void
    {
        Queue::fake();

        $payload = [
            'event' => 'meeting.started',
            'event_ts' => 1700000000,
            'payload' => [
                'object' => [
                    'id' => '1122334455',
                ],
            ],
        ];
        $rawBody = json_encode($payload);
        $timestamp = (string) time();
        $message = "v0:{$timestamp}:{$rawBody}";
        $signature = 'v0='.hash_hmac('sha256', $message, $this->webhookSecret);

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_ZM_SIGNATURE' => $signature,
            'HTTP_X_ZM_REQUEST_TIMESTAMP' => $timestamp,
        ];

        // First intake
        $r1 = $this->call('POST', route('webhooks.zoom', $this->connection->public_id), [], [], [], $headers, $rawBody);
        $r1->assertStatus(200);
        $r1->assertJson(['status' => 'queued']);

        // Second duplicate intake (replay)
        $r2 = $this->call('POST', route('webhooks.zoom', $this->connection->public_id), [], [], [], $headers, $rawBody);
        $r2->assertStatus(200);
        $r2->assertJson(['status' => 'already_received']);

        $this->assertEquals(1, ZoomWebhookEvent::where('event_type', 'meeting.started')->count());
    }

    public function test_process_webhook_job_updates_meeting_started_and_ended(): void
    {
        $mockHostProvider = \Mockery::mock(MeetingHostProviderInterface::class);
        $mockHostProvider->shouldReceive('rotateHostKey')->andReturn(true);
        $this->app->instance(MeetingHostProviderInterface::class, $mockHostProvider);

        $zoomUser = ZoomUser::create([
            'connection_id' => $this->connection->id,
            'zoom_user_id' => 'zu_01',
            'email' => 'host01@univ.edu',
            'user_type' => 2,
            'host_key' => '111111',
            'synced_at' => now(),
        ]);

        $resource = ZoomResource::create([
            'zoom_user_id' => $zoomUser->id,
            'managed' => true,
            'participant_capacity' => 100,
        ]);

        $meeting = Meeting::create([
            'title' => 'Biology Lecture',
            'meeting_type' => 'class',
            'starts_at' => Carbon::now()->subMinutes(10),
            'ends_at' => Carbon::now()->addHour(),
            'participant_count' => 40,
            'requester_user_id' => $this->adminUser->id,
            'owner_user_id' => $this->adminUser->id,
            'department_id' => $this->adminUser->department_id,
            'status' => 'scheduled',
            'zoom_resource_id' => $resource->id,
            'zoom_meeting_id' => '1234567890',
        ]);

        // 1. Process meeting.started
        $eventStarted = ZoomWebhookEvent::create([
            'event_id' => 'evt_start_01',
            'event_type' => 'meeting.started',
            'payload' => [
                'event' => 'meeting.started',
                'payload' => [
                    'object' => [
                        'id' => '1234567890',
                    ],
                ],
            ],
            'status' => 'pending',
        ]);

        $job1 = new ProcessZoomWebhookJob($eventStarted->id);
        $this->app->call([$job1, 'handle']);

        $meeting->refresh();
        $this->assertEquals('started', $meeting->status);
        $eventStarted->refresh();
        $this->assertEquals('processed', $eventStarted->status);

        // 2. Process meeting.ended
        $eventEnded = ZoomWebhookEvent::create([
            'event_id' => 'evt_end_01',
            'event_type' => 'meeting.ended',
            'payload' => [
                'event' => 'meeting.ended',
                'payload' => [
                    'object' => [
                        'id' => '1234567890',
                    ],
                ],
            ],
            'status' => 'pending',
        ]);

        $job2 = new ProcessZoomWebhookJob($eventEnded->id);
        $this->app->call([$job2, 'handle']);

        $meeting->refresh();
        $this->assertEquals('ended', $meeting->status);
        $eventEnded->refresh();
        $this->assertEquals('processed', $eventEnded->status);
    }
}
