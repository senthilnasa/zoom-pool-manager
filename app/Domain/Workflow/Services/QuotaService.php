<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\Quota;
use App\Domain\Workflow\Models\QuotaUsage;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuotaService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Check if booking exceeds user or department quotas.
     * Throws RuntimeException if quota is exceeded.
     */
    public function checkQuota(
        User $user,
        CarbonInterface $startsAt,
        int $durationMinutes,
        bool $bypassIfPermitted = true
    ): void {
        if ($bypassIfPermitted && ($user->can('quota.manage') || $user->can('meeting.override') || $user->hasRole('super_admin') || $user->hasRole('it_admin'))) {
            return;
        }

        $year = (int) $startsAt->format('Y');
        $month = (int) $startsAt->format('n');

        // 1. Check User Quota
        /** @var Quota|null $userQuota */
        $userQuota = Quota::active()
            ->where('scope_type', 'user')
            ->where('scope_id', $user->id)
            ->first();

        if ($userQuota) {
            $this->validateQuotaLimits($userQuota, $year, $month, $durationMinutes, "User {$user->name}");
        }

        // 2. Check Department Quota
        if ($user->department_id) {
            /** @var Quota|null $deptQuota */
            $deptQuota = Quota::active()
                ->where('scope_type', 'department')
                ->where('scope_id', $user->department_id)
                ->first();

            if ($deptQuota) {
                $this->validateQuotaLimits($deptQuota, $year, $month, $durationMinutes, 'Department');
            }
        }
    }

    /**
     * Increment usage records when meeting is scheduled.
     */
    public function recordUsage(Meeting $meeting): void
    {
        $year = (int) $meeting->starts_at->format('Y');
        $month = (int) $meeting->starts_at->format('n');
        $duration = $meeting->duration_minutes;

        DB::transaction(function () use ($meeting, $year, $month, $duration) {
            // User quota
            $userQuota = Quota::active()
                ->where('scope_type', 'user')
                ->where('scope_id', $meeting->requester_user_id)
                ->first();

            if ($userQuota) {
                $this->incrementQuota($userQuota, $year, $month, $duration);
            }

            // Department quota
            if ($meeting->department_id) {
                $deptQuota = Quota::active()
                    ->where('scope_type', 'department')
                    ->where('scope_id', $meeting->department_id)
                    ->first();

                if ($deptQuota) {
                    $this->incrementQuota($deptQuota, $year, $month, $duration);
                }
            }
        });
    }

    /**
     * Decrement usage records when meeting is cancelled.
     */
    public function releaseUsage(Meeting $meeting): void
    {
        $year = (int) $meeting->starts_at->format('Y');
        $month = (int) $meeting->starts_at->format('n');
        $duration = $meeting->duration_minutes;

        DB::transaction(function () use ($meeting, $year, $month, $duration) {
            // User quota
            $userQuota = Quota::where('scope_type', 'user')
                ->where('scope_id', $meeting->requester_user_id)
                ->first();

            if ($userQuota) {
                $this->decrementQuota($userQuota, $year, $month, $duration);
            }

            // Department quota
            if ($meeting->department_id) {
                $deptQuota = Quota::where('scope_type', 'department')
                    ->where('scope_id', $meeting->department_id)
                    ->first();

                if ($deptQuota) {
                    $this->decrementQuota($deptQuota, $year, $month, $duration);
                }
            }
        });
    }

    /**
     * Get usage statistics for a given scope.
     *
     * @return array{meetings_count: int, minutes_used: int, hours_used: float, max_meetings: int|null, max_hours: int|null, percentage_meetings: float|null, percentage_hours: float|null}
     */
    public function getUsageStats(Quota $quota, int $year, int $month): array
    {
        $usage = QuotaUsage::where('quota_id', $quota->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        $meetingsCount = $usage ? $usage->meetings_count : 0;
        $minutesUsed = $usage ? $usage->minutes_used : 0;
        $hoursUsed = round($minutesUsed / 60, 1);

        $pctMeetings = $quota->max_meetings_per_month ? round(($meetingsCount / $quota->max_meetings_per_month) * 100, 1) : null;
        $pctHours = $quota->max_hours_per_month ? round(($hoursUsed / $quota->max_hours_per_month) * 100, 1) : null;

        return [
            'meetings_count' => $meetingsCount,
            'minutes_used' => $minutesUsed,
            'hours_used' => $hoursUsed,
            'max_meetings' => $quota->max_meetings_per_month,
            'max_hours' => $quota->max_hours_per_month,
            'percentage_meetings' => $pctMeetings,
            'percentage_hours' => $pctHours,
        ];
    }

    protected function validateQuotaLimits(
        Quota $quota,
        int $year,
        int $month,
        int $additionalMinutes,
        string $scopeLabel
    ): void {
        $usage = QuotaUsage::where('quota_id', $quota->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        $currentMeetings = $usage ? $usage->meetings_count : 0;
        $currentMinutes = $usage ? $usage->minutes_used : 0;

        if ($quota->max_meetings_per_month !== null && ($currentMeetings + 1) > $quota->max_meetings_per_month) {
            throw new RuntimeException("Quota exceeded: {$scopeLabel} has reached the limit of {$quota->max_meetings_per_month} meetings for this month.");
        }

        $maxMinutes = $quota->max_hours_per_month !== null ? ($quota->max_hours_per_month * 60) : null;
        if ($maxMinutes !== null && ($currentMinutes + $additionalMinutes) > $maxMinutes) {
            $currentHours = round($currentMinutes / 60, 1);
            throw new RuntimeException("Quota exceeded: {$scopeLabel} has used {$currentHours} of {$quota->max_hours_per_month} allocated hours this month.");
        }
    }

    protected function incrementQuota(Quota $quota, int $year, int $month, int $durationMinutes): void
    {
        /** @var QuotaUsage $usage */
        $usage = QuotaUsage::firstOrCreate(
            [
                'quota_id' => $quota->id,
                'period_year' => $year,
                'period_month' => $month,
            ],
            [
                'meetings_count' => 0,
                'minutes_used' => 0,
            ]
        );

        $usage->increment('meetings_count', 1);
        $usage->increment('minutes_used', $durationMinutes);
    }

    protected function decrementQuota(Quota $quota, int $year, int $month, int $durationMinutes): void
    {
        $usage = QuotaUsage::where('quota_id', $quota->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($usage) {
            $newMeetings = max(0, $usage->meetings_count - 1);
            $newMinutes = max(0, $usage->minutes_used - $durationMinutes);

            $usage->update([
                'meetings_count' => $newMeetings,
                'minutes_used' => $newMinutes,
            ]);
        }
    }
}
