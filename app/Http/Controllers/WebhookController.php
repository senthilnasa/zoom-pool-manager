<?php

namespace App\Http\Controllers;

use App\Domain\Webhooks\Jobs\ProcessZoomWebhookJob;
use App\Domain\Webhooks\Models\ZoomWebhookEvent;
use App\Domain\Webhooks\Services\ZoomWebhookVerifier;
use App\Domain\Zoom\Models\ZoomConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebhookController extends Controller
{
    public function __construct(
        protected ZoomWebhookVerifier $verifier
    ) {}

    /**
     * Intake endpoint for Zoom incoming webhooks.
     */
    public function handle(Request $request, string $public_id): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $rawBody = (string) $request->getContent();

        // 1. Zoom URL Validation Challenge-Response Handshake
        if (($payload['event'] ?? '') === 'endpoint.url_validation') {
            $plainToken = (string) ($payload['payload']['plainToken'] ?? '');
            $connection = ZoomConnection::where('public_id', $public_id)->first();
            $secretToken = $connection?->webhook_secret_token ?: config('services.zoom.webhook_secret', '');

            $validation = $this->verifier->verifyUrlValidation($plainToken, (string) $secretToken);

            return response()->json($validation, 200);
        }

        // 2. Locate ZoomConnection and verify HMAC signature
        $connection = ZoomConnection::where('public_id', $public_id)->first();
        $secretToken = $connection?->webhook_secret_token ?: config('services.zoom.webhook_secret', '');

        $signature = (string) $request->header('x-zm-signature', '');
        $timestamp = (string) $request->header('x-zm-request-timestamp', '');

        // If secret token is configured, enforce strict HMAC signature verification
        if (! empty($secretToken)) {
            $isValid = $this->verifier->verifySignature($rawBody, $signature, $timestamp, (string) $secretToken);
            if (! $isValid) {
                return response()->json(['error' => 'Invalid or expired webhook signature'], 401);
            }
        }

        // 3. Extract unique event identifier & deduplicate (Replay Attack Prevention)
        $eventId = $this->verifier->extractEventId($payload, $rawBody);
        $eventType = (string) ($payload['event'] ?? 'unknown');

        if (ZoomWebhookEvent::where('event_id', $eventId)->exists()) {
            return response()->json(['status' => 'already_received'], 200);
        }

        // 4. Record event log
        $event = ZoomWebhookEvent::create([
            'connection_id' => $connection?->id,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
            'signature_valid' => true,
            'status' => 'pending',
            'ip_address' => $request->ip(),
        ]);

        // 5. Dispatch async processing job
        ProcessZoomWebhookJob::dispatch($event->id);

        return response()->json(['status' => 'queued', 'event_id' => $eventId], 200);
    }

    /**
     * Admin view of inbound webhook events.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status');
        $eventType = $request->query('event_type');

        $query = ZoomWebhookEvent::with('connection')->latest();

        if (! empty($status)) {
            $query->where('status', (string) $status);
        }

        if (! empty($eventType)) {
            $query->where('event_type', (string) $eventType);
        }

        $events = $query->paginate(20)->withQueryString();

        return view('webhooks.index', compact('events', 'status', 'eventType'));
    }

    /**
     * Replay a failed or unhandled webhook event.
     */
    public function replay(string $public_id): RedirectResponse
    {
        $event = ZoomWebhookEvent::where('public_id', $public_id)->firstOrFail();

        $event->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        ProcessZoomWebhookJob::dispatch($event->id);

        return back()->with('success', "Webhook event {$event->event_id} queued for replay.");
    }
}
