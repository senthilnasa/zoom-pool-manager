<?php

namespace App\Http\Controllers;

use App\Domain\Api\Models\ApiKey;
use App\Domain\Api\Models\WebhookDelivery;
use App\Domain\Api\Models\WebhookSubscription;
use App\Domain\Api\Services\ApiKeyService;
use App\Domain\Api\Services\OutboundWebhookService;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiAdminController extends Controller
{
    public function __construct(
        protected ApiKeyService $apiKeyService,
        protected OutboundWebhookService $webhookService
    ) {}

    /**
     * Display list of API keys.
     */
    public function indexKeys(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            $keys = ApiKey::with('user')
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json($keys);
        }

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>API Keys Management</h1>',
        ]);
    }

    /**
     * Create a new API key.
     */
    public function storeKey(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['string'],
            'rate_limit' => ['nullable', 'integer', 'min:10', 'max:1000'],
            'expires_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $expiresAt = ! empty($validated['expires_days'])
            ? now()->addDays((int) $validated['expires_days'])
            : null;

        $rateLimit = (int) ($validated['rate_limit'] ?? 60);

        $result = $this->apiKeyService->createKey(
            user: $actor,
            name: $validated['name'],
            scopes: $validated['scopes'],
            rateLimit: $rateLimit,
            expiresAt: $expiresAt,
            actor: $actor
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'key' => $result['key'],
                'plainTextToken' => $result['plainTextToken'],
            ]);
        }

        return redirect()->route('admin.api-keys.index')
            ->with('status', 'API key created successfully.')
            ->with('plainTextToken', $result['plainTextToken']);
    }

    /**
     * Revoke an API key.
     */
    public function revokeKey(string $publicId, Request $request): RedirectResponse|JsonResponse
    {
        /** @var ApiKey $key */
        $key = ApiKey::where('public_id', $publicId)->firstOrFail();

        /** @var User $actor */
        $actor = $request->user();

        $this->apiKeyService->revokeKey($key, $actor);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('admin.api-keys.index')
            ->with('status', 'API key revoked.');
    }

    /**
     * Display outbound webhook subscriptions and deliveries.
     */
    public function indexWebhooks(Request $request): View|JsonResponse
    {
        $subscriptions = WebhookSubscription::with('creator')
            ->orderBy('created_at', 'desc')
            ->get();

        $deliveries = WebhookDelivery::with('subscription')
            ->orderBy('created_at', 'desc')
            ->limit(25)
            ->get();

        if ($request->wantsJson()) {
            return response()->json([
                'subscriptions' => $subscriptions,
                'deliveries' => $deliveries,
            ]);
        }

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Outbound Webhooks</h1>',
        ]);
    }

    /**
     * Create an outbound webhook subscription.
     */
    public function storeWebhook(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'url' => ['required', 'url', 'max:500'],
            'secret' => ['required', 'string', 'min:16'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string'],
        ]);

        /** @var User $actor */
        $actor = $request->user();

        $subscription = WebhookSubscription::create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'secret' => $validated['secret'],
            'events' => $validated['events'],
            'is_active' => true,
            'created_by_user_id' => $actor->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'subscription' => $subscription]);
        }

        return redirect()->route('admin.outbound-webhooks.index')
            ->with('status', 'Webhook subscription created.');
    }

    /**
     * Send test ping to webhook subscription.
     */
    public function testWebhook(string $publicId, Request $request): RedirectResponse|JsonResponse
    {
        /** @var WebhookSubscription $subscription */
        $subscription = WebhookSubscription::where('public_id', $publicId)->firstOrFail();

        $this->webhookService->testPing($subscription);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => "Test ping dispatched to {$subscription->url}."]);
        }

        return redirect()->route('admin.outbound-webhooks.index')
            ->with('status', "Test ping dispatched to {$subscription->url}.");
    }

    /**
     * Toggle webhook subscription active state.
     */
    public function toggleWebhook(string $publicId, Request $request): RedirectResponse|JsonResponse
    {
        /** @var WebhookSubscription $subscription */
        $subscription = WebhookSubscription::where('public_id', $publicId)->firstOrFail();

        $subscription->update(['is_active' => ! $subscription->is_active]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'subscription' => $subscription->fresh()]);
        }

        return redirect()->route('admin.outbound-webhooks.index')
            ->with('status', 'Webhook subscription updated.');
    }

    /**
     * Retry a failed webhook delivery.
     */
    public function retryDelivery(string $publicId, Request $request): RedirectResponse
    {
        /** @var WebhookDelivery $delivery */
        $delivery = WebhookDelivery::where('public_id', $publicId)->firstOrFail();

        $this->webhookService->retryDelivery($delivery);

        return redirect()->route('admin.outbound-webhooks.index')
            ->with('status', 'Delivery queued for retry.');
    }
}
