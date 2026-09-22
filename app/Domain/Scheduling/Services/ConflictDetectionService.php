<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\DTOs\ConflictCheckResult;
use App\Domain\Scheduling\DTOs\ResolvedPolicyDto;
use App\Domain\Scheduling\Models\BlackoutPeriod;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomResource;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ConflictDetectionService
{
    /**
     * Check for scheduling conflicts against policies, blackout periods, and resource occupancy.
     */
    public function check(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $participantCount,
        ResolvedPolicyDto $policy,
        ?ResourcePool $pool = null,
        ?ZoomResource $specificResource = null,
        ?User $requester = null,
        ?int $ignoreMeetingId = null
    ): ConflictCheckResult {
        $conflicts = [];
        $now = Carbon::now();

        // 1. Minimum Notice Check
        $minNoticeCutoff = (clone $now)->addHours($policy->minNoticeHours);
        if ($startsAt->isBefore($minNoticeCutoff)) {
            $conflicts[] = [
                'type' => 'notice_violation',
                'message' => "Bookings require at least {$policy->minNoticeHours} hours advance notice.",
                'details' => [
                    'min_notice_hours' => $policy->minNoticeHours,
                    'earliest_allowed' => $minNoticeCutoff->toIso8601String(),
                ],
            ];
        }

        // 2. Maximum Advance Booking Check
        $maxAdvanceCutoff = (clone $now)->addDays($policy->maxAdvanceDays);
        if ($startsAt->isAfter($maxAdvanceCutoff)) {
            $conflicts[] = [
                'type' => 'advance_booking_violation',
                'message' => "Bookings cannot be scheduled more than {$policy->maxAdvanceDays} days in advance.",
                'details' => [
                    'max_advance_days' => $policy->maxAdvanceDays,
                    'latest_allowed' => $maxAdvanceCutoff->toIso8601String(),
                ],
            ];
        }

        // 3. Duration Limit Check
        $durationMinutes = $startsAt->diffInMinutes($endsAt);
        if ($durationMinutes > $policy->maxDurationMinutes) {
            $conflicts[] = [
                'type' => 'duration_violation',
                'message' => "Meeting duration of {$durationMinutes} minutes exceeds the maximum permitted duration of {$policy->maxDurationMinutes} minutes.",
                'details' => [
                    'requested_duration' => $durationMinutes,
                    'max_duration' => $policy->maxDurationMinutes,
                ],
            ];
        }

        // 4. Blackout Periods Check
        $blackouts = BlackoutPeriod::where(function ($query) use ($requester) {
            $query->whereNull('department_id');
            if ($requester?->department_id) {
                $query->orWhere('department_id', $requester->department_id);
            }
        })
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->get();

        foreach ($blackouts as $blackout) {
            $conflicts[] = [
                'type' => 'blackout_conflict',
                'message' => "Requested time overlaps with blackout period: [{$blackout->name}] ({$blackout->reason}).",
                'details' => [
                    'blackout_id' => $blackout->id,
                    'name' => $blackout->name,
                    'type' => $blackout->type,
                    'starts_at' => $blackout->starts_at->toIso8601String(),
                    'ends_at' => $blackout->ends_at->toIso8601String(),
                ],
            ];
        }

        // 5. Resource Availability and Buffer Window Check
        // Single source of truth: resource_reservations occupied_from and occupied_until
        $occupiedFrom = clone $startsAt;
        $occupiedUntil = (clone $endsAt)->addMinutes($policy->bufferMinutes);

        // Candidate resources query
        $resourceQuery = ZoomResource::query()
            ->where('managed', true)
            ->where('status', 'active')
            ->where('participant_capacity', '>=', $participantCount);

        if ($specificResource) {
            $resourceQuery->where('id', $specificResource->id);
        } elseif ($pool) {
            $resourceQuery->whereHas('pools', function ($query) use ($pool) {
                $query->where('resource_pools.id', $pool->id);
            });
        }

        $candidateResources = $resourceQuery->get();

        if ($candidateResources->isEmpty()) {
            $conflicts[] = [
                'type' => 'capacity_mismatch',
                'message' => 'No active managed Zoom resource is configured with sufficient participant capacity.',
                'details' => [
                    'requested_capacity' => $participantCount,
                ],
            ];

            return new ConflictCheckResult(
                hasConflict: true,
                conflicts: $conflicts,
                availableResourceCount: 0,
            );
        }

        // Check each candidate for existing reservations
        $availableCount = 0;
        foreach ($candidateResources as $resource) {
            $hasOverlap = ResourceReservation::where('resource_id', $resource->id)
                ->where(function ($query) {
                    $query->where('status', 'confirmed')
                        ->orWhere(function ($q2) {
                            $q2->where('status', 'held')
                                ->where('hold_expires_at', '>', now());
                        });
                })
                ->when($ignoreMeetingId, function ($q, $ignoreId) {
                    $q->where(function ($sq) use ($ignoreId) {
                        $sq->whereNull('meeting_id')
                            ->orWhere('meeting_id', '!=', $ignoreId);
                    });
                })
                ->where('occupied_from', '<', $occupiedUntil)
                ->where('occupied_until', '>', $occupiedFrom)
                ->exists();

            if (! $hasOverlap) {
                $availableCount++;
            }
        }

        if ($availableCount === 0) {
            $conflicts[] = [
                'type' => 'resource_overlap',
                'message' => 'All eligible pooled Zoom resources are currently booked or reserved for this time slot (including buffer).',
                'details' => [
                    'occupied_from' => $occupiedFrom->toIso8601String(),
                    'occupied_until' => $occupiedUntil->toIso8601String(),
                    'candidate_count' => $candidateResources->count(),
                ],
            ];
        }

        return new ConflictCheckResult(
            hasConflict: count($conflicts) > 0,
            conflicts: $conflicts,
            availableResourceCount: $availableCount,
        );
    }
}
