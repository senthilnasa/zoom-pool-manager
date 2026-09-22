<?php

namespace App\Domain\Api\Services;

use App\Domain\Api\Models\WebhookDelivery;
use App\Domain\Api\Models\WebhookSubscription;
use App\Jobs\DispatchOutgoingWebhookJob;

class OutboundWebhookService
{
    /**
     * Dispatch an event to all matching active webhook subscriptions.
     *
     * @param  array<string, mixed>  $payload
     */
    public function dispatch(string $eventType, array $payload): void
    {
        $subscriptions = WebhookSubscription::where('is_active', true)->get();

        foreach ($subscriptions as $subscription) {
            if (! $subscription->subscribesTo($eventType)) {
                continue;
            }

            $this->queueDelivery($subscription, $eventType, $payload);
        }
    }

    /**
     * Queue a delivery for a specific subscription.
     *
     * @param  array<string, mixed>  $payload
     */
    public function queueDelivery(WebhookSubscription $subscription, string $eventType, array $payload): WebhookDelivery
    {
        $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $rawPayload, (string) $subscription->secret);

        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::create([
            'subscription_id' => $subscription->id,
            'event_type' => $eventType,
            'payload' => $payload,
            'signature' => $signature,
            'status' => 'pending',
            'attempt' => 0,
        ]);

        DispatchOutgoingWebhookJob::dispatch($delivery);

        return $delivery;
    }

    /**
     * Send an immediate test ping event for a subscription.
     */
    public function testPing(WebhookSubscription $subscription): WebhookDelivery
    {
        $testPayload = [
            'event' => 'endpoint.test',
            'timestamp' => now()->toIso8601String(),
            'subscription_id' => $subscription->public_id,
            'message' => 'Zoom Pool Manager test webhook event',
        ];

        return $this->queueDelivery($subscription, 'endpoint.test', $testPayload);
    }

    /**
     * Manually retry a failed delivery.
     */
    public function retryDelivery(WebhookDelivery $delivery): void
    {
        $delivery->update([
            'status' => 'pending',
            'attempt' => 0,
        ]);

        DispatchOutgoingWebhookJob::dispatch($delivery);
    }
}
