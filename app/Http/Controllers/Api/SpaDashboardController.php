<?php

namespace App\Http\Controllers\Api;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Workflow\Models\MeetingApproval;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomResource;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpaDashboardController extends Controller
{
    /**
     * Return aggregate statistics, recent meetings, and pool availability.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = Auth::user();
        $todayStart = Carbon::today()->startOfDay();
        $todayEnd = Carbon::today()->endOfDay();

        $meetingsTodayCount = Meeting::whereBetween('starts_at', [$todayStart, $todayEnd])
            ->whereNotIn('status', ['cancelled'])
            ->count();

        $activeMeetingsCount = Meeting::where('status', 'started')->count();

        $upcomingMeetingsCount = Meeting::where('starts_at', '>=', Carbon::now())
            ->whereIn('status', ['scheduled', 'allocating'])
            ->count();

        $totalPools = ResourcePool::count();
        $totalResources = ZoomResource::count();
        $activeResources = ZoomResource::where('status', 'active')->count();

        $pendingApprovalsCount = MeetingApproval::pending()->count();

        // Get recent/upcoming meetings accessible to user
        $meetingsQuery = Meeting::with(['owner', 'zoomResource'])
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('starts_at', 'asc');

        if ($user && ! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator')) {
            $meetingsQuery->where(function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhere('requester_user_id', $user->id);
            });
        }

        $recentMeetings = $meetingsQuery->take(8)->get()->map(function (Meeting $meeting) {
            return [
                'public_id' => $meeting->public_id,
                'title' => $meeting->title,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'ends_at' => $meeting->ends_at?->toIso8601String(),
                'status' => $meeting->status,
                'join_url' => $meeting->join_url,
                'owner_name' => $meeting->owner->name ?? 'Unassigned',
                'resource_name' => $meeting->zoomResource->name ?? 'Dedicated Pool',
            ];
        });

        // Pool utilization summary
        /** @var Collection<int, ResourcePool> $poolCollection */
        $poolCollection = ResourcePool::withCount('resources')->get();
        $pools = $poolCollection->map(function (ResourcePool $pool) {
            return [
                'id' => $pool->id,
                'public_id' => $pool->public_id,
                'name' => $pool->name,
                'strategy' => $pool->pool_strategy,
                'resources_count' => $pool->resources_count,
            ];
        });

        /** @var Collection<int, Meeting> $activeMeetings */
        $activeMeetings = Meeting::with(['owner', 'zoomResource.zoomUser', 'department'])
            ->where('status', 'started')
            ->orderBy('starts_at', 'asc')
            ->get();

        $activeMeetingsList = $activeMeetings->map(function (Meeting $meeting): array {
            $elapsed = (int) max(0, $meeting->starts_at ? $meeting->starts_at->diffInMinutes(now()) : 0);

            return [
                'public_id' => $meeting->public_id,
                'title' => $meeting->title,
                'zoom_meeting_id' => $meeting->zoom_meeting_id,
                'join_url' => $meeting->join_url,
                'passcode' => $meeting->passcode,
                'host_key' => $meeting->zoomResource?->zoomUser?->host_key,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'ends_at' => $meeting->ends_at?->toIso8601String(),
                'elapsed_minutes' => $elapsed,
                'owner_name' => $meeting->owner->name ?? 'Unassigned',
                'department_name' => $meeting->department->name ?? 'General',
                'resource_name' => $meeting->zoomResource->name ?? 'Pooled Host',
                'participant_count' => $meeting->participant_count,
            ];
        });

        return response()->json([
            'stats' => [
                'meetings_today' => $meetingsTodayCount,
                'active_meetings' => $activeMeetingsCount,
                'upcoming_meetings' => $upcomingMeetingsCount,
                'total_pools' => $totalPools,
                'total_licenses' => $totalResources,
                'active_licenses' => $activeResources,
                'pending_approvals' => $pendingApprovalsCount,
            ],
            'recent_meetings' => $recentMeetings,
            'active_meetings_list' => $activeMeetingsList,
            'pools' => $pools,
            'demo_mode' => (bool) config('app.demo', false),
            'app_version' => (string) config('zpm.version', '1.0.0'),
        ]);
    }
}
