<?php

namespace App\Domain\Meetings\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingStatusHistory;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MeetingStateMachine
{
    /**
     * Strict permitted state transitions.
     *
     * @var array<string, array<int, string>>
     */
    protected const ALLOWED_TRANSITIONS = [
        'draft' => ['pending_approval', 'approved', 'allocating', 'waitlisted', 'rejected', 'cancelled'],
        'pending_approval' => ['approved', 'rejected', 'cancelled'],
        'approved' => ['allocating', 'cancelled'],
        'allocating' => ['scheduled', 'failed', 'waitlisted', 'cancelled'],
        'waitlisted' => ['allocating', 'cancelled'],
        'failed' => ['allocating', 'cancelled'],
        'scheduled' => ['started', 'allocating', 'cancelled'],
        'started' => ['ended', 'cancelled'],
        'ended' => ['recording_processing', 'completed'],
        'recording_processing' => ['completed'],
        'completed' => [],
        'cancelled' => [],
        'rejected' => [],
    ];

    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Check if a transition from current meeting status to target status is valid.
     */
    public function canTransitionTo(Meeting $meeting, string $toStatus): bool
    {
        $current = $meeting->status ?: 'draft';
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        return in_array($toStatus, $allowed, true);
    }

    /**
     * Execute a state transition and persist historical audit records.
     */
    public function transitionTo(
        Meeting $meeting,
        string $toStatus,
        ?User $actor = null,
        ?string $reason = null
    ): Meeting {
        $fromStatus = $meeting->status ?: 'draft';

        if (! $this->canTransitionTo($meeting, $toStatus)) {
            throw new RuntimeException("Illegal state transition from [{$fromStatus}] to [{$toStatus}].");
        }

        return DB::transaction(function () use ($meeting, $fromStatus, $toStatus, $actor, $reason) {
            MeetingStatusHistory::create([
                'meeting_id' => $meeting->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'actor_user_id' => $actor ? $actor->id : null,
                'reason' => $reason,
                'created_at' => now(),
            ]);

            $meeting->status = $toStatus;
            if ($toStatus === 'cancelled') {
                $meeting->cancelled_reason = $reason;
            }
            $meeting->save();

            $this->auditService->log('meeting.status_transition', $meeting, [
                'status' => $fromStatus,
            ], [
                'status' => $toStatus,
                'reason' => $reason,
            ], $actor);

            return $meeting;
        });
    }
}
