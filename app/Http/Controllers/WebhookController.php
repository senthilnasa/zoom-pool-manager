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
     * Informational health check response for GET requests (e.g., browser navigation or health probes).
     */
    public function ping(Request $request, ?string $public_id = null): JsonResponse
    {
        return response()->json([
            'status' => 'active',
            'service' => 'Zoom Pool Manager - Zoom Webhook Intake Endpoint',
            'supported_method' => 'POST',
            'message' => 'This endpoint accepts incoming HTTP POST webhooks from Zoom (URL validation CRC handshake and event notifications). Direct browser GET requests are not processed.',
            'docs' => 'Configure this URL in your Zoom Marketplace App under Feature -> Event Subscriptions.',
        ], 200);
    }

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

            $connection = ZoomConnection::first();
            $secretToken = $connection?->webhook_secret_token
                ?: config('zoom.webhook_secret_token', config('services.zoom.webhook_secret', ''));

            $stats = [
                'total' => ZoomWebhookEvent::count(),
                'processed' => ZoomWebhookEvent::where('status', 'processed')->count(),
                'pending' => ZoomWebhookEvent::where('status', 'pending')->count(),
                'failed' => ZoomWebhookEvent::where('status', 'failed')->count(),
                'signature_valid' => ZoomWebhookEvent::where('signature_valid', true)->count(),
                'signature_invalid' => ZoomWebhookEvent::where('signature_valid', false)->count(),
            ];

            return response()->json([
                'events' => $events,
                'stats' => $stats,
                'webhook_url' => url('/webhooks/zoom'),
                'alt_webhook_url' => url('/api/webhooks/zoom'),
                'has_secret_token' => ! empty($secretToken),
                'connection_name' => $connection ? $connection->name : 'Primary Connection',
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

    /**
     * Send or simulate a test webhook event for debugging.
     */
    public function simulate(Request $request): JsonResponse
    {
        $request->validate([
            'event_type' => 'required|string',
            'payload' => 'nullable|array',
            'process_immediately' => 'nullable|boolean',
        ]);

        $eventType = (string) $request->input('event_type');
        $connection = ZoomConnection::first();
        $secretToken = $connection?->webhook_secret_token
            ?: config('zoom.webhook_secret_token', config('services.zoom.webhook_secret', ''));

        // Handle URL Validation challenge simulation
        if ($eventType === 'endpoint.url_validation') {
            $plainToken = (string) ($request->input('payload.plainToken') ?? 'test_plain_token_'.bin2hex(random_bytes(6)));
            $validation = $this->verifier->verifyUrlValidation($plainToken, (string) $secretToken);

            return response()->json([
                'success' => true,
                'message' => 'Simulated Zoom URL validation CRC challenge successfully.',
                'validation' => $validation,
                'has_secret' => ! empty($secretToken),
            ]);
        }

        $eventId = 'sim-'.bin2hex(random_bytes(8));
        $payload = $request->input('payload');

        if (empty($payload)) {
            $meetingId = '9'.rand(100000000, 999999999);
            $payload = [
                'event' => $eventType,
                'event_ts' => now()->timestamp * 1000,
                'payload' => [
                    'account_id' => $connection ? $connection->account_id : 'test_account',
                    'object' => [
                        'id' => $meetingId,
                        'uuid' => base64_encode('uuid_'.$meetingId),
                        'topic' => 'Simulated Debug Meeting',
                        'type' => 2,
                        'start_time' => now()->toIso8601String(),
                        'duration' => 60,
                        'timezone' => 'UTC',
                        'host_id' => 'sim_host_'.rand(1000, 9999),
                    ],
                ],
            ];
        }

        $event = ZoomWebhookEvent::create([
            'connection_id' => $connection?->id,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
            'signature_valid' => true,
            'status' => 'pending',
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        if ($request->boolean('process_immediately', true)) {
            try {
                ProcessZoomWebhookJob::dispatchSync($event->id);
                $event->refresh();
            } catch (\Throwable $e) {
                $event->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                ]);
            }
        } else {
            ProcessZoomWebhookJob::dispatch($event->id);
        }

        return response()->json([
            'success' => true,
            'message' => "Simulated event '{$eventType}' recorded successfully.",
            'event' => $event,
        ]);
    }

    /**
     * Clear simulated debug webhook events.
     */
    public function clear(Request $request): JsonResponse
    {
        $count = ZoomWebhookEvent::where('event_id', 'like', 'sim-%')->delete();

        return response()->json([
            'success' => true,
            'message' => "Cleared {$count} simulated debug webhook events.",
        ]);
    }
}
