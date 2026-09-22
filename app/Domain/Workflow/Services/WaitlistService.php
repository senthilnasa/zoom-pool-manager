<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\MeetingNotificationService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingStateMachine;
use App\Domain\Scheduling\Services\AllocationEngine;
use App\Domain\Scheduling\Services\EffectivePolicyResolver;
use App\Domain\Workflow\Models\WaitlistEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class WaitlistService
{
    public function __construct(
        protected MeetingStateMachine $stateMachine,
        protected AuditService $auditService,
        protected AllocationEngine $allocationEngine
    ) {}

    /**
     * Add a meeting to the waitlist.
     */
    public function addToWaitlist(Meeting $meeting, int $priority = 100): WaitlistEntry
    {
        if ($meeting->status !== 'waitlisted') {
            $this->stateMachine->transitionTo(
                meeting: $meeting,
                toStatus: 'waitlisted',
                actor: $meeting->requester,
                reason: 'No pool resource available. Placed on waitlist.'
            );
        }

        /** @var WaitlistEntry $entry */
        $entry = WaitlistEntry::create([
            'meeting_id' => $meeting->id,
            'priority' => $priority,
            'status' => 'waiting',
        ]);

        $this->auditService->log(
            event: 'meeting.waitlisted',
            auditable: $meeting,
            actor: $meeting->requester,
            newValues: [
                'priority' => $priority,
                'waitlist_id' => $entry->public_id,
            ]
        );

        return $entry;
    }

    /**
     * Attempt to allocate the next eligible waitlisted meeting.
     */
    public function allocateNextEligible(): ?Meeting
    {
        $entries = WaitlistEntry::waiting()
            ->orderedByPriority()
            ->with('meeting')
            ->get();

        foreach ($entries as $entry) {
            $meeting = $entry->meeting;

            // If meeting start time is already in the past, expire it
            if ($meeting->starts_at->isPast()) {
                $entry->update(['status' => 'expired']);
                $this->stateMachine->transitionTo(
                    meeting: $meeting,
                    toStatus: 'cancelled',
                    reason: 'Waitlist expired without available resource.'
                );

                continue;
            }

            try {
                // Transition to allocating
                $this->stateMachine->transitionTo(
                    meeting: $meeting,
                    toStatus: 'allocating',
                    reason: 'Resource freed; attempting allocation from waitlist.'
                );

                $pool = $meeting->zoomResource?->pools()->first() ?? $meeting->template?->defaultPool;
                $policy = app(EffectivePolicyResolver::class)->resolve(
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

                $meeting->update([
                    'zoom_resource_id' => $reservation->resource_id,
                ]);

                $this->stateMachine->transitionTo(
                    meeting: $meeting,
                    toStatus: 'scheduled',
                    reason: 'Successfully allocated from waitlist.'
                );

                $entry->update([
                    'status' => 'allocated',
                    'allocated_at' => Carbon::now(),
                ]);

                $this->auditService->log(
                    event: 'meeting.waitlist_allocated',
                    auditable: $meeting,
                    newValues: [
                        'resource_id' => $reservation->resource_id,
                        'waitlist_id' => $entry->public_id,
                    ]
                );

                try {
                    app(MeetingNotificationService::class)->notifyWaitlistAllocated($meeting);
                } catch (\Throwable $e) {
                    Log::warning("Waitlist allocated notification failed: {$e->getMessage()}");
                }

                return $meeting;
            } catch (\Throwable $e) {
                // Could not allocate this entry yet; leave waitlisted or revert
                if ($meeting->status === 'allocating') {
                    $this->stateMachine->transitionTo(
                        meeting: $meeting,
                        toStatus: 'waitlisted',
                        reason: 'Slot still unavailable: '.$e->getMessage()
                    );
                }
            }
        }

        return null;
    }

    /**
     * Cancel a waitlist entry.
     */
    public function cancelWaitlist(WaitlistEntry $entry, ?string $reason = null): void
    {
        $entry->update(['status' => 'cancelled']);

        $meeting = $entry->meeting;
        if ($meeting->status === 'waitlisted') {
            $this->stateMachine->transitionTo(
                meeting: $meeting,
                toStatus: 'cancelled',
                reason: $reason ?? 'Waitlist entry cancelled.'
            );
        }
    }
}
