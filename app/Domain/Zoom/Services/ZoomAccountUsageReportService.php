<?php

namespace App\Domain\Zoom\Services;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ZoomAccountUsageReportService
{
    /**
     * Generate comprehensive usage and concurrency report for Zoom accounts.
     *
     * @param  array{start_date?: string|null, end_date?: string|null, pool_id?: int|string|null, search?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function generateReport(array $filters = []): array
    {
        $now = Carbon::now();

        // 1. Resolve Date Range (default: last 30 days)
        $startDate = ! empty($filters['start_date'])
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : Carbon::now()->subDays(29)->startOfDay();

        $endDate = ! empty($filters['end_date'])
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : Carbon::now()->endOfDay();

        if ($startDate->isAfter($endDate)) {
            [$startDate, $endDate] = [$endDate->copy()->startOfDay(), $startDate->copy()->endOfDay()];
        }

        // 2. Resolve Zoom Resources Query
        $resourceQuery = ZoomResource::with(['zoomUser', 'pools'])->whereNull('deleted_at');

        if (! empty($filters['pool_id'])) {
            $poolId = $filters['pool_id'];
            $resourceQuery->whereHas('pools', function (Builder $q) use ($poolId) {
                if (is_numeric($poolId)) {
                    $q->where('resource_pools.id', (int) $poolId);
                } else {
                    $q->where('resource_pools.public_id', $poolId);
                }
            });
        }

        if (! empty($filters['search'])) {
            $searchTerm = trim($filters['search']);
            $resourceQuery->where(function (Builder $q) use ($searchTerm) {
                $q->whereHas('zoomUser', function (Builder $uq) use ($searchTerm) {
                    $uq->where('first_name', 'like', "%{$searchTerm}%")
                        ->orWhere('last_name', 'like', "%{$searchTerm}%")
                        ->orWhere('email', 'like', "%{$searchTerm}%");
                })->orWhere('public_id', 'like', "%{$searchTerm}%");
            });
        }

        /** @var Collection<int, ZoomResource> $resources */
        $resources = $resourceQuery->orderBy('priority', 'asc')->get();
        $resourceIds = $resources->pluck('id')->all();

        // 3. Query Relevant Meetings within Date Range
        $meetingsQuery = Meeting::query()
            ->whereIn('zoom_resource_id', $resourceIds)
            ->whereNotIn('status', ['cancelled'])
            ->where('starts_at', '<', $endDate)
            ->where('ends_at', '>', $startDate);

        /** @var Collection<int, Meeting> $meetings */
        $meetings = $meetingsQuery->orderBy('starts_at', 'asc')->get();

        // 4. Query Currently Active Meetings Right Now
        $currentlyActiveMeetings = Meeting::with(['zoomResource.zoomUser'])
            ->whereIn('zoom_resource_id', $resourceIds)
            ->where('status', 'started')
            ->get()
            ->keyBy('zoom_resource_id');

        // 5. Calculate Global Peak Concurrency & Timeline Breakdown
        $concurrencyData = $this->calculateConcurrency($meetings, $startDate, $endDate);
        $timelineData = $this->calculateTimeline($meetings, $startDate, $endDate);

        // 6. Aggregate Per-Account Metrics
        $accountMetrics = $this->calculateAccountMetrics(
            $resources,
            $meetings,
            $currentlyActiveMeetings,
            $startDate,
            $endDate
        );

        // 7. Calculate System High-Level Summary
        $totalAccounts = $resources->count();
        $managedAccounts = $resources->where('managed', true)->count();
        $currentlyActiveCount = $currentlyActiveMeetings->count();
        $peakConcurrent = $concurrencyData['peak_concurrent'];
        $peakTime = $concurrencyData['peak_time'];

        $concurrencyHeadroom = max(0, $totalAccounts - $peakConcurrent);
        $bufferPercentage = $totalAccounts > 0 ? round(($concurrencyHeadroom / $totalAccounts) * 100, 1) : 0;
        $peakRate = $totalAccounts > 0 ? round(($peakConcurrent / $totalAccounts) * 100, 1) : 0;
        $currentRate = $totalAccounts > 0 ? round(($currentlyActiveCount / $totalAccounts) * 100, 1) : 0;

        $totalMeetingsCount = $meetings->count();
        $totalMinutesUsed = (int) $meetings->sum(function (Meeting $m) use ($startDate, $endDate) {
            $effectiveStart = $m->starts_at->isBefore($startDate) ? $startDate : $m->starts_at;
            $effectiveEnd = $m->ends_at->isAfter($endDate) ? $endDate : $m->ends_at;

            return max(0, (int) $effectiveStart->diffInMinutes($effectiveEnd));
        });

        $totalHoursUsed = round($totalMinutesUsed / 60, 1);
        $avgDurationMinutes = $totalMeetingsCount > 0 ? (int) round($totalMinutesUsed / $totalMeetingsCount) : 0;

        $underutilizedCount = collect($accountMetrics)->where('is_underutilized', true)->count();

        // 8. Generate System Insights & Sizing Recommendation
        $insights = $this->generateInsights(
            $totalAccounts,
            $peakConcurrent,
            $concurrencyHeadroom,
            $bufferPercentage,
            $underutilizedCount
        );

        // 9. Available pools for filter dropdown
        $availablePools = ResourcePool::orderBy('name', 'asc')->get(['id', 'public_id', 'name', 'code']);

        return [
            'summary' => [
                'total_accounts' => $totalAccounts,
                'managed_accounts' => $managedAccounts,
                'currently_active_accounts' => $currentlyActiveCount,
                'current_concurrency_rate' => $currentRate,
                'peak_concurrent_accounts' => $peakConcurrent,
                'peak_concurrency_rate' => $peakRate,
                'peak_time' => $peakTime,
                'concurrency_headroom' => $concurrencyHeadroom,
                'buffer_percentage' => $bufferPercentage,
                'total_meetings' => $totalMeetingsCount,
                'total_duration_minutes' => $totalMinutesUsed,
                'total_hours' => $totalHoursUsed,
                'avg_meeting_duration_minutes' => $avgDurationMinutes,
                'underutilized_accounts_count' => $underutilizedCount,
                'range_start' => $startDate->toIso8601String(),
                'range_end' => $endDate->toIso8601String(),
            ],
            'insights' => $insights,
            'timeline' => $timelineData,
            'accounts' => $accountMetrics,
            'available_pools' => $availablePools,
            'filters' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'pool_id' => $filters['pool_id'] ?? null,
                'search' => $filters['search'] ?? null,
            ],
        ];
    }

    /**
     * Calculate global peak concurrency using event sweep line algorithm.
     *
     * @param  Collection<int, Meeting>  $meetings
     * @return array{peak_concurrent: int, peak_time: string|null}
     */
    protected function calculateConcurrency(Collection $meetings, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        if ($meetings->isEmpty()) {
            return [
                'peak_concurrent' => 0,
                'peak_time' => null,
            ];
        }

        $events = [];

        foreach ($meetings as $meeting) {
            $effectiveStart = $meeting->starts_at->isBefore($rangeStart) ? $rangeStart : $meeting->starts_at;
            $effectiveEnd = $meeting->ends_at->isAfter($rangeEnd) ? $rangeEnd : $meeting->ends_at;

            if ($effectiveEnd->isBefore($effectiveStart) || $effectiveEnd->equalTo($effectiveStart)) {
                continue;
            }

            // +1 for meeting start, -1 for meeting end
            $events[] = [
                'timestamp' => $effectiveStart->timestamp,
                'type' => 1,
                'resource_id' => $meeting->zoom_resource_id,
            ];
            $events[] = [
                'timestamp' => $effectiveEnd->timestamp,
                'type' => -1,
                'resource_id' => $meeting->zoom_resource_id,
            ];
        }

        // Sort events chronologically. If timestamps are identical, ends (-1) precede starts (+1)
        usort($events, function (array $a, array $b) {
            if ($a['timestamp'] === $b['timestamp']) {
                return $a['type'] <=> $b['type'];
            }

            return $a['timestamp'] <=> $b['timestamp'];
        });

        $currentConcurrent = 0;
        $peakConcurrent = 0;
        $peakTimestamp = null;
        $activeResources = [];

        foreach ($events as $event) {
            if ($event['type'] === 1) {
                $resId = $event['resource_id'];
                $activeResources[$resId] = ($activeResources[$resId] ?? 0) + 1;
                $currentConcurrent = count(array_filter($activeResources, fn ($c) => $c > 0));

                if ($currentConcurrent > $peakConcurrent) {
                    $peakConcurrent = $currentConcurrent;
                    $peakTimestamp = $event['timestamp'];
                }
            } else {
                $resId = $event['resource_id'];
                if (isset($activeResources[$resId])) {
                    $activeResources[$resId] = max(0, $activeResources[$resId] - 1);
                    if ($activeResources[$resId] === 0) {
                        unset($activeResources[$resId]);
                    }
                }
                $currentConcurrent = count($activeResources);
            }
        }

        return [
            'peak_concurrent' => $peakConcurrent,
            'peak_time' => $peakTimestamp ? Carbon::createFromTimestamp($peakTimestamp)->toIso8601String() : null,
        ];
    }

    /**
     * Calculate day-by-day (or hour-by-hour) concurrency and meeting volume.
     *
     * @param  Collection<int, Meeting>  $meetings
     * @return array<int, array{date: string, label: string, meetings_count: int, peak_concurrency: int, total_hours: float}>
     */
    protected function calculateTimeline(Collection $meetings, Carbon $rangeStart, Carbon $rangeEnd): array
    {
        $daysDiff = $rangeStart->diffInDays($rangeEnd);
        $timeline = [];

        if ($daysDiff <= 1) {
            // Hour-by-hour breakdown for single-day reports
            $currentHour = $rangeStart->copy()->startOfHour();
            while ($currentHour->isBefore($rangeEnd)) {
                $nextHour = $currentHour->copy()->addHour();
                $hourMeetings = $meetings->filter(function (Meeting $m) use ($currentHour, $nextHour) {
                    return $m->starts_at->isBefore($nextHour) && $m->ends_at->isAfter($currentHour);
                });

                $peak = $this->calculateConcurrency($hourMeetings, $currentHour, $nextHour);
                $durationMinutes = (int) $hourMeetings->sum(function (Meeting $m) use ($currentHour, $nextHour) {
                    $s = $m->starts_at->isBefore($currentHour) ? $currentHour : $m->starts_at;
                    $e = $m->ends_at->isAfter($nextHour) ? $nextHour : $m->ends_at;

                    return max(0, (int) $s->diffInMinutes($e));
                });

                $timeline[] = [
                    'date' => $currentHour->format('Y-m-d H:00'),
                    'label' => $currentHour->format('H:00'),
                    'meetings_count' => $hourMeetings->count(),
                    'peak_concurrency' => $peak['peak_concurrent'],
                    'total_hours' => round($durationMinutes / 60, 1),
                ];

                $currentHour->addHour();
            }

            return $timeline;
        }

        // Daily breakdown
        $currentDay = $rangeStart->copy()->startOfDay();
        while ($currentDay->isBefore($rangeEnd)) {
            $nextDay = $currentDay->copy()->endOfDay();
            $dayMeetings = $meetings->filter(function (Meeting $m) use ($currentDay, $nextDay) {
                return $m->starts_at->isBefore($nextDay) && $m->ends_at->isAfter($currentDay);
            });

            $peak = $this->calculateConcurrency($dayMeetings, $currentDay, $nextDay);
            $durationMinutes = (int) $dayMeetings->sum(function (Meeting $m) use ($currentDay, $nextDay) {
                $s = $m->starts_at->isBefore($currentDay) ? $currentDay : $m->starts_at;
                $e = $m->ends_at->isAfter($nextDay) ? $nextDay : $m->ends_at;

                return max(0, (int) $s->diffInMinutes($e));
            });

            $timeline[] = [
                'date' => $currentDay->format('Y-m-d'),
                'label' => $currentDay->format('M j'),
                'meetings_count' => $dayMeetings->count(),
                'peak_concurrency' => $peak['peak_concurrent'],
                'total_hours' => round($durationMinutes / 60, 1),
            ];

            $currentDay->addDay();
        }

        return $timeline;
    }

    /**
     * Calculate per-account metrics.
     *
     * @param  Collection<int, ZoomResource>  $resources
     * @param  Collection<int, Meeting>  $meetings
     * @param  Collection<int, Meeting>  $activeMeetings
     * @return array<int, array<string, mixed>>
     */
    protected function calculateAccountMetrics(
        Collection $resources,
        Collection $meetings,
        Collection $activeMeetings,
        Carbon $rangeStart,
        Carbon $rangeEnd
    ): array {
        // Assume standard operational window: 10 working hours per day over date range
        $totalDays = max(1, (int) ceil($rangeStart->diffInDays($rangeEnd)));
        $standardWorkHours = $totalDays * 10;
        $standardWorkMinutes = $standardWorkHours * 60;

        $meetingsByResource = $meetings->groupBy('zoom_resource_id');

        $result = [];

        foreach ($resources as $resource) {
            /** @var Collection<int, Meeting> $accountMeetings */
            $accountMeetings = $meetingsByResource->get($resource->id, collect());

            $meetingsCount = $accountMeetings->count();
            $totalMinutes = (int) $accountMeetings->sum(function (Meeting $m) use ($rangeStart, $rangeEnd) {
                $effectiveStart = $m->starts_at->isBefore($rangeStart) ? $rangeStart : $m->starts_at;
                $effectiveEnd = $m->ends_at->isAfter($rangeEnd) ? $rangeEnd : $m->ends_at;

                return max(0, (int) $effectiveStart->diffInMinutes($effectiveEnd));
            });

            $totalHours = round($totalMinutes / 60, 1);
            $utilizationRate = $standardWorkMinutes > 0
                ? min(100.0, round(($totalMinutes / $standardWorkMinutes) * 100, 1))
                : 0.0;

            // Determine current status
            $currentMeeting = $activeMeetings->get($resource->id);
            $status = 'idle';
            if (! $resource->managed) {
                $status = 'excluded';
            } elseif ($currentMeeting !== null) {
                $status = 'in_meeting';
            }

            // Latest meeting
            $latestMeeting = $accountMeetings->sortByDesc('starts_at')->first();
            $lastUsedAt = $latestMeeting ? $latestMeeting->starts_at?->toIso8601String() : null;

            // Pools
            $pools = $resource->pools->map(function ($pool) {
                return [
                    'id' => $pool->id,
                    'public_id' => $pool->public_id,
                    'name' => $pool->name,
                    'code' => $pool->code,
                ];
            })->values()->all();

            $isUnderutilized = $resource->managed && ($meetingsCount === 0 || $utilizationRate < 5.0);

            $result[] = [
                'id' => $resource->id,
                'public_id' => $resource->public_id,
                'name' => $resource->name,
                'email' => $resource->zoomUser?->email ?? 'N/A',
                'zoom_user_id' => $resource->zoomUser?->zoom_user_id ?? $resource->zoom_user_id,
                'capacity' => $resource->participant_capacity ?: 100,
                'managed' => (bool) $resource->managed,
                'status' => $resource->status,
                'capabilities' => [
                    'cloud_recording' => (bool) $resource->cloud_recording,
                    'ai_companion' => (bool) $resource->ai_companion,
                    'webinar' => (bool) $resource->webinar_capacity,
                ],
                'pools' => $pools,
                'meetings_count' => $meetingsCount,
                'total_minutes' => $totalMinutes,
                'total_hours' => $totalHours,
                'utilization_rate' => $utilizationRate,
                'current_status' => $status,
                'current_meeting' => $currentMeeting ? [
                    'public_id' => $currentMeeting->public_id,
                    'title' => $currentMeeting->title,
                    'starts_at' => $currentMeeting->starts_at?->toIso8601String(),
                    'ends_at' => $currentMeeting->ends_at?->toIso8601String(),
                    'join_url' => $currentMeeting->join_url,
                ] : null,
                'last_used_at' => $lastUsedAt,
                'is_underutilized' => $isUnderutilized,
            ];
        }

        // Sort by total hours descending so busiest accounts are at the top
        usort($result, function (array $a, array $b) {
            return $b['total_minutes'] <=> $a['total_minutes'];
        });

        return $result;
    }

    /**
     * Generate actionable system insights and capacity recommendations.
     *
     * @return array{headline: string, recommendation: string, status_level: string, recommended_licenses: int}
     */
    protected function generateInsights(
        int $totalAccounts,
        int $peakConcurrent,
        int $concurrencyHeadroom,
        float $bufferPercentage,
        int $underutilizedCount
    ): array {
        if ($totalAccounts === 0) {
            return [
                'headline' => 'No Zoom accounts registered in the system.',
                'recommendation' => 'Sync your Zoom accounts from Settings > Zoom Accounts to analyze concurrency.',
                'status_level' => 'neutral',
                'recommended_licenses' => 0,
            ];
        }

        // Target a 25% safety buffer above peak concurrency
        $recommendedLicenses = max(1, (int) ceil($peakConcurrent * 1.25));

        if ($peakConcurrent === 0) {
            return [
                'headline' => "Zero concurrent meetings recorded across all {$totalAccounts} accounts in this timeframe.",
                'recommendation' => 'No active usage was observed during this period. Ensure meetings are being scheduled through the pool.',
                'status_level' => 'neutral',
                'recommended_licenses' => $recommendedLicenses,
            ];
        }

        if ($bufferPercentage >= 50.0 && $totalAccounts >= 10) {
            return [
                'headline' => "Significant Capacity Headroom: Peak concurrency was {$peakConcurrent} of {$totalAccounts} accounts ({$bufferPercentage}% buffer).",
                'recommendation' => "Your system peaked at {$peakConcurrent} concurrent accounts. With {$concurrencyHeadroom} unused licenses during peak demand and {$underutilizedCount} underutilized accounts, your organization could comfortably operate with approximately {$recommendedLicenses} pooled licenses (saving up to " . ($totalAccounts - $recommendedLicenses) . " license subscriptions).",
                'status_level' => 'optimal',
                'recommended_licenses' => $recommendedLicenses,
            ];
        }

        if ($bufferPercentage <= 15.0) {
            return [
                'headline' => "High Utilization Alert: Peak concurrency reached {$peakConcurrent} of {$totalAccounts} accounts ({$bufferPercentage}% buffer remaining).",
                'recommendation' => "Your concurrency buffer is low. During peak slots, {$peakConcurrent} host licenses were simultaneously occupied. Consider adding 3–5 additional host accounts or using the waitlist engine to avoid meeting allocation rejections.",
                'status_level' => 'warning',
                'recommended_licenses' => (int) ceil($peakConcurrent * 1.3),
            ];
        }

        return [
            'headline' => "Balanced Pool Utilization: Peak concurrency reached {$peakConcurrent} of {$totalAccounts} accounts ({$bufferPercentage}% buffer).",
            'recommendation' => "Concurrency is well-balanced. Your pool maintains sufficient headroom ({$concurrencyHeadroom} spare accounts at peak) to absorb demand surges while avoiding excessive idle license costs.",
            'status_level' => 'healthy',
            'recommended_licenses' => $recommendedLicenses,
        ];
    }

    /**
     * Export usage metrics to a downloadable CSV stream.
     *
     * @param  array{start_date?: string|null, end_date?: string|null, pool_id?: int|string|null, search?: string|null}  $filters
     */
    public function exportCsv(array $filters = []): StreamedResponse
    {
        $report = $this->generateReport($filters);
        $accounts = $report['accounts'];
        $summary = $report['summary'];

        $filename = 'zoom_account_usage_report_' . Carbon::now()->format('Ymd_His') . '.csv';

        return new StreamedResponse(function () use ($accounts, $summary) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel
            fputs($handle, "\xEF\xBB\xBF");

            // Summary Metadata Header
            fputcsv($handle, ['Zoom Pool Manager - Account Usage & Concurrency Report']);
            fputcsv($handle, ['Generated At', Carbon::now()->toDateTimeString()]);
            fputcsv($handle, ['Date Range', $summary['range_start'] . ' to ' . $summary['range_end']]);
            fputcsv($handle, ['Total Accounts', $summary['total_accounts']]);
            fputcsv($handle, ['Peak Concurrency (Simultaneous Accounts)', $summary['peak_concurrent_accounts']]);
            fputcsv($handle, ['Concurrency Headroom (Buffer)', $summary['concurrency_headroom'] . ' accounts (' . $summary['buffer_percentage'] . '%)']);
            fputcsv($handle, ['Total Meetings Hosted', $summary['total_meetings']]);
            fputcsv($handle, ['Total Occupied Hours', $summary['total_hours'] . ' hrs']);
            fputcsv($handle, []); // Blank separator row

            // Data Table Headers
            fputcsv($handle, [
                'Account Name',
                'Zoom Email',
                'Participant Capacity',
                'Status',
                'Managed in Pool',
                'Assigned Pools',
                'Total Meetings',
                'Total Duration (Minutes)',
                'Total Duration (Hours)',
                'Est. Utilization (%)',
                'Underutilized',
                'Last Used Date',
            ]);

            foreach ($accounts as $row) {
                $poolNames = implode('; ', array_column($row['pools'], 'name'));

                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['capacity'],
                    ucfirst(str_replace('_', ' ', $row['current_status'])),
                    $row['managed'] ? 'Yes' : 'No',
                    $poolNames ?: 'None',
                    $row['meetings_count'],
                    $row['total_minutes'],
                    $row['total_hours'],
                    $row['utilization_rate'] . '%',
                    $row['is_underutilized'] ? 'Yes' : 'No',
                    $row['last_used_at'] ? Carbon::parse($row['last_used_at'])->format('Y-m-d H:i:s') : 'Never',
                ]);
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
