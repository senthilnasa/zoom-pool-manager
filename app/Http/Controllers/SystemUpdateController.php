<?php

namespace App\Http\Controllers;

use App\Domain\System\Services\AppUpdateService;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SystemUpdateController extends Controller
{
    public function __construct(
        protected AppUpdateService $updateService
    ) {}

    /**
     * Authorize that the current user has permission to manage system updates.
     */
    protected function authorizeUpdates(): void
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user || (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator') && ! $user->can('settings.manage'))) {
            abort(403, 'Unauthorized. Administrator privileges required to manage application updates.');
        }
    }

    /**
     * System updates overview page.
     */
    public function index(): View
    {
        $this->authorizeUpdates();

        $currentVersion = $this->updateService->getCurrentVersion();
        $releaseInfo = $this->updateService->checkForUpdates(force: false);
        $isLocked = $this->updateService->isLocked();

        return view('system.updates', compact('currentVersion', 'releaseInfo', 'isLocked'));
    }

    /**
     * Force check for latest release from GitHub API.
     */
    public function check(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeUpdates();

        $releaseInfo = $this->updateService->checkForUpdates(force: true);

        if ($request->wantsJson()) {
            return response()->json($releaseInfo);
        }

        if ($releaseInfo['error']) {
            return back()->with('error', 'Update check failed: '.$releaseInfo['error']);
        }

        if ($releaseInfo['update_available']) {
            return back()->with('success', "New update available: v{$releaseInfo['latest_version']}");
        }

        return back()->with('success', 'Your application is up to date.');
    }

    /**
     * Trigger application update.
     */
    public function apply(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeUpdates();

        if ($this->updateService->isLocked()) {
            $msg = 'An application update is currently in progress. Please wait.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 423);
            }

            return back()->with('error', $msg);
        }

        $result = $this->updateService->applyUpdate();

        if ($request->wantsJson()) {
            return response()->json($result, $result['success'] ? 200 : 500);
        }

        if ($result['success']) {
            return redirect()->route('admin.system.updates.index')->with('success', $result['message']);
        }

        return redirect()->route('admin.system.updates.index')->with('error', $result['message']);
    }
}
