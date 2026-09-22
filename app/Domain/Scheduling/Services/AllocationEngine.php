<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\DTOs\ResolvedPolicyDto;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomResource;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AllocationEngine
{
    public function __construct(
        protected ConflictDetectionService $conflictService
    ) {}

    /**
     * Concurrency-safe atomic resource allocation and holding.
     * Locks candidate resources with SELECT ... FOR UPDATE ordered strictly by ID to eliminate deadlocks.
     */
    public function holdResource(
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        int $participantCount,
        ResolvedPolicyDto $policy,
        ?ResourcePool $pool = null,
        ?ZoomResource $preferredResource = null,
        ?int $meetingId = null,
        string $strategy = 'least_hours_today'
    ): ResourceReservation {
        $conflictResult = $this->conflictService->check(
            startsAt: $startsAt,
            endsAt: $endsAt,
            participantCount: $participantCount,
            policy: $policy,
            pool: $pool,
            specificResource: $preferredResource,
            ignoreMeetingId: $meetingId
        );

        if ($conflictResult->hasConflict) {
            $firstError = $conflictResult->conflicts[0]['message'] ?? 'Scheduling conflict detected.';
            throw new RuntimeException("Allocation rejected: {$firstError}");
        }

        $occupiedFrom = clone $startsAt;
        $occupiedUntil = (clone $endsAt)->addMinutes($policy->bufferMinutes);

        // Atomic Transaction: strictly order candidate resources by ID before locking
        return DB::transaction(function () use (
            $occupiedFrom,
            $occupiedUntil,
            $participantCount,
            $pool,
            $preferredResource,
            $meetingId,
            $strategy
        ) {
            // Find eligible candidate resource IDs
            $query = ZoomResource::query()
                ->where('managed', true)
                ->where('status', 'active')
                ->where('participant_capacity', '>=', $participantCount);

            if ($preferredResource) {
                $query->where('id', $preferredResource->id);
            } elseif ($pool) {
                $query->whereHas('pools', function ($q) use ($pool) {
                    $q->where('resource_pools.id', $pool->id);
                });
            }

            $candidateIds = $query->pluck('id')->all();

            if (empty($candidateIds)) {
                throw new RuntimeException('No active managed resources available matching required capabilities.');
            }

            // CRITICAL DEADLOCK PREVENTION: Always lock resources ordered strictly by id
            $lockedCandidates = ZoomResource::whereIn('id', $candidateIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get();

            // Filter out resources with active overlapping reservations
            $now = Carbon::now();
            $availableResources = [];

            foreach ($lockedCandidates as $resource) {
                $hasOverlap = ResourceReservation::where('resource_id', $resource->id)
                    ->where(function ($q) use ($now) {
                        $q->where('status', 'confirmed')
                            ->orWhere(function ($q2) use ($now) {
                                $q2->where('status', 'held')
                                    ->where('hold_expires_at', '>', $now);
                            });
                    })
                    ->when($meetingId, function ($q, $mId) {
                        $q->where(function ($sq) use ($mId) {
                            $sq->whereNull('meeting_id')
                                ->orWhere('meeting_id', '!=', $mId);
                        });
                    })
                    ->where('occupied_from', '<', $occupiedUntil)
                    ->where('occupied_until', '>', $occupiedFrom)
                    ->exists();

                if (! $hasOverlap) {
                    $availableResources[] = $resource;
                }
            }

            if (empty($availableResources)) {
                throw new RuntimeException('Concurrency conflict: No resources available for the requested window.');
            }

            // Apply Strategy Selection among free candidates
            $selectedResource = $this->selectResourceByStrategy($availableResources, $strategy, $occupiedFrom);

            // Create Held Reservation with 10-minute hold window
            return ResourceReservation::create([
                'resource_id' => $selectedResource->id,
                'meeting_id' => $meetingId,
                'source' => 'zpm',
                'occupied_from' => $occupiedFrom,
                'occupied_until' => $occupiedUntil,
                'status' => 'held',
                'hold_expires_at' => (clone $now)->addMinutes(10),
            ]);
        });
    }

    /**
     * Transition held reservation to confirmed once meeting is successfully scheduled.
     */
    public function confirmReservation(ResourceReservation $reservation, int $meetingId): void
    {
        $reservation->update([
            'meeting_id' => $meetingId,
            'status' => 'confirmed',
            'hold_expires_at' => null,
        ]);
    }

    /**
     * Release reservation back to pool.
     */
    public function releaseReservation(ResourceReservation $reservation): void
    {
        $reservation->update([
            'status' => 'released',
        ]);
    }

    /**
     * Release expired holds (older than 10 minutes).
     */
    public function releaseExpiredHolds(): int
    {
        return ResourceReservation::where('status', 'held')
            ->where('hold_expires_at', '<=', now())
            ->update([
                'status' => 'released',
            ]);
    }

    /**
     * Select the best candidate based on the configured pool strategy.
     *
     * @param  array<int, ZoomResource>  $resources
     */
    protected function selectResourceByStrategy(
        array $resources,
        string $strategy,
        CarbonInterface $forDate
    ): ZoomResource {
        if (count($resources) === 1) {
            return $resources[0];
        }

        $startOfDay = (clone $forDate)->startOfDay();
        $endOfDay = (clone $forDate)->endOfDay();

        return match ($strategy) {
            'least_meetings_today' => $this->pickLeastMeetings($resources, $startOfDay, $endOfDay),
            'priority' => $this->pickByPriority($resources),
            'random' => $resources[array_rand($resources)],
            default => $this->pickLeastHours($resources, $startOfDay, $endOfDay), // 'least_hours_today' default
        };
    }

    /**
     * Pick resource with the lowest total occupied hours today.
     *
     * @param  array<int, ZoomResource>  $resources
     */
    protected function pickLeastHours(
        array $resources,
        CarbonInterface $startOfDay,
        CarbonInterface $endOfDay
    ): ZoomResource {
        $bestResource = $resources[0];
        $minMinutes = PHP_INT_MAX;

        foreach ($resources as $resource) {
            $reservations = ResourceReservation::where('resource_id', $resource->id)
                ->whereIn('status', ['confirmed', 'held'])
                ->where('occupied_from', '<', $endOfDay)
                ->where('occupied_until', '>', $startOfDay)
                ->get();

            $totalMinutes = 0;
            foreach ($reservations as $res) {
                $start = $res->occupied_from->isBefore($startOfDay) ? $startOfDay : $res->occupied_from;
                $end = $res->occupied_until->isAfter($endOfDay) ? $endOfDay : $res->occupied_until;
                $totalMinutes += $start->diffInMinutes($end);
            }

            if ($totalMinutes < $minMinutes) {
                $minMinutes = $totalMinutes;
                $bestResource = $resource;
            }
        }

        return $bestResource;
    }

    /**
     * Pick resource with the lowest number of meetings scheduled today.
     *
     * @param  array<int, ZoomResource>  $resources
     */
    protected function pickLeastMeetings(
        array $resources,
        CarbonInterface $startOfDay,
        CarbonInterface $endOfDay
    ): ZoomResource {
        $bestResource = $resources[0];
        $minMeetings = PHP_INT_MAX;

        foreach ($resources as $resource) {
            $count = ResourceReservation::where('resource_id', $resource->id)
                ->whereIn('status', ['confirmed', 'held'])
                ->where('occupied_from', '<', $endOfDay)
                ->where('occupied_until', '>', $startOfDay)
                ->count();

            if ($count < $minMeetings) {
                $minMeetings = $count;
                $bestResource = $resource;
            }
        }

        return $bestResource;
    }

    /**
     * Pick resource with the highest priority (lower numerical value = higher priority).
     *
     * @param  array<int, ZoomResource>  $resources
     */
    protected function pickByPriority(array $resources): ZoomResource
    {
        usort($resources, fn ($a, $b) => $a->priority <=> $b->priority);

        return $resources[0];
    }
}
