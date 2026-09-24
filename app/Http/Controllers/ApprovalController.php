<?php

namespace App\Http\Controllers;

use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\MeetingApproval;
use App\Domain\Workflow\Services\ApprovalWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApprovalController extends Controller
{
    public function __construct(
        protected ApprovalWorkflowService $approvalService,
        protected MeetingService $meetingService
    ) {}

    /**
     * Display a listing of approvals for the current user (assigned or delegated).
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        // Get user IDs that have delegated approval authority to the current user
        $delegatorIds = ApprovalDelegation::currentlyValid()
            ->where('delegate_user_id', $user->id)
            ->pluck('user_id')
            ->toArray();

        $allowedApproverIds = array_unique(array_merge([$user->id], $delegatorIds));

        $pendingApprovals = MeetingApproval::with(['meeting.requester', 'meeting.department', 'meeting.template', 'approver'])
            ->where('decision', 'pending')
            ->whereIn('approver_user_id', $allowedApproverIds)
            ->orderBy('due_at', 'asc')
            ->paginate(15, ['*'], 'pending_page');

        $resolvedApprovals = MeetingApproval::with(['meeting.requester', 'meeting.department', 'approver', 'delegatedFrom'])
            ->whereIn('decision', ['approved', 'rejected', 'bypassed'])
            ->whereIn('approver_user_id', $allowedApproverIds)
            ->orderBy('decided_at', 'desc')
            ->paginate(15, ['*'], 'resolved_page');

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Workflow Approvals</h1>',
        ]);
    }

    /**
     * Display the approval review page with conflict check.
     */
    public function show(Request $request, string $publicId): View
    {
        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Approval Request</h1>',
        ]);
    }

    /**
     * Process an approval or rejection decision.
     */
    public function decide(Request $request, string $publicId): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'decision_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var MeetingApproval $approval */
        $approval = MeetingApproval::where('public_id', $publicId)->firstOrFail();

        try {
            $this->approvalService->decide(
                approval: $approval,
                actor: $user,
                decision: $validated['decision'],
                notes: $validated['decision_notes'] ?? null
            );

            $actionWord = $validated['decision'] === 'approved' ? 'approved' : 'rejected';

            return redirect()->route('approvals.index')
                ->with('status', "Meeting request was successfully {$actionWord}.");
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
