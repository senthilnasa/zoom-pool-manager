<?php

namespace App\Http\Controllers;

use App\Domain\Webhooks\Jobs\ProcessZoomWebhookJob;
use App\Domain\Webhooks\Models\ZoomWebhookEvent;
use App\Domain\Webhooks\Services\ZoomWebhookVerifier;
use App\Domain\Zoom\Models\ZoomConnection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class WebhookController extends Controller
{
    public function __construct(
        protected ZoomWebhookVerifier $verifier
    ) {}

    /**
     * Intake endpoint for Zoom incoming webhooks.
     */
    public function handle(Request $request, ?string $public_id = null): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $rawBody = (string) $request->getContent();

        // 0. Locate connection (by public_id or fallback to primary connection)
        $connection = $public_id ? ZoomConnection::where('public_id', $public_id)->first() : null;
        if (! $connection) {
            $connection = ZoomConnection::first();
        }

        $secretToken = $connection?->webhook_secret_token
            ?: config('zoom.webhook_secret_token', config('services.zoom.webhook_secret', ''));

        // 1. Zoom URL Validation Challenge-Response Handshake
        if (($payload['event'] ?? '') === 'endpoint.url_validation') {
            $plainToken = (string) ($payload['payload']['plainToken'] ?? '');

            Log::channel('webhook')->info('Zoom URL validation challenge received', [
                'has_secret' => ! empty($secretToken),
                'connection_id' => $connection?->id,
                'ip' => $request->ip(),
            ]);

            $validation = $this->verifier->verifyUrlValidation($plainToken, (string) $secretToken);

            return response()->json($validation, 200);
        }

        $signature = (string) $request->header('x-zm-signature', '');
        $timestamp = (string) $request->header('x-zm-request-timestamp', '');

        Log::channel('webhook')->info('Inbound Zoom webhook received', [
            'event' => $payload['event'] ?? 'unknown',
            'has_signature' => ! empty($signature),
            'timestamp' => $timestamp,
            'ip' => $request->ip(),
        ]);

        // 2. If secret token is configured, enforce strict HMAC signature verification
        if (! empty($secretToken)) {
            $isValid = $this->verifier->verifySignature($rawBody, $signature, $timestamp, (string) $secretToken);
            if (! $isValid) {
                Log::channel('webhook')->warning('Inbound Zoom webhook rejected: invalid or expired signature', [
                    'event' => $payload['event'] ?? 'unknown',
                    'timestamp' => $timestamp,
                    'ip' => $request->ip(),
                ]);

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
    public function index(Request $request): View|JsonResponse
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

        if ($request->wantsJson()) {
            $events = $query->paginate(20)->withQueryString();

            return response()->json([
                'events' => $events,
                'status' => $status,
                'event_type' => $eventType,
            ]);
        }

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Inbound Webhook Events</h1>',
        ]);
    }

    /**
     * Replay a failed or unhandled webhook event.
     */
    public function replay(string $public_id): RedirectResponse|JsonResponse
    {
        $event = ZoomWebhookEvent::where('public_id', $public_id)->firstOrFail();

        $event->update([
            'status' => 'pending',
            'error_message' => null,
        ]);

        ProcessZoomWebhookJob::dispatch($event->id);

        if (request()->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Webhook event {$event->event_id} queued for replay."]);
        }

        return back()->with('success', "Webhook event {$event->event_id} queued for replay.");
    }
}
