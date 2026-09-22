<?php

namespace App\Domain\Webhooks\Services;

class ZoomWebhookVerifier
{
    /**
     * Handle Zoom URL validation challenge-response handshake.
     *
     * @return array{plainToken: string, encryptedToken: string}
     */
    public function verifyUrlValidation(string $plainToken, string $secretToken): array
    {
        $encryptedToken = hash_hmac('sha256', $plainToken, $secretToken);

        return [
            'plainToken' => $plainToken,
            'encryptedToken' => $encryptedToken,
        ];
    }

    /**
     * Verify HMAC-SHA256 signature from Zoom webhook headers.
     *
     * @param  string  $rawBody  The raw HTTP request body
     * @param  string  $signature  The x-zm-signature header value (e.g. v0=...)
     * @param  string|int  $timestamp  The x-zm-request-timestamp header value
     * @param  string  $secretToken  The connection's webhook secret token
     */
    public function verifySignature(string $rawBody, string $signature, string|int $timestamp, string $secretToken): bool
    {
        $numericTimestamp = (int) $timestamp;

        // Reject if timestamp is older than 5 minutes (300 seconds) or more than 5 minutes in future (replay protection)
        if (abs(time() - $numericTimestamp) > 300) {
            return false;
        }

        $message = "v0:{$timestamp}:{$rawBody}";
        $expectedHash = 'v0='.hash_hmac('sha256', $message, $secretToken);

        return hash_equals($expectedHash, $signature);
    }

    /**
     * Extract a stable, unique event identifier from Zoom webhook payload for deduplication.
     *
     * @param  array<string, mixed>  $payload
     */
    public function extractEventId(array $payload, string $rawBody): string
    {
        if (! empty($payload['event_id']) && is_string($payload['event_id'])) {
            return $payload['event_id'];
        }

        $eventType = (string) ($payload['event'] ?? 'unknown');
        $eventTs = (string) ($payload['event_ts'] ?? time());
        $objectId = '';

        if (isset($payload['payload']['object']) && is_array($payload['payload']['object'])) {
            $objectId = (string) ($payload['payload']['object']['id'] ?? $payload['payload']['object']['uuid'] ?? '');
        }

        if ($objectId !== '') {
            return "{$eventType}:{$objectId}:{$eventTs}";
        }

        return hash('sha256', "{$eventType}:{$eventTs}:{$rawBody}");
    }
}
