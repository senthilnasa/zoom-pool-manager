<?php

namespace App\Domain\Meetings\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\MeetingNotificationService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingInvitee;
use App\Domain\Scheduling\DTOs\ConflictCheckResult;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Scheduling\Services\AllocationEngine;
use App\Domain\Scheduling\Services\ConflictDetectionService;
use App\Domain\Scheduling\Services\EffectivePolicyResolver;
use App\Domain\Settings\Models\Setting;
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
            } else {
                $orgRequiresApproval = (bool) Setting::get('org.require_meeting_approval', true);
                $canBypassApproval = $requester->can('meeting.approve')
                    || $requester->can('meeting.override')
                    || $requester->hasRole('super_admin')
                    || $requester->hasRole('Super Administrator')
                    || $requester->hasRole('Administrator')
                    || $requester->hasRole('it_admin');

                if ($orgRequiresApproval && ! $canBypassApproval) {
                    $requiresApproval = true;
                    $approvalSteps = [
                        ['step' => 1, 'approver_type' => 'dept_admin', 'mode' => 'ANY'],
                    ];
                }
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
            $ownerId = (! empty($data['owner_user_id'])) ? (int) $data['owner_user_id'] : $requester->id;
            $owner = ($ownerId === $requester->id) ? $requester : (User::find($ownerId) ?? $requester);

            $meeting = Meeting::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'meeting_type' => $data['meeting_type'] ?? 'meeting',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => $data['timezone'] ?? $owner->timezone ?? $requester->timezone ?? 'Asia/Kolkata',
                'participant_count' => $participantCount,
                'requester_user_id' => $requester->id,
                'owner_user_id' => $owner->id,
                'department_id' => $owner->department_id ?? $requester->department_id,
                'template_id' => $template?->id,
                'security_profile_id' => $securityProfile?->id,
                'ai_companion_policy' => $resolvedPolicy->aiCompanionPolicy,
                'recording_mode' => $ruleResult->overrideRecordingMode ?? $data['recording_mode'] ?? $resolvedPolicy->recordingMode,
                'waiting_room' => isset($data['waiting_room']) ? (bool) $data['waiting_room'] : (bool) ($securityProfile?->settings['waiting_room'] ?? true),
                'join_before_host' => isset($data['join_before_host']) ? (bool) $data['join_before_host'] : false,
                'jbh_time' => isset($data['jbh_time']) ? (int) $data['jbh_time'] : 0,
                'attendance_tracking' => isset($data['attendance_tracking']) ? (bool) $data['attendance_tracking'] : true,
                'share_host_key' => isset($data['share_host_key']) ? (bool) $data['share_host_key'] : false,
                'external_participants' => ! empty($data['external_participants']),
                'registration_enabled' => ! empty($data['registration_enabled']),
                'passcode' => $data['passcode'] ?? null,
                'custom_fields' => $data['custom_fields'] ?? null,
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
                $approvals = $this->approvalService->createApprovals($meeting, $approvalSteps);
                try {
                    $approverIds = $approvals->pluck('approver_user_id')->filter()->unique();
                    $approvers = User::whereIn('id', $approverIds)->get();
                    $notificationService = app(MeetingNotificationService::class);
                    $notificationService->notifyMeetingRequested($meeting, $approvers);
                } catch (\Throwable $e) {
                    Log::warning("Notification dispatch failed for pending approval meeting #{$meeting->id}: {$e->getMessage()}");
                }
            }

            // If auto-allocating, hold and confirm resource immediately
            if ($initialStatus === 'allocating') {
                $strategy = $pool !== null ? $pool->pool_strategy : 'least_hours_today';

                try {
                    $reservation = $this->allocationEngine->holdResource(
                        startsAt: $startsAt,
                        endsAt: $endsAt,
                        participantCount: $participantCount,
                        policy: $resolvedPolicy,
                        pool: $pool,
                        meetingId: $meeting->id,
                        strategy: $strategy
                    );

                    $this->allocationEngine->confirmReservation($reservation, $meeting->id);
                    $meeting->zoom_resource_id = $reservation->resource_id;

                    // Provision Zoom details (Live S2S or deterministic mock)
                    app(MeetingLifecycleService::class)->provisionZoomDetails($meeting);
                    $meeting->save();

                    $this->quotaService->recordUsage($meeting);

                    // Transition from allocating to scheduled
                    $this->stateMachine->transitionTo(
                        meeting: $meeting,
                        toStatus: 'scheduled',
                        actor: $requester,
                        reason: 'Resource reservation confirmed and Zoom meeting provisioned.'
                    );
                } catch (\Throwable $e) {
                    Log::warning("Allocation failed for meeting #{$meeting->id}: {$e->getMessage()}");
                    if ($this->stateMachine->canTransitionTo($meeting, 'failed')) {
                        $this->stateMachine->transitionTo($meeting, 'failed', $requester, $e->getMessage());
                    }
                }
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
                if (in_array($meeting->status, ['scheduled', 'allocating'])) {
                    $notificationService->notifyMeetingConfirmed($meeting);
                } elseif ($meeting->status === 'waitlisted') {
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

    /**
     * Update/reschedule an existing meeting.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $inviteeEmails
     */
    public function updateMeeting(
        Meeting $meeting,
        User $actor,
        array $data,
        array $inviteeEmails = []
    ): Meeting {
        $canEdit = $actor->id === $meeting->owner_user_id
            || $actor->id === $meeting->requester_user_id
            || $actor->hasRole('Super Administrator')
            || $actor->hasRole('Administrator')
            || $actor->hasRole('super_admin');

        if (! $canEdit) {
            throw new RuntimeException('You are not authorized to edit this meeting.');
        }

        if (in_array($meeting->status, ['completed', 'cancelled', 'failed'])) {
            throw new RuntimeException("Cannot edit a meeting in '{$meeting->status}' status.");
        }

        return DB::transaction(function () use ($meeting, $actor, $data, $inviteeEmails) {
            $startsAt = ! empty($data['starts_at']) ? Carbon::parse($data['starts_at']) : $meeting->starts_at;
            $endsAt = ! empty($data['ends_at']) ? Carbon::parse($data['ends_at']) : $meeting->ends_at;
            $timeChanged = ! $startsAt->equalTo($meeting->starts_at) || ! $endsAt->equalTo($meeting->ends_at);

            if ($timeChanged && $meeting->status === 'scheduled' && $meeting->zoom_resource_id) {
                $buffer = $meeting->buffer_minutes ?: 10;
                $occupiedFrom = $startsAt;
                $occupiedUntil = (clone $endsAt)->addMinutes($buffer);

                $conflict = ResourceReservation::where('resource_id', $meeting->zoom_resource_id)
                    ->where('meeting_id', '!=', $meeting->id)
                    ->whereIn('status', ['held', 'confirmed'])
                    ->where('occupied_from', '<', $occupiedUntil)
                    ->where('occupied_until', '>', $occupiedFrom)
                    ->exists();

                if ($conflict) {
                    throw new RuntimeException('Cannot reschedule meeting: The allocated Zoom resource has a conflict in the requested time window.');
                }

                ResourceReservation::where('meeting_id', $meeting->id)
                    ->whereIn('status', ['held', 'confirmed'])
                    ->update([
                        'occupied_from' => $occupiedFrom,
                        'occupied_until' => $occupiedUntil,
                    ]);
            }

            $meeting->title = $data['title'] ?? $meeting->title;
            if (array_key_exists('description', $data)) {
                $meeting->description = $data['description'];
            }
            $meeting->starts_at = $startsAt;
            $meeting->ends_at = $endsAt;

            if (isset($data['waiting_room'])) {
                $meeting->waiting_room = (bool) $data['waiting_room'];
            }
            if (isset($data['join_before_host'])) {
                $meeting->join_before_host = (bool) $data['join_before_host'];
            }
            if (isset($data['jbh_time'])) {
                $meeting->jbh_time = (int) $data['jbh_time'];
            }
            if (isset($data['recording_mode'])) {
                $meeting->recording_mode = (string) $data['recording_mode'];
            }
            if (isset($data['attendance_tracking'])) {
                $meeting->attendance_tracking = (bool) $data['attendance_tracking'];
            }
            if (isset($data['share_host_key'])) {
                $meeting->share_host_key = (bool) $data['share_host_key'];
            }
            if (! empty($data['passcode'])) {
                $meeting->passcode = (string) $data['passcode'];
            }
            if (array_key_exists('custom_fields', $data)) {
                $meeting->custom_fields = $data['custom_fields'];
            }

            $meeting->save();

            // Sync invitees if provided
            if (! empty($inviteeEmails)) {
                foreach ($inviteeEmails as $email) {
                    $email = trim($email);
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $exists = MeetingInvitee::where('meeting_id', $meeting->id)->where('email', $email)->exists();
                        if (! $exists) {
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
                }
            }

            // Sync changes to Zoom
            app(MeetingLifecycleService::class)->updateZoomMeeting($meeting);

            $this->auditService->log('meeting.updated', $meeting, null, [
                'title' => $meeting->title,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ], $actor);

            return $meeting;
        });
    }

    /**
     * Extend an active or scheduled meeting by additional minutes.
     */
    public function extendMeeting(Meeting $meeting, User $actor, int $minutes = 15): Meeting
    {
        $canExtend = $actor->id === $meeting->owner_user_id
            || $actor->id === $meeting->requester_user_id
            || $actor->hasRole('Super Administrator')
            || $actor->hasRole('Administrator')
            || $actor->hasRole('super_admin');

        if (! $canExtend) {
            throw new RuntimeException('You are not authorized to extend this meeting.');
        }

        if (! in_array($meeting->status, ['started', 'scheduled'])) {
            throw new RuntimeException("Cannot extend a meeting with status '{$meeting->status}'.");
        }

        $minutes = max(5, min(120, $minutes));
        $newEndsAt = (clone $meeting->ends_at)->addMinutes($minutes);
        $buffer = $meeting->buffer_minutes ?: 10;
        $newOccupiedUntil = (clone $newEndsAt)->addMinutes($buffer);

        return DB::transaction(function () use ($meeting, $actor, $minutes, $newEndsAt, $newOccupiedUntil) {
            if ($meeting->zoom_resource_id) {
                $conflict = ResourceReservation::where('resource_id', $meeting->zoom_resource_id)
                    ->where('meeting_id', '!=', $meeting->id)
                    ->whereIn('status', ['held', 'confirmed'])
                    ->where('occupied_from', '<', $newOccupiedUntil)
                    ->where('occupied_until', '>', $meeting->ends_at)
                    ->exists();

                if ($conflict) {
                    throw new RuntimeException('Cannot extend meeting: The assigned Zoom host license is reserved for another upcoming meeting.');
                }

                ResourceReservation::where('meeting_id', $meeting->id)
                    ->whereIn('status', ['held', 'confirmed'])
                    ->update(['occupied_until' => $newOccupiedUntil]);
            }

            $meeting->ends_at = $newEndsAt;
            $meeting->save();

            // Patch Zoom API
            app(MeetingLifecycleService::class)->updateZoomMeeting($meeting);

            $this->auditService->log('meeting.extended', $meeting, null, [
                'added_minutes' => $minutes,
                'new_ends_at' => $newEndsAt->toIso8601String(),
            ], $actor);

            return $meeting;
        });
    }
}
