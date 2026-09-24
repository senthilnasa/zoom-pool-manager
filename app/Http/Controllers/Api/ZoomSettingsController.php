<?php

namespace App\Http\Controllers\Api;

use App\Domain\Users\Models\User;
use App\Domain\Zoom\Services\ZoomConfigurationService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ZoomSettingsController extends Controller
{
    public function __construct(
        protected ZoomConfigurationService $zoomConfigService
    ) {}

    /**
     * Authorize that the current user has settings management privileges.
     */
    protected function authorizeSettings(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator') && ! $user->can('settings.manage'))) {
            abort(403, 'Unauthorized. Administrator privileges required to manage Zoom configuration.');
        }
    }

    /**
     * Get current Zoom configuration.
     */
    public function show(): JsonResponse
    {
        $this->authorizeSettings();

        $config = $this->zoomConfigService->getSafeConfiguration();

        return response()->json($config);
    }

    /**
     * Update Zoom credentials and settings.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorizeSettings();

        /** @var array{name?: string, account_id?: string, client_id?: string, client_secret?: ?string, webhook_secret_token?: ?string, enabled?: bool} $validated */
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'account_id' => 'required|string|max:255',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'nullable|string|max:255',
            'webhook_secret_token' => 'nullable|string|max:255',
            'enabled' => 'boolean',
        ]);

        $this->zoomConfigService->updateConfiguration($validated);

        return response()->json([
            'success' => true,
            'message' => 'Zoom configuration saved successfully.',
            'config' => $this->zoomConfigService->getSafeConfiguration(),
        ]);
    }

    /**
     * Test the Zoom connection.
     */
    public function test(): JsonResponse
    {
        $this->authorizeSettings();

        $result = $this->zoomConfigService->testConnection();

        return response()->json($result, $result['success'] ? 200 : 422);
    }
}
