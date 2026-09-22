<?php

namespace Tests\Feature;

use App\Domain\Api\Models\WebhookDelivery;
use App\Domain\Api\Models\WebhookSubscription;
use App\Domain\Api\Services\OutboundWebhookService;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Jobs\DispatchOutgoingWebhookJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OutboundWebhooksTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected OutboundWebhookService $webhookService;

    protected function setUp(): void
    {
        parent::setUp();

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $role = Role::create(['name' => 'Super Administrator', 'guard_name' => 'web']);
        $department = Department::create(['name' => 'Network Operations', 'code' => 'NOC']);

        $this->adminUser = User::create([
            'name' => 'Webhook Admin',
            'email' => 'webhookadmin@univ.edu',
            'password' => bcrypt('password123'),
            'department_id' => $department->id,
            'is_active' => true,
        ]);
        $this->adminUser->assignRole('Super Administrator');

        $this->webhookService = app(OutboundWebhookService::class);
    }

    public function test_dispatch_creates_delivery_records_and_queues_jobs(): void
    {
        Queue::fake();

        $subA = WebhookSubscription::create([
            'name' => 'Meeting Events Receiver',
            'url' => 'https://external-service.org/webhooks/meetings',
            'secret' => 'super_secret_key_123456',
            'events' => ['meeting.created', 'meeting.cancelled'],
            'is_active' => true,
        ]);

        $subB = WebhookSubscription::create([
            'name' => 'Recordings Only Receiver',
            'url' => 'https://external-service.org/webhooks/recordings',
            'secret' => 'recording_secret_654321',
            'events' => ['recording.completed'],
            'is_active' => true,
        ]);

        $this->webhookService->dispatch('meeting.created', [
            'meeting_id' => 'mtg_test_123',
            'topic' => 'Chemistry Lab',
        ]);

        $this->assertDatabaseHas('webhook_deliveries', [
            'subscription_id' => $subA->id,
            'event_type' => 'meeting.created',
            'status' => 'pending',
        ]);

        $this->assertDatabaseMissing('webhook_deliveries', [
            'subscription_id' => $subB->id,
        ]);

        Queue::assertPushed(DispatchOutgoingWebhookJob::class, 1);
    }

    public function test_dispatch_job_executes_signed_http_post(): void
    {
        Http::fake([
            'https://recipient.example.com/*' => Http::response(['ack' => true], 200),
        ]);

        $subscription = WebhookSubscription::create([
            'name' => 'Recipient Service',
            'url' => 'https://recipient.example.com/webhook',
            'secret' => 'hmac_verification_secret_key',
            'events' => ['*'],
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'meeting.started',
            'payload' => ['meeting_id' => '123456'],
            'signature' => 'sig_test',
            'status' => 'pending',
        ]);

        $job = new DispatchOutgoingWebhookJob($delivery);
        $job->handle();

        Http::assertSent(function ($request) use ($subscription) {
            return $request->url() === $subscription->url
                && $request->hasHeader('X-ZPM-Signature')
                && str_starts_with($request->header('X-ZPM-Signature')[0], 'sha256=')
                && $request->hasHeader('X-ZPM-Event')
                && $request->header('X-ZPM-Event')[0] === 'meeting.started';
        });

        $delivery->refresh();
        $this->assertEquals('delivered', $delivery->status);
        $this->assertEquals(200, $delivery->response_status);
        $this->assertNotNull($delivery->delivered_at);

        $subscription->refresh();
        $this->assertNotNull($subscription->last_delivered_at);
        $this->assertEquals(0, $subscription->failure_count);
    }

    public function test_outbound_webhooks_admin_interface_crud_and_ping(): void
    {
        Queue::fake();

        // 1. Create subscription via web interface
        $response = $this->actingAs($this->adminUser)
            ->post(route('admin.outbound-webhooks.store'), [
                'name' => 'Internal Audit Listener',
                'url' => 'https://audit.univ.edu/zpm',
                'secret' => 'audit_secret_token_12345',
                'events' => ['meeting.created', 'meeting.cancelled'],
            ]);

        $response->assertRedirect(route('admin.outbound-webhooks.index'));

        $sub = WebhookSubscription::where('name', 'Internal Audit Listener')->first();
        $this->assertNotNull($sub);
        $this->assertTrue($sub->is_active);

        // 2. Trigger test ping
        $pingRes = $this->actingAs($this->adminUser)
            ->post(route('admin.outbound-webhooks.test', $sub->public_id));

        $pingRes->assertRedirect(route('admin.outbound-webhooks.index'));
        Queue::assertPushed(DispatchOutgoingWebhookJob::class);

        // 3. Toggle subscription active state
        $toggleRes = $this->actingAs($this->adminUser)
            ->post(route('admin.outbound-webhooks.toggle', $sub->public_id));

        $toggleRes->assertRedirect(route('admin.outbound-webhooks.index'));
        $sub->refresh();
        $this->assertFalse($sub->is_active);
    }
}
