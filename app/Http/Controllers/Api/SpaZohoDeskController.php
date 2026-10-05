<?php

namespace App\Http\Controllers\Api;

use App\Domain\Integrations\ZohoDesk\Services\ZohoDeskService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Zoom\Models\ResourcePool;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpaZohoDeskController extends Controller
{
    public function __construct(
        protected ZohoDeskService $zohoDeskService
    ) {}

    /**
     * Authorize administrator access for Zoho Desk settings.
     */
    protected function authorizeSettings(Request $request): void
    {
        $user = $request->user();
        if (! $user || (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator') && ! $user->can('settings.manage') && ! $user->can('system.manage'))) {
            abort(403, 'Unauthorized. Administrator privileges required to manage Zoho Desk integration.');
        }
    }

    /**
     * Get Zoho Desk configuration and available pools/templates.
     */
    public function show(Request $request): JsonResponse
    {
        $this->authorizeSettings($request);

        $config = $this->zohoDeskService->getConfiguration();

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
     * Update Zoho Desk configuration.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorizeSettings($request);

        $validated = $request->validate([
            'enabled' => 'nullable|boolean',
            'server_url' => 'nullable|url',
            'api_token' => 'nullable|string|max:120',
            'dc' => 'nullable|string|in:in,com,eu,com.au,jp,ca,com.cn',
            'org_id' => 'nullable|string|max:100',
            'client_id' => 'nullable|string|max:150',
            'client_secret' => 'nullable|string|max:255',
            'refresh_token' => 'nullable|string|max:255',
            'agent_token' => 'nullable|string|max:255',
            'default_pool_id' => 'nullable|integer|exists:resource_pools,id',
            'default_template_id' => 'nullable|integer|exists:meeting_templates,id',
            'default_duration_minutes' => 'nullable|integer|min:15|max:480',
            'default_is_public' => 'nullable|boolean',
            'auto_close_ticket' => 'nullable|boolean',
            'ticket_close_status' => 'nullable|string|max:50',
            'comment_template' => 'nullable|string',
        ]);

        $this->zohoDeskService->updateConfiguration($validated, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Zoho Desk configuration updated successfully.',
            'config' => $this->zohoDeskService->getConfiguration(),
        ]);
    }

    /**
     * Test connection to Zoho Desk API.
     */
    public function test(Request $request): JsonResponse
    {
        $this->authorizeSettings($request);

        $result = $this->zohoDeskService->testConnection($request->all());

        return response()->json($result);
    }

    /**
     * Download the pre-configured Zoho Desk extension ZIP package.
     */
    public function downloadExtensionPackage(Request $request): BinaryFileResponse
    {
        $this->authorizeSettings($request);

        $zipPath = $this->zohoDeskService->generateExtensionPackageZip();

        return response()->download($zipPath, 'zoom-pool-manager-zoho-desk.zip', [
            'Content-Type' => 'application/zip',
        ])->deleteFileAfterSend(true);
    }
}
