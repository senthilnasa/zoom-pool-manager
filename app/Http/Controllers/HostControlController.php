<?php

namespace App\Http\Controllers;

use App\Domain\HostControl\Services\HostControlService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HostControlController extends Controller
{
    public function __construct(
        protected HostControlService $hostControlService
    ) {}

    /**
     * JIT start meeting: validates timing and authorization, redirects away to Zoom start URL.
     */
    public function start(string $publicId, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        $emergencyReason = $request->input('emergency_reason') ? (string) $request->input('emergency_reason') : null;

        try {
            $startUrl = $this->hostControlService->getJitStartUrl($meeting, $user, $emergencyReason);

            return redirect()->away($startUrl);
        } catch (Exception $e) {
            return redirect()->route('meetings.show', $meeting->public_id)
                ->withErrors(['host_control' => $e->getMessage()]);
        }
    }

    /**
     * Reveal host key for late joining or claiming host role inside Zoom.
     */
    public function revealHostKey(string $publicId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        $emergencyReason = $request->input('emergency_reason') ? (string) $request->input('emergency_reason') : null;

        try {
            $hostKey = $this->hostControlService->revealHostKey($meeting, $user, $emergencyReason);

            return response()->json([
                'success' => true,
                'host_key' => $hostKey,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Manually force host key rotation on the assigned resource.
     */
    public function rotateHostKey(string $publicId, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasRole('super_admin') && ! $user->hasRole('it_admin')) {
            abort(403, 'Only IT Administrators may manually trigger host key rotation.');
        }

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        if (! $meeting->zoomResource) {
            return back()->withErrors(['host_control' => 'No Zoom resource assigned to this meeting.']);
        }

        $this->hostControlService->rotateHostKey($meeting->zoomResource, $user, 'manual_admin_rotation');

        return redirect()->route('meetings.show', $meeting->public_id)
            ->with('status', 'Host key has been rotated successfully.');
    }
}
