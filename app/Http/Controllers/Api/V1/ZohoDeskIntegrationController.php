<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Services\ApiKeyService;
use App\Domain\Integrations\ZohoDesk\Services\ZohoDeskService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZohoDeskIntegrationController extends Controller
{
    public function __construct(
        protected ZohoDeskService $zohoDeskService,
        protected ApiKeyService $apiKeyService
    ) {}

    /**
     * Authorize Zoho Desk integration request via token or API key.
     */
    protected function resolveAuthorizedUser(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $token = $request->header('X-API-KEY')
            ?? $request->header('X-Api-Key')
            ?? $request->header('X-Zoho-Desk-Token')
            ?? $request->query('api_key')
            ?? $request->query('token');

        if (! $token && $request->hasHeader('Authorization')) {
            $authHeader = (string) $request->header('Authorization');
            if (str_starts_with($authHeader, 'Bearer ')) {
                $token = substr($authHeader, 7);
            }
        }

        if (empty($token)) {
            return null;
        }

        // 1. Check configured Zoho Desk API Token in settings
        $configuredToken = (string) Setting::get('zoho_desk.api_token');
        if (! empty($configuredToken) && hash_equals($configuredToken, (string) $token)) {
            // Return first admin or null
            return User::role('Super Administrator')->first() ?? User::first();
        }

        // 2. Check standard ApiKeyService
        $apiKey = $this->apiKeyService->authenticate((string) $token);
        if ($apiKey) {
            return $apiKey->user;
        }

        return null;
    }

    /**
     * Get booking options, active resource pools, and templates for the Zoho Desk widget.
     */
    public function options(Request $request): JsonResponse
    {
        $actor = $this->resolveAuthorizedUser($request);
        if (! $actor) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing API Key. Provide via X-API-KEY header or ?api_key= query parameter.',
            ], 401);
        }

        $pools = ResourcePool::where('is_active', true)
            ->select(['id', 'name', 'capacity'])
            ->get();

        $templates = MeetingTemplate::where('is_active', true)
            ->select(['id', 'name', 'duration_minutes'])
            ->get();

        $config = $this->zohoDeskService->getConfiguration();

        return response()->json([
            'success' => true,
            'pools' => $pools,
            'templates' => $templates,
            'default_pool_id' => $config['default_pool_id'],
            'default_template_id' => $config['default_template_id'],
            'default_duration_minutes' => $config['default_duration_minutes'],
            'default_is_public' => $config['default_is_public'],
            'auto_close_ticket' => $config['auto_close_ticket'],
            'ticket_close_status' => $config['ticket_close_status'],
            'comment_template' => $config['comment_template'],
            'org_name' => Setting::get('org.name', 'Zoom Pool Manager'),
        ]);
    }

    /**
     * Book a meeting on behalf of the ticket creator, post comment, and optionally close ticket.
     */
    public function bookAndReply(Request $request): JsonResponse
    {
        $actor = $this->resolveAuthorizedUser($request);
        if (! $actor) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing API Key. Provide via X-API-KEY header or ?api_key= query parameter.',
            ], 401);
        }

        $validated = $request->validate([
            'ticket_id' => 'required|string|max:100',
            'ticket_number' => 'nullable|string|max:100',
            'ticket_subject' => 'nullable|string|max:255',
            'ticket_email' => 'required|email|max:255',
            'ticket_contact_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'starts_at' => 'required|date',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'ends_at' => 'nullable|date|after:starts_at',
            'pool_id' => 'nullable|integer|exists:resource_pools,id',
            'template_id' => 'nullable|integer|exists:meeting_templates,id',
            'post_to_ticket' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
            'close_ticket' => 'nullable|boolean',
            'ticket_status' => 'nullable|string|max:50',
            'custom_comment' => 'nullable|string',
            'share_host_key' => 'nullable|boolean',
            'agent_id' => 'nullable|string|max:100',
            'agent_name' => 'nullable|string|max:255',
            'agent_email' => 'nullable|email|max:255',
        ]);

        try {
            $result = $this->zohoDeskService->bookMeetingFromTicket($validated, $actor);

            return response()->json($result);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
