<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Services\ApiKeyService;
use App\Domain\Integrations\GoogleWorkspace\Services\GoogleWorkspaceService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GoogleWorkspaceIntegrationController extends Controller
{
    public function __construct(
        protected GoogleWorkspaceService $googleWorkspaceService,
        protected ApiKeyService $apiKeyService
    ) {}

    /**
     * Authorize request via configured Google Workspace token or API key.
     */
    protected function resolveAuthorizedUser(Request $request): ?User
    {
        if ($request->user()) {
            return $request->user();
        }

        $token = $request->header('X-API-KEY')
            ?? $request->header('X-Api-Key')
            ?? $request->header('X-Google-Workspace-Token')
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

        // 1. Check configured Google Workspace API Token in settings
        $configuredToken = (string) Setting::get('google_workspace.api_token');
        if (! empty($configuredToken) && hash_equals($configuredToken, (string) $token)) {
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
     * Get booking options and active resource pools for Google Workspace Add-on.
     */
    public function options(Request $request): JsonResponse
    {
        $actor = $this->resolveAuthorizedUser($request);
        if (! $actor) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Valid Google Workspace API token or API key required.',
            ], 401);
        }

        $pools = ResourcePool::where('is_active', true)
            ->select(['id', 'public_id', 'name', 'capacity'])
            ->get();

        $templates = MeetingTemplate::where('is_active', true)
            ->select(['id', 'public_id', 'name', 'duration_minutes'])
            ->get();

        $config = $this->googleWorkspaceService->getConfiguration();

        return response()->json([
            'enabled' => $config['enabled'],
            'default_pool_id' => $config['default_pool_id'],
            'default_template_id' => $config['default_template_id'],
            'default_duration_minutes' => $config['default_duration_minutes'],
            'allowed_domains' => $config['allowed_domains'],
            'pools' => $pools,
            'templates' => $templates,
        ]);
    }

    /**
     * Google Calendar conference booking endpoint.
     */
    public function bookConference(Request $request): JsonResponse
    {
        $actor = $this->resolveAuthorizedUser($request);
        if (! $actor) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Valid Google Workspace API token or API key required.',
            ], 401);
        }

        $validated = $request->validate([
            'user_email' => 'required|email|max:255',
            'user_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'calendar_event_id' => 'nullable|string|max:255',
            'starts_at' => 'nullable|string',
            'ends_at' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'pool_id' => 'nullable|integer|exists:resource_pools,id',
            'template_id' => 'nullable|integer|exists:meeting_templates,id',
            'share_host_key' => 'nullable|boolean',
            'invitees' => 'nullable|array',
            'invitees.*' => 'email',
        ]);

        $validated['channel'] = 'google_calendar';

        try {
            $result = $this->googleWorkspaceService->bookMeeting($validated, $actor);

            return response()->json($result, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation / Policy Rejection',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Google Workspace book-conference error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'error' => 'Booking Failure',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Gmail compose quick meeting link endpoint.
     */
    public function bookLink(Request $request): JsonResponse
    {
        $actor = $this->resolveAuthorizedUser($request);
        if (! $actor) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Valid Google Workspace API token or API key required.',
            ], 401);
        }

        $validated = $request->validate([
            'user_email' => 'required|email|max:255',
            'user_name' => 'nullable|string|max:255',
            'title' => 'nullable|string|max:255',
            'starts_at' => 'nullable|string',
            'ends_at' => 'nullable|string',
            'duration_minutes' => 'nullable|integer|min:15|max:480',
            'pool_id' => 'nullable|integer|exists:resource_pools,id',
            'template_id' => 'nullable|integer|exists:meeting_templates,id',
            'share_host_key' => 'nullable|boolean',
        ]);

        $validated['channel'] = 'gmail_compose';

        try {
            $result = $this->googleWorkspaceService->bookMeeting($validated, $actor);

            return response()->json($result, 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation / Policy Rejection',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Booking Failure',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
