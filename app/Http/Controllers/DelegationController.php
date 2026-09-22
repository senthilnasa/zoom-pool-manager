<?php

namespace App\Http\Controllers;

use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DelegationController extends Controller
{
    public function __construct(
        protected ApprovalWorkflowService $approvalService
    ) {}

    /**
     * Display listing of delegations granted and received.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $myDelegations = ApprovalDelegation::with('delegate')
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $delegatedToMe = ApprovalDelegation::with('user')
            ->where('delegate_user_id', $user->id)
            ->where('is_active', true)
            ->where('ends_at', '>=', Carbon::now())
            ->orderBy('created_at', 'desc')
            ->get();

        $eligibleDelegates = User::where('id', '!=', $user->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('delegations.index', [
            'myDelegations' => $myDelegations,
            'delegatedToMe' => $delegatedToMe,
            'eligibleDelegates' => $eligibleDelegates,
        ]);
    }

    /**
     * Store new approval delegation.
     */
    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'delegate_user_id' => ['required', 'integer', 'exists:users,id', 'different:'.$user->id],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        /** @var User $delegate */
        $delegate = User::findOrFail($validated['delegate_user_id']);

        try {
            $this->approvalService->createDelegation(
                user: $user,
                delegate: $delegate,
                startsAt: Carbon::parse($validated['starts_at']),
                endsAt: Carbon::parse($validated['ends_at'])
            );

            return redirect()->route('delegations.index')
                ->with('status', "Approval authority successfully delegated to {$delegate->name}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Revoke an active delegation.
     */
    public function destroy(Request $request, string $publicId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var ApprovalDelegation $delegation */
        $delegation = ApprovalDelegation::where('public_id', $publicId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $delegation->update(['is_active' => false]);

        return redirect()->route('delegations.index')
            ->with('status', 'Delegation revoked.');
    }
}
