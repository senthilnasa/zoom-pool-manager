<?php

namespace App\Domain\Meetings\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\MeetingNotificationService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingInvitee;
use App\Domain\Scheduling\DTOs\ConflictCheckResult;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Scheduling\Services\AllocationEngine;
use App\Domain\Scheduling\Services\ConflictDetectionService;
use App\Domain\Scheduling\Services\EffectivePolicyResolver;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Services\ApprovalWorkflowService;
use App\Domain\Workflow\Services\QuotaService;
use App\Domain\Workflow\Services\RuleEvaluationEngine;
use App\Domain\Workflow\Services\WaitlistService;
use App\Domain\Zoom\Models\ResourcePool;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MeetingService
{
    public function __construct(
        protected EffectivePolicyResolver $policyResolver,
        protected ConflictDetectionService $conflictService,
        protected AllocationEngine $allocationEngine,
        protected MeetingStateMachine $stateMachine,
        protected AgendaMarkerService $markerService,
        protected AuditService $auditService,
        protected QuotaService $quotaService,
        protected RuleEvaluationEngine $ruleEngine,
        protected ApprovalWorkflowService $approvalService,
        protected WaitlistService $waitlistService
    ) {}

    /**
     * Preview conflicts before creating or updating a meeting.
     *
     * @param  array<string, mixed>  $requestInput
     */
    public function previewConflicts(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $participantCount,
        ?User $user = null,
        ?MeetingTemplate $template = null,
        ?SecurityProfile $securityProfile = null,
        ?ResourcePool $pool = null,
        array $requestInput = [],
        ?int $ignoreMeetingId = null
    ): ConflictCheckResult {
        $policy = $this->policyResolver->resolve(
            user: $user,
            department: $user?->department,
            securityProfile: $securityProfile,
            template: $template,
            requestInput: $requestInput
        );

        return $this->conflictService->check(
            startsAt: $startsAt,
            endsAt: $endsAt,
            participantCount: $participantCount,
            policy: $policy,
            pool: $pool,
            requester: $user,
            ignoreMeetingId: $ignoreMeetingId
        );
    }

    /**
     * Create a new meeting request and initiate resource allocation.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $inviteeEmails
     */
    public function createMeeting(
        User $requester,
        array $data,
        array $inviteeEmails = []
    ): Meeting {
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = Carbon::parse($data['ends_at']);
        $participantCount = (int) ($data['participant_count'] ?? 10);

        $duration = (int) $startsAt->diffInMinutes($endsAt);

        // 1. Quota Check
        $this->quotaService->checkQuota($requester, $startsAt, $duration);

        // 2. Rule Evaluation
        $ruleResult = $this->ruleEngine->evaluateDraft($data, $requester);
        if ($ruleResult->isRejected) {
            $msg = $ruleResult->rejectReason ?? 'Request rejected by organizational policy.';
            throw new RuntimeException("Cannot book meeting: {$msg}");
        }

        /** @var MeetingTemplate|null $template */
        $template = ! empty($data['template_id']) ? MeetingTemplate::find($data['template_id']) : null;

        /** @var SecurityProfile|null $securityProfile */
        $securityProfile = null;
        if ($ruleResult->overrideProfileId) {
            $securityProfile = SecurityProfile::find($ruleResult->overrideProfileId);
        } elseif (! empty($data['security_profile_id'])) {
            $securityProfile = SecurityProfile::find($data['security_profile_id']);
        } elseif ($template !== null) {
            $securityProfile = $template->securityProfile;
        }
        if (! $securityProfile) {
            $securityProfile = SecurityProfile::where('is_default', true)->first();
        }

        $pool = null;
        if ($ruleResult->overridePoolId) {
            $pool = ResourcePool::find($ruleResult->overridePoolId);
        } elseif (! empty($data['preferred_pool_id'])) {
            $pool = ResourcePool::find($data['preferred_pool_id']);
        } else {
            $pool = $template?->defaultPool;
        }

        // Resolve effective policy
        $resolvedPolicy = $this->policyResolver->resolve(
            user: $requester,
            department: $requester->department,
            securityProfile: $securityProfile,
            template: $template,
            requestInput: $data
        );

        // Verify conflict
        $conflictResult = $this->conflictService->check(
            startsAt: $startsAt,
            endsAt: $endsAt,
            participantCount: $participantCount,
            policy: $resolvedPolicy,
            pool: $pool,
            requester: $requester
        );

        $allowWaitlist = ! empty($data['allow_waitlist']);

        if ($conflictResult->hasConflict && ! $allowWaitlist) {
            $msg = $conflictResult->conflicts[0]['message'] ?? 'Scheduling conflict detected.';
            throw new RuntimeException("Cannot book meeting: {$msg}");
        }

        // Determine if approval is needed
        $requiresApproval = false;
        $approvalSteps = [];

        if (! $ruleResult->isAutoApproved) {
            if ($ruleResult->requiresApproval) {
                $requiresApproval = true;
                $approvalSteps = $ruleResult->approvalSteps;
            } elseif ($template && $template->requires_approval) {
                $requiresApproval = true;
                $approvalSteps = [
                    ['step' => 1, 'approver_type' => 'dept_admin', 'mode' => 'ANY'],
                ];
            }
        }

        $initialStatus = 'allocating';
        if ($conflictResult->hasConflict) {
            $initialStatus = 'waitlisted';
        } elseif ($requiresApproval) {
            $initialStatus = 'pending_approval';
        }

        return DB::transaction(function () use (
            $requester,
            $data,
            $startsAt,
            $endsAt,
            $participantCount,
            $template,
            $securityProfile,
            $resolvedPolicy,
            $pool,
            $initialStatus,
            $inviteeEmails,
            $approvalSteps,
            $ruleResult
        ) {
            $meeting = Meeting::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'meeting_type' => $data['meeting_type'] ?? 'meeting',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $data['timezone'] ?? $requester->timezone ?? 'Asia/Kolkata',
                'participant_count' => $participantCount,
                'requester_user_id' => $requester->id,
                'owner_user_id' => $data['owner_user_id'] ?? $requester->id,
                'department_id' => $requester->department_id,
                'template_id' => $template?->id,
                'security_profile_id' => $securityProfile?->id,
                'ai_companion_policy' => $resolvedPolicy->aiCompanionPolicy,
                'recording_mode' => $ruleResult->overrideRecordingMode ?? $resolvedPolicy->recordingMode,
                'external_participants' => ! empty($data['external_participants']),
                'registration_enabled' => ! empty($data['registration_enabled']),
                'status' => 'draft',
                'source' => $data['source'] ?? 'web',
            ]);

            // Transition to initial status
            $this->stateMachine->transitionTo(
                meeting: $meeting,
                toStatus: $initialStatus,
                actor: $requester,
                reason: 'Initial submission'
            );

            // Handle waitlist
            if ($initialStatus === 'waitlisted') {
                $this->waitlistService->addToWaitlist($meeting);
            }

            // Handle approvals
            if ($initialStatus === 'pending_approval') {
                $this->approvalService->createApprovals($meeting, $approvalSteps);
            }

            // If auto-allocating, hold resource immediately
            if ($initialStatus === 'allocating') {
                $strategy = $pool !== null ? $pool->pool_strategy : 'least_hours_today';

                $reservation = $this->allocationEngine->holdResource(
                    startsAt: $startsAt,
                    endsAt: $endsAt,
                    participantCount: $participantCount,
                    policy: $resolvedPolicy,
                    pool: $pool,
                    meetingId: $meeting->id,
                    strategy: $strategy
                );

                $meeting->zoom_resource_id = $reservation->resource_id;
                $meeting->save();

                $this->quotaService->recordUsage($meeting);
            }

            // Record invitees
            foreach ($inviteeEmails as $email) {
                $email = trim($email);
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $internalUser = User::where('email', $email)->first();
                    MeetingInvitee::create([
                        'meeting_id' => $meeting->id,
                        'user_id' => $internalUser?->id,
                        'email' => $email,
                        'name' => $internalUser?->name,
                        'status' => 'pending',
                    ]);
                }
            }

            $this->auditService->log('meeting.created', $meeting, null, [
                'title' => $meeting->title,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ], $requester);

            // Dispatch communication notifications
            try {
                $notificationService = app(MeetingNotificationService::class);
                if ($initialStatus === 'allocating') {
                    $notificationService->notifyMeetingConfirmed($meeting);
                } elseif ($initialStatus === 'waitlisted') {
                    $notificationService->notifyMeetingWaitlisted($meeting);
                }
            } catch (\Throwable $e) {
                Log::warning("Notification dispatch failed: {$e->getMessage()}");
            }

            return $meeting;
        });
    }

    /**
     * Cancel an existing meeting and release its allocated resource.
     */
    public function cancelMeeting(Meeting $meeting, User $actor, string $reason): Meeting
    {
        return DB::transaction(function () use ($meeting, $actor, $reason) {
            $this->stateMachine->transitionTo($meeting, 'cancelled', $actor, $reason);

            // Release any reservation
            if ($meeting->zoom_resource_id) {
                $reservation = $meeting->zoomResource?->reservations()
                    ->where('meeting_id', $meeting->id)
                    ->whereIn('status', ['held', 'confirmed'])
                    ->first();

                if ($reservation) {
                    $this->allocationEngine->releaseReservation($reservation);
                }

                $this->quotaService->releaseUsage($meeting);
            }

            $this->auditService->log('meeting.cancelled', $meeting, null, ['reason' => $reason], $actor);

            // Trigger waitlist check to see if an awaiting meeting can now claim a spot
            $this->waitlistService->allocateNextEligible();

            // Dispatch cancellation notification
            try {
                app(MeetingNotificationService::class)->notifyMeetingCancelled($meeting, $reason);
            } catch (\Throwable $e) {
                Log::warning("Cancellation notification dispatch failed: {$e->getMessage()}");
            }

            return $meeting;
        });
    }
}
