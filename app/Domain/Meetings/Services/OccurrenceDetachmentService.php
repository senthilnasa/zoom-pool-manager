<?php

namespace App\Domain\Meetings\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingSeries;
use App\Domain\Scheduling\Services\AllocationEngine;
use App\Domain\Scheduling\Services\ConflictDetectionService;
use App\Domain\Scheduling\Services\EffectivePolicyResolver;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomResource;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OccurrenceDetachmentService
{
    public function __construct(
        protected AllocationEngine $allocationEngine,
        protected ConflictDetectionService $conflictService,
        protected EffectivePolicyResolver $policyResolver,
        protected AuditService $auditService
    ) {}

    /**
     * Detach a single occurrence from its parent series and optionally reallocate.
     */
    public function detachOccurrence(
        Meeting $meeting,
        User $actor,
        ?CarbonInterface $newStartsAt = null,
        ?CarbonInterface $newEndsAt = null,
        ?ZoomResource $newResource = null,
        string $joinLinkPolicy = 'BLOCK_AFTER_NOTIFICATION'
    ): Meeting {
        if (! $meeting->series_id) {
            throw new RuntimeException('This meeting is not part of a recurring series.');
        }

        if ($meeting->is_detached_from_series) {
            throw new RuntimeException('This occurrence has already been detached from the series.');
        }

        return DB::transaction(function () use (
            $meeting,
            $actor,
            $newStartsAt,
            $newEndsAt,
            $newResource,
            $joinLinkPolicy
        ) {
            $oldResourceId = $meeting->zoom_resource_id;
            $oldStartsAt = $meeting->starts_at;
            $oldEndsAt = $meeting->ends_at;

            $meeting->is_detached_from_series = true;

            // If new times provided, update
            if ($newStartsAt && $newEndsAt) {
                $meeting->starts_at = Carbon::instance($newStartsAt);
                $meeting->ends_at = Carbon::instance($newEndsAt);
            }

            // If a different resource was specified
            if ($newResource && $newResource->id !== $oldResourceId) {
                // Release previous reservation
                if ($oldResourceId) {
                    $oldReservation = $meeting->zoomResource?->reservations()
                        ->where('meeting_id', $meeting->id)
                        ->first();
                    if ($oldReservation) {
                        $this->allocationEngine->releaseReservation($oldReservation);
                    }
                }

                $policy = $this->policyResolver->resolve(
                    user: $meeting->owner,
                    department: $meeting->department,
                    securityProfile: $meeting->securityProfile,
                    template: $meeting->template
                );

                // Reserve new resource
                $this->allocationEngine->holdResource(
                    startsAt: $meeting->starts_at,
                    endsAt: $meeting->ends_at,
                    participantCount: $meeting->participant_count,
                    policy: $policy,
                    preferredResource: $newResource,
                    meetingId: $meeting->id
                );

                $meeting->zoom_resource_id = $newResource->id;

                // Handle join link policy
                if ($joinLinkPolicy === 'NEW_LINK') {
                    $meeting->join_url = null;
                    $meeting->zoom_meeting_id = null;
                }
            }

            $meeting->save();

            $this->auditService->log('meeting.occurrence_detached', $meeting, [
                'series_id' => $meeting->series_id,
                'is_detached' => false,
            ], [
                'is_detached' => true,
                'old_resource_id' => $oldResourceId,
                'new_resource_id' => $meeting->zoom_resource_id,
                'join_link_policy' => $joinLinkPolicy,
            ], $actor);

            return $meeting;
        });
    }

    /**
     * Cancel the entire recurring series and all future occurrences.
     */
    public function cancelSeries(MeetingSeries $series, User $actor, string $reason): MeetingSeries
    {
        return DB::transaction(function () use ($series, $actor, $reason) {
            $series->status = 'cancelled';
            $series->save();

            // Cancel all future meetings
            $now = Carbon::now();
            $futureMeetings = $series->meetings()
                ->where('ends_at', '>=', $now)
                ->whereNotIn('status', ['cancelled', 'completed', 'ended'])
                ->get();

            foreach ($futureMeetings as $m) {
                $m->status = 'cancelled';
                $m->cancelled_reason = "Series cancelled: {$reason}";
                $m->save();

                // Release reservation
                if ($m->zoom_resource_id) {
                    $reservation = $m->zoomResource?->reservations()
                        ->where('meeting_id', $m->id)
                        ->whereIn('status', ['held', 'confirmed'])
                        ->first();
                    if ($reservation) {
                        $this->allocationEngine->releaseReservation($reservation);
                    }
                }
            }

            $this->auditService->log('series.cancelled', $series, null, [
                'reason' => $reason,
                'future_meetings_cancelled' => $futureMeetings->count(),
            ], $actor);

            return $series;
        });
    }
}
