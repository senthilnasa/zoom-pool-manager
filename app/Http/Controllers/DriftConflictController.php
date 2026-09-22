<?php

namespace App\Http\Controllers;

use App\Domain\Reconciliation\Models\DriftConflict;
use App\Domain\Reconciliation\Services\DriftReconciliationService;
use App\Domain\Users\Models\User;
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
    public function index(Request $request): View
    {
        $status = $request->query('status', 'open');

        $query = DriftConflict::with(['meeting', 'resource', 'resolvedBy'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $conflicts = $query->paginate(20)->withQueryString();

        return view('drift.index', compact('conflicts', 'status'));
    }

    /**
     * Resolve a drift conflict.
     */
    public function resolve(Request $request, string $public_id): RedirectResponse
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

        return back()->with('success', "Drift conflict resolved with action: {$validated['action']}.");
    }

    /**
     * Trigger a manual reconciliation scan.
     */
    public function scan(Request $request): RedirectResponse
    {
        $days = (int) $request->input('days', 30);
        $result = $this->driftService->reconcile($days);

        return back()->with('success', "Reconciliation scan completed. Checked {$result['checked_resources']} resources, {$result['conflicts_created']} new conflicts identified.");
    }
}
