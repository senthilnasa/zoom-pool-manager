<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\MeetingNotificationService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingLifecycleService;
use App\Domain\Meetings\Services\MeetingStateMachine;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Scheduling\Services\AllocationEngine;
use App\Domain\Scheduling\Services\EffectivePolicyResolver;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\ApprovalDelegation;
use App\Domain\Workflow\Models\MeetingApproval;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ApprovalWorkflowService
{
    public function __construct(
        protected MeetingStateMachine $stateMachine,
        protected AllocationEngine $allocationEngine,
        protected EffectivePolicyResolver $policyResolver,
        protected QuotaService $quotaService,
        protected AuditService $auditService
    ) {}

    /**
     * Create approval chain records for a meeting.
     *
     * @param  array<int, array<string, mixed>>  $approvalSteps
     * @return Collection<int, MeetingApproval>
     */
    public function createApprovals(Meeting $meeting, array $approvalSteps = []): Collection
    {
        /** @var Collection<int, MeetingApproval> $approvals */
        $approvals = new Collection;

        if (empty($approvalSteps)) {
            // Default 1-step approval by department admin or IT admin
            $approvalSteps = [
                [
                    'step' => 1,
                    'approver_type' => 'dept_admin',
                    'mode' => 'ANY',
                ],
            ];
        }

        foreach ($approvalSteps as $index => $stepConfig) {
            $stepNumber = $stepConfig['step'] ?? ($index + 1);
            $approvers = $this->resolveApproversForStep($meeting, $stepConfig);

            // Mandatory Anti-Self-Approval:
            // Under NO circumstances may the meeting requester or owner approve their own meeting.
            $filteredApprovers = $approvers->reject(function (User $user) use ($meeting) {
                return $user->id === $meeting->requester_user_id || $user->id === $meeting->owner_user_id;
            });

            // If anti-self-approval filtered everyone out, fallback to an IT admin who is not the requester
            if ($filteredApprovers->isEmpty()) {
                $fallbackAdmin = User::whereHas('roles', function ($q) {
                    $q->whereIn('name', ['it_admin', 'super_admin', 'Super Administrator', 'Super Admin', 'Administrator']);
                })
                    ->where('id', '!=', $meeting->requester_user_id)
                    ->where('id', '!=', $meeting->owner_user_id)
                    ->first();

                if ($fallbackAdmin) {
                    $filteredApprovers = collect([$fallbackAdmin]);
                }
            }

            foreach ($filteredApprovers as $approver) {
                $dueAt = $meeting->starts_at->isPast() ? Carbon::now() : Carbon::now()->addHours(24);
                if ($dueAt->isAfter($meeting->starts_at)) {
                    $dueAt = $meeting->starts_at->copy();
                }

                $approval = MeetingApproval::create([
                    'meeting_id' => $meeting->id,
                    'step' => $stepNumber,
                    'approver_user_id' => $approver->id,
                    'decision' => 'pending',
                    'due_at' => $dueAt,
                ]);

                $approvals->push($approval);
            }
        }

        return $approvals;
    }

    /**
     * Process an approval or rejection decision.
     */
    public function decide(
        MeetingApproval $approval,
        User $actor,
        string $decision,
        ?string $notes = null
    ): MeetingApproval {
        $meeting = $approval->meeting;

        // 1. Mandatory Anti-Self-Approval Check
        if ($actor->id === $meeting->requester_user_id || $actor->id === $meeting->owner_user_id) {
            throw new RuntimeException('Anti-Self-Approval violation: You cannot decide on your own meeting request.');
        }

        // 2. Validate Authority (Assigned Approver OR Active Delegate)
        $isAssigned = $approval->approver_user_id === $actor->id;
        $isDelegate = false;

        if (! $isAssigned) {
            $isDelegate = ApprovalDelegation::currentlyValid()
                ->where('user_id', $approval->approver_user_id)
                ->where('delegate_user_id', $actor->id)
                ->exists();

            if (! $isDelegate && ! $actor->hasRole(['super_admin', 'it_admin'])) {
                throw new RuntimeException('Unauthorized: You are not authorized to approve or reject this request.');
            }
        }

        if (! in_array($decision, ['approved', 'rejected'], true)) {
            throw new RuntimeException("Invalid decision [{$decision}]. Allowed: approved, rejected.");
        }

        return DB::transaction(function () use ($approval, $meeting, $actor, $decision, $notes, $isDelegate) {
            $approval->update([
                'decision' => $decision,
                'decision_notes' => $notes,
                'decided_at' => Carbon::now(),
                'delegated_from_user_id' => $isDelegate ? $approval->approver_user_id : null,
            ]);

            $this->auditService->log(
                event: "meeting.approval.{$decision}",
                auditable: $meeting,
                actor: $actor,
                newValues: [
                    'approval_id' => $approval->public_id,
                    'step' => $approval->step,
                    'decision' => $decision,
                    'notes' => $notes,
                    'is_delegate' => $isDelegate,
                ]
            );

            // Handle Rejection
            if ($decision === 'rejected') {
                // Cancel other pending approvals
                MeetingApproval::where('meeting_id', $meeting->id)
                    ->where('decision', 'pending')
                    ->update(['decision' => 'bypassed']);

                // Transition meeting to rejected
                $this->stateMachine->transitionTo(
                    meeting: $meeting,
                    toStatus: 'rejected',
                    actor: $actor,
                    reason: $notes ?? 'Meeting request was rejected during approval review.'
                );

                // Release held reservation if one exists
                $heldReservation = ResourceReservation::where('meeting_id', $meeting->id)
                    ->whereIn('status', ['held', 'confirmed'])
                    ->first();

                if ($heldReservation) {
                    $this->allocationEngine->releaseReservation($heldReservation);
                }

                try {
                    $notificationService = app(MeetingNotificationService::class);
                    $notificationService->notifyMeetingRejected($meeting, $notes ?? 'Meeting request was rejected during review.');
                } catch (\Throwable $e) {
                    Log::warning("Notification dispatch failed for rejected meeting #{$meeting->id}: {$e->getMessage()}");
                }

                return $approval;
            }

            // Handle Approval: Check step completion
            $step = $approval->step;

            // Check remaining pending approvals in this step
            $remainingInStep = MeetingApproval::where('meeting_id', $meeting->id)
                ->where('step', $step)
                ->where('decision', 'pending')
                ->count();

            // Check if there are other steps
            $higherStepsCount = MeetingApproval::where('meeting_id', $meeting->id)
                ->where('step', '>', $step)
                ->count();

            // If this step is completed and there are no higher pending steps
            if ($remainingInStep === 0 && $higherStepsCount === 0) {
                // All steps are approved!
                $this->finalizeMeetingApproval($meeting, $actor);
            }

            return $approval;
        });
    }

    /**
     * Finalize meeting approval by transitioning and scheduling allocation.
     */
    protected function finalizeMeetingApproval(Meeting $meeting, User $actor): void
    {
        $this->stateMachine->transitionTo(
            meeting: $meeting,
            toStatus: 'approved',
            actor: $actor,
            reason: 'All required workflow approvals obtained.'
        );

        // Transition to allocating
        $this->stateMachine->transitionTo(
            meeting: $meeting,
            toStatus: 'allocating',
            actor: $actor,
            reason: 'Initiating resource allocation following approval.'
        );

        // Check if there's already a held reservation
        $held = ResourceReservation::where('meeting_id', $meeting->id)
            ->where('status', 'held')
            ->first();

        if ($held) {
            $this->allocationEngine->confirmReservation($held, $meeting->id);
            $meeting->update(['zoom_resource_id' => $held->resource_id]);
        } else {
            // Allocate resource
            $pool = $meeting->zoomResource?->pools()->first() ?? $meeting->template?->defaultPool;
            $policy = $this->policyResolver->resolve(
                user: $meeting->requester,
                department: $meeting->department,
                securityProfile: $meeting->securityProfile,
                template: $meeting->template
            );

            $reservation = $this->allocationEngine->holdResource(
                startsAt: $meeting->starts_at,
                endsAt: $meeting->ends_at,
                participantCount: $meeting->participant_count,
                policy: $policy,
                pool: $pool,
                meetingId: $meeting->id
            );

            $this->allocationEngine->confirmReservation($reservation, $meeting->id);
            $meeting->update(['zoom_resource_id' => $reservation->resource_id]);
        }

        // Record quota usage
        $this->quotaService->recordUsage($meeting);

        $this->stateMachine->transitionTo(
            meeting: $meeting,
            toStatus: 'scheduled',
            actor: $actor,
            reason: 'Resource confirmed and meeting scheduled.'
        );

        try {
            if (! $meeting->zoom_meeting_id) {
                app(MeetingLifecycleService::class)->provisionZoomDetails($meeting);
                $meeting->save();
            }

            $notificationService = app(MeetingNotificationService::class);
            $notificationService->notifyMeetingApproved($meeting);
            $notificationService->notifyMeetingConfirmed($meeting);
        } catch (\Throwable $e) {
            Log::warning("Notification dispatch failed for approved meeting #{$meeting->id}: {$e->getMessage()}");
        }
    }

    /**
     * Resolve eligible approvers for a step configuration.
     *
     * @param  array<string, mixed>  $stepConfig
     * @return Collection<int, User>
     */
    protected function resolveApproversForStep(Meeting $meeting, array $stepConfig): Collection
    {
        $approverType = $stepConfig['approver_type'] ?? 'dept_admin';

        return match ($approverType) {
            'manager', 'dept_admin' => $this->getDepartmentApprovers($meeting),
            'it_admin' => User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['it_admin', 'super_admin', 'Super Administrator', 'Super Admin', 'Administrator']);
            })->get(),
            'role' => ! empty($stepConfig['approver_role'])
                ? User::whereHas('roles', function ($q) use ($stepConfig) {
                    $roleName = (string) $stepConfig['approver_role'];
                    $candidates = [$roleName];
                    if ($roleName === 'dept_admin') {
                        $candidates[] = 'department_admin';
                        $candidates[] = 'Department Administrator';
                    } elseif ($roleName === 'department_admin') {
                        $candidates[] = 'dept_admin';
                    }
                    $q->whereIn('name', $candidates);
                })->get()
                : User::whereHas('roles', function ($q) {
                    $q->whereIn('name', ['it_admin', 'super_admin', 'Super Administrator', 'Super Admin', 'Administrator']);
                })->get(),
            'specific_users' => ! empty($stepConfig['approver_ids'])
                ? User::whereIn('id', (array) $stepConfig['approver_ids'])->get()
                : new Collection,
            default => $this->getDepartmentApprovers($meeting),
        };
    }

    /**
     * @return Collection<int, User>
     */
    protected function getDepartmentApprovers(Meeting $meeting): Collection
    {
        if ($meeting->department_id) {
            $deptUsers = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['dept_admin', 'department_admin', 'Department Administrator']);
            })
                ->where('department_id', $meeting->department_id)
                ->get();

            if ($deptUsers->isNotEmpty()) {
                return $deptUsers;
            }
        }

        // Fallback to IT admins if department has no dept_admin
        return User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['it_admin', 'super_admin', 'Super Administrator', 'Super Admin', 'Administrator']);
        })->get();
    }

    /**
     * Check for overdue approvals and escalate.
     */
    public function checkTimeoutsAndEscalate(): int
    {
        $overdueApprovals = MeetingApproval::pending()
            ->whereNotNull('due_at')
            ->where('due_at', '<', Carbon::now())
            ->whereNull('escalated_at')
            ->with('meeting')
            ->get();

        $escalatedCount = 0;

        foreach ($overdueApprovals as $approval) {
            // Find IT Admin to escalate to
            $itAdmin = User::whereHas('roles', function ($q) {
                $q->whereIn('name', ['it_admin', 'super_admin', 'Super Administrator', 'Super Admin', 'Administrator']);
            })
                ->where('id', '!=', $approval->meeting->requester_user_id)
                ->first();

            if ($itAdmin) {
                $approval->update([
                    'approver_user_id' => $itAdmin->id,
                    'escalated_at' => Carbon::now(),
                ]);

                $this->auditService->log(
                    event: 'meeting.approval.escalated',
                    auditable: $approval->meeting,
                    newValues: [
                        'approval_id' => $approval->public_id,
                        'escalated_to' => $itAdmin->name,
                    ]
                );

                $escalatedCount++;
            }
        }

        return $escalatedCount;
    }

    /**
     * Check if updated meeting parameters require re-approval.
     *
     * @param  array<string, mixed>  $updatedData
     */
    public function requiresReapproval(Meeting $meeting, array $updatedData): bool
    {
        // 1. Time shift or duration increase
        if (isset($updatedData['starts_at'])) {
            $newStarts = Carbon::parse($updatedData['starts_at']);
            if (! $meeting->starts_at->equalTo($newStarts)) {
                return true;
            }
        }

        if (isset($updatedData['ends_at'])) {
            $newEnds = Carbon::parse($updatedData['ends_at']);
            if (! $meeting->ends_at->equalTo($newEnds)) {
                return true;
            }
        }

        // 2. Participant count increased by more than 20%
        if (isset($updatedData['participant_count'])) {
            $newCount = (int) $updatedData['participant_count'];
            $threshold = (int) ceil($meeting->participant_count * 1.2);
            if ($newCount > $threshold) {
                return true;
            }
        }

        // 3. Security profile changed
        if (isset($updatedData['security_profile_id']) && (int) $updatedData['security_profile_id'] !== $meeting->security_profile_id) {
            return true;
        }

        // 4. Recording mode enabled
        if (isset($updatedData['recording_mode']) && $updatedData['recording_mode'] !== $meeting->recording_mode && $updatedData['recording_mode'] !== 'none') {
            return true;
        }

        // 5. External participants added
        if (! empty($updatedData['external_participants']) && ! $meeting->external_participants) {
            return true;
        }

        return false;
    }

    /**
     * Delegate approval authority.
     */
    public function createDelegation(
        User $user,
        User $delegate,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt
    ): ApprovalDelegation {
        if ($user->id === $delegate->id) {
            throw new RuntimeException('Cannot delegate approval authority to yourself.');
        }

        if ($endsAt->isBefore($startsAt)) {
            throw new RuntimeException('Delegation end date must be after start date.');
        }

        /** @var ApprovalDelegation $delegation */
        $delegation = ApprovalDelegation::create([
            'user_id' => $user->id,
            'delegate_user_id' => $delegate->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => true,
        ]);

        $this->auditService->log(
            event: 'approval.delegation.created',
            auditable: $delegation,
            actor: $user,
            newValues: [
                'delegate_id' => $delegate->id,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ]
        );

        return $delegation;
    }
}
