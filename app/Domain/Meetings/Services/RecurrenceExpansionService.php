<?php

namespace App\Domain\Meetings\Services;

use App\Domain\Scheduling\Models\BlackoutPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use RRule\RRule;

class RecurrenceExpansionService
{
    /**
     * Maximum allowed occurrences in a single series.
     */
    public const MAX_OCCURRENCES = 50;

    /**
     * Expand an RRULE into discrete occurrence start and end datetime pairs,
     * automatically skipping any dates falling on active blackout periods.
     *
     * @return array<int, array{starts_at: Carbon, ends_at: Carbon, index: int, is_skipped: bool, skip_reason: ?string}>
     */
    public function expand(
        string $rruleString,
        CarbonInterface $startsAt,
        int $durationMinutes,
        ?int $departmentId = null
    ): array {
        // Strip any leading RRULE: prefix if present
        $cleanRrule = preg_replace('/^RRULE:\s*/i', '', trim($rruleString));

        $rrule = new RRule($cleanRrule, $startsAt->toDateTime());

        $blackouts = BlackoutPeriod::where(function ($query) use ($departmentId) {
            $query->whereNull('department_id');
            if ($departmentId) {
                $query->orWhere('department_id', $departmentId);
            }
        })->get();

        $occurrences = [];
        $index = 1;

        foreach ($rrule as $occurrenceDate) {
            if ($index > self::MAX_OCCURRENCES) {
                break;
            }

            /** @var \DateTimeInterface $occurrenceDate */
            $occStart = Carbon::instance($occurrenceDate);
            $occEnd = $occStart->copy()->addMinutes($durationMinutes);

            // Check if falls inside a blackout period
            $isSkipped = false;
            $skipReason = null;

            foreach ($blackouts as $blackout) {
                if ($occStart->betweenIncluded($blackout->starts_at, $blackout->ends_at) ||
                    $occEnd->betweenIncluded($blackout->starts_at, $blackout->ends_at) ||
                    ($occStart->lessThanOrEqualTo($blackout->starts_at) && $occEnd->greaterThanOrEqualTo($blackout->ends_at))) {
                    $isSkipped = true;
                    $skipReason = "Blackout: {$blackout->name} ({$blackout->type})";
                    break;
                }
            }

            $occurrences[] = [
                'starts_at' => $occStart,
                'ends_at' => $occEnd,
                'index' => $index,
                'is_skipped' => $isSkipped,
                'skip_reason' => $skipReason,
            ];

            $index++;
        }

        return $occurrences;
    }
}
