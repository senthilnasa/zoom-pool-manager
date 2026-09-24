<?php

namespace App\Http\Controllers;

use App\Domain\Reconciliation\Models\DriftConflict;
use App\Domain\Reconciliation\Services\DriftReconciliationService;
use App\Domain\Users\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DriftConflictController extends Controller
{
    public function __construct(
        protected DriftReconciliationService $driftService
    ) {}

    /**
     * List drift conflicts.
     */
    public function index(Request $request): View|JsonResponse
    {
        $status = $request->query('status', 'open');

        $query = DriftConflict::with(['meeting', 'resource', 'resolvedBy'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $conflicts = $query->paginate(20)->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($conflicts);
        }

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Drift Conflicts</h1>',
        ]);
    }

    /**
     * Resolve a drift conflict.
     */
    public function resolve(Request $request, string $public_id): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string|in:resolve,accepted_zoom,ignored,marked_external',
            'notes' => 'nullable|string|max:500',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $conflict = DriftConflict::where('public_id', $public_id)->firstOrFail();

        $this->driftService->resolveConflict(
            $conflict,
            $validated['action'],
            $user,
            $validated['notes'] ?? null
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'action' => $validated['action']]);
        }

        return back()->with('success', "Drift conflict resolved with action: {$validated['action']}.");
    }

    /**
     * Trigger a manual reconciliation scan.
     */
    public function scan(Request $request): RedirectResponse|JsonResponse
    {
        $days = (int) $request->input('days', 30);
        $result = $this->driftService->reconcile($days);

        if ($request->wantsJson()) {
            return response()->json(array_merge(['success' => true], $result));
        }

        return back()->with('success', "Reconciliation scan completed. Checked {$result['checked_resources']} resources, {$result['conflicts_created']} new conflicts identified.");
    }
}
