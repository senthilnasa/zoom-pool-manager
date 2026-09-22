<?php

namespace App\Jobs;

use App\Domain\Api\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchOutgoingWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public WebhookDelivery $delivery
    ) {}

    public function handle(): void
    {
        $subscription = $this->delivery->subscription;

        if (! $subscription->is_active) {
            $this->delivery->update([
                'status' => 'cancelled',
                'response_body' => 'Subscription inactive or deleted',
            ]);

            return;
        }

        $timestamp = (string) now()->timestamp;
        $rawPayload = json_encode($this->delivery->payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', "v0:{$timestamp}:{$rawPayload}", (string) $subscription->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'ZoomPoolManager-Webhook/1.0',
                    'X-ZPM-Delivery' => $this->delivery->public_id,
                    'X-ZPM-Event' => $this->delivery->event_type,
                    'X-ZPM-Timestamp' => $timestamp,
                    'X-ZPM-Signature' => "sha256={$signature}",
                ])
                ->withBody($rawPayload ?: '{}', 'application/json')
                ->post($subscription->url);

            $this->delivery->update([
                'response_status' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'attempt' => $this->attempts(),
            ]);

            if ($response->successful()) {
                $this->delivery->update([
                    'status' => 'delivered',
                    'delivered_at' => now(),
                ]);

                $subscription->update([
                    'last_delivered_at' => now(),
                    'failure_count' => 0,
                ]);
            } else {
                $this->handleFailure("HTTP {$response->status()}");
            }
        } catch (\Throwable $e) {
            $this->delivery->update([
                'response_status' => 0,
                'response_body' => substr($e->getMessage(), 0, 1000),
                'attempt' => $this->attempts(),
            ]);

            $this->handleFailure($e->getMessage());
        }
    }

    protected function handleFailure(string $reason): void
    {
        $subscription = $this->delivery->subscription;

        if ($this->attempts() >= $this->tries) {
            $this->delivery->update(['status' => 'failed']);
            $subscription->increment('failure_count');
            Log::warning("Outbound webhook permanently failed: {$this->delivery->public_id}", ['reason' => $reason]);
        } else {
            $this->release($this->backoff[$this->attempts() - 1] ?? 60);
        }
    }
}
