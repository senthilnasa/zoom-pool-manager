<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrations\GoogleWorkspace\Services\GoogleWorkspaceService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Zoom\Models\ResourcePool;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpaGoogleWorkspaceController extends Controller
{
    public function __construct(
        protected GoogleWorkspaceService $googleWorkspaceService
    ) {}

    /**
     * Authorize administrator access for Google Workspace settings.
     */
    protected function authorizeSettings(Request $request): void
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator') && ! $user->can('settings.manage') && ! $user->can('system.manage'))) {
            abort(403, 'Unauthorized. Administrator privileges required to manage Google Workspace integration.');
        }
    }

    /**
     * Get Google Workspace configuration and available pools/templates.
     */
    public function show(Request $request): JsonResponse
    {
        $this->authorizeSettings($request);

        $config = $this->googleWorkspaceService->getConfiguration();

        $pools = ResourcePool::where('is_active', true)
            ->select(['id', 'name', 'capacity'])
            ->get();

        $templates = MeetingTemplate::where('is_active', true)
            ->select(['id', 'name', 'duration_minutes'])
            ->get();

        return response()->json([
            'config' => $config,
            'pools' => $pools,
            'templates' => $templates,
        ]);
    }

    /**
     * Update Google Workspace configuration.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorizeSettings($request);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'server_url' => 'required|url|max:255',
            'api_token' => 'nullable|string|max:255',
            'allowed_domains' => 'nullable|string|max:500',
            'default_pool_id' => 'nullable|integer|exists:resource_pools,id',
            'default_template_id' => 'nullable|integer|exists:meeting_templates,id',
            'default_duration_minutes' => 'required|integer|min:15|max:480',
            'share_host_key' => 'required|boolean',
            'email_template' => 'nullable|string|max:5000',
        ]);

        $this->googleWorkspaceService->updateConfiguration($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Google Workspace integration settings updated successfully.',
            'config' => $this->googleWorkspaceService->getConfiguration(),
        ]);
    }

    /**
     * Test connection to Google Workspace integration.
     */
    public function test(Request $request): JsonResponse
    {
        $this->authorizeSettings($request);

        $result = $this->googleWorkspaceService->testConnection();

        return response()->json($result);
    }

    /**
     * Regenerate Google Workspace API Token.
     */
    public function regenerateToken(Request $request): JsonResponse
    {
        $this->authorizeSettings($request);

        $newToken = $this->googleWorkspaceService->regenerateToken($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Google Workspace API token regenerated successfully.',
            'api_token' => $newToken,
        ]);
    }

    /**
     * Download the ready-to-deploy Google Workspace Add-on ZIP package.
     */
    public function downloadAddonPackage(Request $request): BinaryFileResponse
    {
        $this->authorizeSettings($request);

        $zipPath = $this->googleWorkspaceService->generateAddonZip();

        return response()->download($zipPath, 'zoom-pool-manager-google-workspace-addon.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
