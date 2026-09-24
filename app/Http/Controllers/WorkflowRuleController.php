<?php

namespace App\Http\Controllers;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\WorkflowRule;
use App\Domain\Workflow\Services\RuleEvaluationEngine;
use App\Domain\Zoom\Models\ResourcePool;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkflowRuleController extends Controller
{
    public function __construct(
        protected RuleEvaluationEngine $ruleEngine,
        protected AuditService $auditService
    ) {}

    /**
     * List all configured workflow rules.
     */
    public function index(Request $request): View|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'rules' => WorkflowRule::orderedByPriority()->get(),
                'departments' => Department::all(),
                'templates' => MeetingTemplate::all(),
                'profiles' => SecurityProfile::all(),
                'pools' => ResourcePool::all(),
            ]);
        }

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Workflow Rules</h1>',
        ]);
    }

    /**
     * Show rule creation visual builder.
     */
    public function create(): View
    {
        return app(SpaController::class)->index(request(), [
            'fallbackHtml' => '<h1>Create Workflow Rule</h1>',
        ]);
    }

    /**
     * Store newly created workflow rule.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'conditions' => ['required', 'array'],
            'actions' => ['required', 'array'],
            'is_enabled' => ['boolean'],
        ]);

        /** @var WorkflowRule $rule */
        $rule = WorkflowRule::create([
            'name' => $validated['name'],
            'priority' => (int) $validated['priority'],
            'conditions' => $validated['conditions'],
            'actions' => $validated['actions'],
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);

        $this->auditService->log(
            event: 'workflow_rule.created',
            auditable: $rule,
            actor: $request->user(),
            newValues: [
                'name' => $rule->name,
                'priority' => $rule->priority,
            ]
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'rule' => $rule]);
        }

        return redirect()->route('workflows.index')
            ->with('status', 'Workflow rule created successfully.');
    }

    /**
     * Show rule edit form.
     */
    public function edit(string $publicId): View
    {
        return app(SpaController::class)->index(request(), [
            'fallbackHtml' => '<h1>Edit Workflow Rule</h1>',
        ]);
    }

    /**
     * Update workflow rule.
     */
    public function update(Request $request, string $publicId): RedirectResponse|JsonResponse
    {
        /** @var WorkflowRule $rule */
        $rule = WorkflowRule::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'conditions' => ['required', 'array'],
            'actions' => ['required', 'array'],
            'is_enabled' => ['boolean'],
        ]);

        $oldValues = $rule->only(['name', 'priority', 'conditions', 'actions', 'is_enabled']);

        $rule->update([
            'name' => $validated['name'],
            'priority' => (int) $validated['priority'],
            'conditions' => $validated['conditions'],
            'actions' => $validated['actions'],
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);

        $this->auditService->log(
            event: 'workflow_rule.updated',
            auditable: $rule,
            actor: $request->user(),
            oldValues: $oldValues,
            newValues: $rule->only(['name', 'priority', 'conditions', 'actions', 'is_enabled'])
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'rule' => $rule]);
        }

        return redirect()->route('workflows.index')
            ->with('status', 'Workflow rule updated successfully.');
    }

    /**
     * Delete workflow rule.
     */
    public function destroy(Request $request, string $publicId): RedirectResponse|JsonResponse
    {
        /** @var WorkflowRule $rule */
        $rule = WorkflowRule::where('public_id', $publicId)->firstOrFail();

        $this->auditService->log(
            event: 'workflow_rule.deleted',
            auditable: $rule,
            actor: $request->user(),
            oldValues: ['name' => $rule->name]
        );

        $rule->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('workflows.index')
            ->with('status', 'Workflow rule deleted.');
    }

    /**
     * Toggle rule enabled status.
     */
    public function toggle(Request $request, string $publicId): RedirectResponse|JsonResponse
    {
        /** @var WorkflowRule $rule */
        $rule = WorkflowRule::where('public_id', $publicId)->firstOrFail();
        $rule->update(['is_enabled' => ! $rule->is_enabled]);

        $this->auditService->log(
            event: 'workflow_rule.toggled',
            auditable: $rule,
            actor: $request->user(),
            newValues: ['is_enabled' => $rule->is_enabled]
        );

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'rule' => $rule->fresh()]);
        }

        return redirect()->route('workflows.index')
            ->with('status', "Workflow rule '{$rule->name}' ".($rule->is_enabled ? 'enabled' : 'disabled').'.');
    }

    /**
     * Simulate and test rules against draft request data.
     */
    public function simulate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'participant_count' => ['nullable', 'integer', 'min:1'],
            'department_id' => ['nullable', 'integer'],
            'template_id' => ['nullable', 'integer'],
            'security_profile_id' => ['nullable', 'integer'],
            'preferred_pool_id' => ['nullable', 'integer'],
            'meeting_type' => ['nullable', 'string'],
            'recording_mode' => ['nullable', 'string'],
            'external_participants' => ['nullable', 'boolean'],
            'is_instant' => ['nullable', 'boolean'],
        ]);

        $result = $this->ruleEngine->evaluateDraft($data, $user);

        return response()->json([
            'matched_count' => $result->matchedRules->count(),
            'matched_rules' => $result->matchedRules->map(fn ($r) => [
                'name' => $r->name,
                'priority' => $r->priority,
            ]),
            'is_auto_approved' => $result->isAutoApproved,
            'is_rejected' => $result->isRejected,
            'reject_reason' => $result->rejectReason,
            'requires_approval' => $result->requiresApproval,
            'approval_steps' => $result->approvalSteps,
            'override_pool_id' => $result->overridePoolId,
            'override_profile_id' => $result->overrideProfileId,
            'override_recording_mode' => $result->overrideRecordingMode,
            'logs' => $result->logs,
        ]);
    }
}
