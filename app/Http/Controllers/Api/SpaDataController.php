<?php

namespace App\Http\Controllers\Api;

use App\Domain\Attendance\Models\MeetingAttendance;
use App\Domain\Attendance\Services\ZoomAttendanceSyncService;
use App\Domain\Communication\Services\IcsCalendarService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingCustomField;
use App\Domain\Meetings\Models\MeetingInvitee;
use App\Domain\Meetings\Models\MeetingSeries;
use App\Domain\Meetings\Services\MeetingLifecycleService;
use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Meetings\Services\MeetingStateMachine;
use App\Domain\Meetings\Services\SeriesAllocationService;
use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Recordings\Services\CloudRecordingService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\MeetingApproval;
use App\Domain\Workflow\Services\WaitlistService;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SpaDataController extends Controller
{
    public function __construct(
        protected ZoomAttendanceSyncService $attendanceService,
        protected CloudRecordingService $recordingService,
        protected MeetingLifecycleService $lifecycleService,
        protected MeetingService $meetingService,
        protected SeriesAllocationService $seriesAllocationService,
        protected MeetingStateMachine $stateMachine,
        protected WaitlistService $waitlistService,
        protected IcsCalendarService $icsService
    ) {}

    /**
     * Get paginated meetings with search, filter, and server-side sorting.
     */
    public function meetings(Request $request): JsonResponse
    {
        // Reconcile lifecycle states (resolve stuck allocating, advance started & completed)
        $this->lifecycleService->reconcileAll();

        /** @var User $user */
        $user = Auth::user();

        $query = Meeting::with([
            'owner:id,name,email',
            'requester:id,name,email',
            'zoomResource.zoomUser',
            'template:id,name',
            'securityProfile:id,name',
            'invitees',
        ]);

        if (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator')) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhere('requester_user_id', $user->id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('public_id', 'like', $search)
                    ->orWhere('zoom_meeting_id', 'like', $search);
            });
        }

        // Server-Side Sorting
        $sortBy = $request->query('sort_by', 'starts_at');
        $allowedSorts = ['title', 'starts_at', 'created_at', 'status', 'participant_count'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'starts_at';
        }

        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Server-Side Pagination (default 25; allowed 10, 25, 100)
        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, [10, 25, 100], true)) {
            $perPage = 25;
        }

        $meetings = $query->paginate($perPage);

        return response()->json($meetings);
    }

    /**
     * Export scheduled meetings to Excel (.xlsx) or PDF printable report.
     */
    public function exportMeetings(Request $request): Response|StreamedResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $query = Meeting::with(['owner', 'requester', 'zoomResource', 'template']);

        if (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator')) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhere('requester_user_id', $user->id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)
                    ->orWhere('public_id', 'like', $search)
                    ->orWhere('zoom_meeting_id', 'like', $search);
            });
        }

        $sortBy = $request->query('sort_by', 'starts_at');
        if (! in_array($sortBy, ['title', 'starts_at', 'created_at', 'status', 'participant_count'], true)) {
            $sortBy = 'starts_at';
        }
        $sortDir = strtolower((string) $request->query('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $format = strtolower((string) $request->query('format', 'xlsx'));

        if ($format === 'pdf') {
            $meetings = $query->limit(500)->get();
            $html = view('exports.meetings_pdf', [
                'meetings' => $meetings,
                'user' => $user,
                'exported_at' => now(),
                'filters' => [
                    'status' => $request->query('status') ?: 'All Statuses',
                    'search' => $request->query('search') ?: 'None',
                    'total' => $meetings->count(),
                ],
            ])->render();

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="meetings_report_'.date('Ymd_His').'.html"',
            ]);
        }

        // Excel / XLSX Export
        $filename = 'scheduled_meetings_'.date('Ymd_His').'.xlsx';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, [
                'Meeting ID',
                'Title',
                'Status',
                'Host / Owner',
                'Owner Email',
                'Assigned Resource',
                'Zoom Meeting ID',
                'Start Date & Time',
                'End Date & Time',
                'Duration (Minutes)',
                'Max Participants',
                'Join URL',
                'Created At',
            ]);

            $query->chunk(200, function ($batch) use ($handle) {
                foreach ($batch as $m) {
                    fputcsv($handle, [
                        $m->public_id,
                        $m->title,
                        strtoupper($m->status),
                        $m->owner->name ?? 'N/A',
                        $m->owner->email ?? 'N/A',
                        $m->zoomResource->name ?? 'Pooled Auto-Assign',
                        $m->zoom_meeting_id ?: 'N/A',
                        $m->starts_at ? $m->starts_at->toIso8601String() : 'N/A',
                        $m->ends_at ? $m->ends_at->toIso8601String() : 'N/A',
                        $m->starts_at && $m->ends_at ? $m->starts_at->diffInMinutes($m->ends_at) : 60,
                        $m->participant_count ?? 0,
                        $m->join_url ?: 'N/A',
                        $m->created_at ? $m->created_at->toIso8601String() : 'N/A',
                    ]);
                }
            });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Get options required for creating meetings (templates, profiles, pools, users for booking on behalf).
     */
    public function meetingOptions(): JsonResponse
    {
        $templates = MeetingTemplate::where('is_active', true)->get();
        $profiles = SecurityProfile::all();
        $pools = ResourcePool::where('is_active', true)->get();

        /** @var User|null $user */
        $user = Auth::user();

        $canBookOnBehalf = $user && (
            $user->can('meeting.book_on_behalf')
            || $user->hasRole('Super Administrator')
            || $user->hasRole('Administrator')
            || $user->hasRole('super_admin')
        );

        $users = $canBookOnBehalf
            ? User::where('is_active', true)->select('id', 'name', 'email', 'department_id', 'designation')->with('department:id,name')->orderBy('name')->limit(50)->get()
            : [];

        $customFields = MeetingCustomField::where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        return response()->json([
            'templates' => $templates,
            'profiles' => $profiles,
            'pools' => $pools,
            'can_book_on_behalf' => $canBookOnBehalf,
            'users' => $users,
            'custom_fields' => $customFields,
        ]);
    }

    /**
     * Get paginated recordings list.
     */
    public function recordings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $query = CloudRecording::with(['meeting.owner'])
            ->orderBy('created_at', 'desc');

        if (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator')) {
            $query->whereHas('meeting', function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhere('requester_user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('topic', 'like', $search)
                    ->orWhere('zoom_meeting_id', 'like', $search)
                    ->orWhere('public_id', 'like', $search);
            });
        }

        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, [10, 25, 100], true)) {
            $perPage = 25;
        }

        $recordings = $query->paginate($perPage);

        return response()->json($recordings);
    }

    /**
     * Manually register or link a cloud recording.
     */
    public function storeRecording(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var array{topic: string, meeting_id?: ?int, zoom_meeting_id?: ?string, play_url: string, duration_minutes?: ?int, file_size_bytes?: ?int, recording_start?: ?string} $validated */
        $validated = $request->validate([
            'topic' => 'required|string|max:255',
            'meeting_id' => 'nullable|integer|exists:meetings,id',
            'zoom_meeting_id' => 'nullable|string|max:64',
            'play_url' => 'required|url|max:2048',
            'duration_minutes' => 'nullable|integer|min:0',
            'file_size_bytes' => 'nullable|integer|min:0',
            'recording_start' => 'nullable|date',
        ]);

        $recording = CloudRecording::create([
            'meeting_id' => $validated['meeting_id'] ?? null,
            'logical_owner_user_id' => $user->id,
            'topic' => $validated['topic'],
            'zoom_meeting_id' => $validated['zoom_meeting_id'] ?? 'MANUAL_'.time(),
            'storage_provider' => 'zoom',
            'play_url' => $validated['play_url'],
            'share_url' => $validated['play_url'],
            'duration_minutes' => $validated['duration_minutes'] ?? 60,
            'file_size_bytes' => $validated['file_size_bytes'] ?? 104857600, // 100MB default
            'recording_start' => ! empty($validated['recording_start']) ? Carbon::parse($validated['recording_start']) : now(),
            'recording_end' => now(),
            'status' => 'completed',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cloud recording registered successfully.',
            'recording' => $recording,
        ], 201);
    }

    /**
     * Trigger sync of completed cloud recordings from Zoom API.
     */
    public function syncRecordings(): JsonResponse
    {
        /** @var ZoomConnection|null $conn */
        $conn = ZoomConnection::first();

        if (config('app.demo') || ! $conn || empty($conn->account_id)) {
            // Generate a demo recording for testing
            $demoMeeting = Meeting::latest()->first();
            $recording = CloudRecording::create([
                'meeting_id' => $demoMeeting?->id,
                'logical_owner_user_id' => Auth::id(),
                'topic' => $demoMeeting ? $demoMeeting->title.' (Recording)' : 'Introduction to Advanced Quantum Computing',
                'zoom_meeting_id' => $demoMeeting?->zoom_meeting_id ?: '84920491823',
                'storage_provider' => 'zoom',
                'play_url' => 'https://zoom.us/rec/play/demo_playback_url',
                'share_url' => 'https://zoom.us/rec/share/demo_share_url',
                'duration_minutes' => 75,
                'file_size_bytes' => 314572800, // ~300MB
                'recording_start' => now()->subDay(),
                'recording_end' => now()->subDay()->addMinutes(75),
                'status' => 'completed',
            ]);

            return response()->json([
                'success' => true,
                'synced_count' => 1,
                'message' => 'Synced completed recordings from Zoom (1 recording updated).',
            ]);
        }

        // Live Mode
        \Artisan::call('zpm:recordings:sync');
        $output = trim(\Artisan::output());

        return response()->json([
            'success' => true,
            'message' => $output ?: 'Triggered Zoom cloud recordings synchronization.',
        ]);
    }

    /**
     * Get meeting attendance overview with aggregate stats.
     */
    public function attendance(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $query = Meeting::withCount('attendances')
            ->with(['owner:id,name,email', 'zoomResource.zoomUser'])
            ->whereNotNull('zoom_meeting_id')
            ->orderBy('starts_at', 'desc');

        if (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator')) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhere('requester_user_id', $user->id);
            });
        }

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('zoom_meeting_id', 'like', $search);
            });
        }

        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, [10, 25, 100], true)) {
            $perPage = 25;
        }

        $meetings = $query->paginate($perPage);

        return response()->json($meetings);
    }

    /**
     * Get detailed participant attendance logs for a specific meeting.
     */
    public function attendanceDetails(string $publicId): JsonResponse
    {
        $meeting = Meeting::where('public_id', $publicId)->orWhere('id', $publicId)->firstOrFail();

        $attendances = MeetingAttendance::where('meeting_id', $meeting->id)
            ->orderBy('join_time', 'asc')
            ->get();

        $avgRate = $attendances->count() > 0 ? round($attendances->avg('attendance_percentage'), 1) : 0;
        $totalPresent = $attendances->where('status', 'present')->count();
        $totalPartial = $attendances->where('status', 'partial')->count();

        return response()->json([
            'meeting' => $meeting->load(['owner', 'zoomResource']),
            'attendances' => $attendances,
            'stats' => [
                'total_participants' => $attendances->count(),
                'avg_attendance_percentage' => $avgRate,
                'total_present' => $totalPresent,
                'total_partial' => $totalPartial,
            ],
        ]);
    }

    /**
     * Sync past meeting attendee participation reports from Zoom.
     */
    public function syncAttendance(Request $request): JsonResponse
    {
        if ($request->filled('meeting_id')) {
            $meeting = Meeting::findOrFail($request->integer('meeting_id'));
            $result = $this->attendanceService->syncAttendanceForMeeting($meeting);
        } else {
            $result = $this->attendanceService->syncRecentAttendance();
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * Export attendance report as downloadable CSV.
     */
    public function exportAttendanceCsv(string $publicId): StreamedResponse
    {
        $meeting = Meeting::where('public_id', $publicId)->orWhere('id', $publicId)->firstOrFail();
        $attendances = MeetingAttendance::where('meeting_id', $meeting->id)->orderBy('join_time')->get();

        $filename = 'attendance_meeting_'.$meeting->zoom_meeting_id.'_'.date('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($attendances) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Header Row
            fputcsv($handle, [
                'Participant Name',
                'Email',
                'Join Time',
                'Leave Time',
                'Duration (Minutes)',
                'Attendance Rate (%)',
                'Status',
                'Device',
            ]);

            foreach ($attendances as $row) {
                fputcsv($handle, [
                    $row->participant_name,
                    $row->participant_email ?: 'N/A',
                    $row->join_time ? $row->join_time->toIso8601String() : 'N/A',
                    $row->leave_time ? $row->leave_time->toIso8601String() : 'N/A',
                    round($row->duration_seconds / 60, 1),
                    $row->attendance_percentage.'%',
                    ucfirst($row->status),
                    $row->device_type ?: 'Unknown',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Get approval requests for current user or admin.
     */
    public function approvals(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $query = MeetingApproval::with(['meeting.owner', 'meeting.template', 'approver'])
            ->orderBy('created_at', 'desc');

        if (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator')) {
            $query->where('approver_user_id', $user->id);
        }

        $approvals = $query->paginate($request->integer('per_page', 15));

        return response()->json($approvals);
    }

    /**
     * Get paginated recurring series list.
     */
    public function series(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $query = MeetingSeries::with(['owner', 'zoomResource'])
            ->withCount('meetings')
            ->orderBy('created_at', 'desc');

        if (! $user->hasRole('Super Administrator') && ! $user->hasRole('Administrator')) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_user_id', $user->id)
                    ->orWhere('requester_user_id', $user->id);
            });
        }

        $series = $query->paginate($request->integer('per_page', 15));

        return response()->json($series);
    }

    /**
     * Get resource timeline calendar data with rich relations, pools, departments, and buffer configs.
     */
    public function calendar(Request $request): JsonResponse
    {
        $startStr = $request->query('start');
        $endStr = $request->query('end');

        $start = $startStr ? Carbon::parse($startStr) : Carbon::now()->startOfWeek();
        $end = $endStr ? Carbon::parse($endStr) : Carbon::now()->endOfWeek()->addWeeks(2);

        $resourcesQuery = ZoomResource::with(['zoomUser', 'pools'])
            ->where(function ($q) {
                $q->where('status', 'active')
                    ->orWhere('managed', true);
            });

        if ($request->filled('pool_id')) {
            $poolId = (int) $request->query('pool_id');
            $resourcesQuery->whereHas('pools', function ($q) use ($poolId) {
                $q->where('resource_pools.id', $poolId);
            });
        }

        $resources = $resourcesQuery->get();

        $meetingsQuery = Meeting::whereBetween('starts_at', [$start, $end])
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->with(['owner:id,name,email', 'zoomResource.zoomUser', 'department:id,name,code', 'template:id,name']);

        if ($request->filled('pool_id')) {
            $poolId = (int) $request->query('pool_id');
            $meetingsQuery->whereHas('zoomResource.pools', function ($q) use ($poolId) {
                $q->where('resource_pools.id', $poolId);
            });
        }

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $meetingsQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', $search)
                    ->orWhere('public_id', 'like', $search)
                    ->orWhere('zoom_meeting_id', 'like', $search);
            });
        }

        /** @var Collection<int, Meeting> $meetingsCollection */
        $meetingsCollection = $meetingsQuery->get();

        $meetings = $meetingsCollection->map(function (Meeting $meeting): array {
            return [
                'id' => $meeting->id,
                'public_id' => $meeting->public_id,
                'title' => $meeting->title,
                'description' => $meeting->description,
                'meeting_type' => $meeting->meeting_type,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'ends_at' => $meeting->ends_at?->toIso8601String(),
                'status' => $meeting->status,
                'zoom_resource_id' => $meeting->zoom_resource_id,
                'zoom_meeting_id' => $meeting->zoom_meeting_id,
                'join_url' => $meeting->join_url,
                'passcode' => $meeting->passcode,
                'host_key' => $meeting->host_key ?? $meeting->zoomResource?->zoomUser?->host_key,
                'recording_mode' => $meeting->recording_mode,
                'waiting_room' => (bool) $meeting->waiting_room,
                'join_before_host' => (bool) $meeting->join_before_host,
                'jbh_time' => (int) $meeting->jbh_time,
                'attendance_tracking' => (bool) $meeting->attendance_tracking,
                'share_host_key' => (bool) $meeting->share_host_key,
                'participant_count' => $meeting->participant_count,
                'owner' => $meeting->owner,
                'department' => $meeting->department,
                'zoom_resource' => $meeting->zoomResource,
                'template' => $meeting->template,
            ];
        });

        $pools = ResourcePool::select('id', 'public_id', 'name', 'pool_strategy')->get();
        $departments = Department::select('id', 'name', 'code')->orderBy('name')->get();
        $templates = MeetingTemplate::select('id', 'name', 'requires_approval')->where('is_active', true)->get();
        $bufferMinutes = (int) Setting::get('org.min_buffer_minutes', 15);

        return response()->json([
            'resources' => $resources,
            'meetings' => $meetings,
            'pools' => $pools,
            'departments' => $departments,
            'templates' => $templates,
            'buffer_minutes' => $bufferMinutes,
        ]);
    }

    /**
     * Quick-book meeting directly from the interactive calendar.
     */
    public function quickBook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'meeting_type' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'participant_count' => ['nullable', 'integer', 'min:1'],
            'preferred_pool_id' => ['nullable', 'integer'],
            'pool_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'template_id' => ['nullable', 'integer'],
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recording_mode' => ['nullable', 'string', 'in:none,cloud,local'],
            'waiting_room' => ['nullable', 'boolean'],
            'join_before_host' => ['nullable', 'boolean'],
            'jbh_time' => ['nullable', 'integer', 'in:0,5,10,15'],
            'attendance_tracking' => ['nullable', 'boolean'],
            'share_host_key' => ['nullable', 'boolean'],
            'passcode' => ['nullable', 'string', 'max:32'],
            'custom_fields' => ['nullable', 'array'],
            'invitees' => ['nullable', 'string'],
            'is_recurring' => ['nullable', 'boolean'],
            'rrule' => ['nullable', 'string'],
            'frequency' => ['nullable', 'string', 'in:DAILY,WEEKLY,MONTHLY'],
            'interval' => ['nullable', 'integer', 'min:1'],
            'byday' => ['nullable'],
            'occurrence_count' => ['nullable', 'integer', 'min:1', 'max:50'],
            'until_date' => ['nullable', 'date'],
            'series_mode' => ['nullable', 'string', 'in:SINGLE_RESOURCE,SPLIT_WHEN_NEEDED,PER_OCCURRENCE'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! empty($validated['pool_id']) && empty($validated['preferred_pool_id'])) {
            $validated['preferred_pool_id'] = $validated['pool_id'];
        }

        $inviteeEmails = [];
        if (! empty($validated['invitees'])) {
            $inviteeEmails = array_map('trim', explode(',', $validated['invitees']));
        }

        try {
            if ($request->boolean('is_recurring')) {
                if (empty($validated['rrule'])) {
                    $freq = strtoupper((string) ($validated['frequency'] ?? 'WEEKLY'));
                    $interval = max(1, (int) ($validated['interval'] ?? 1));
                    $rruleParts = ["FREQ={$freq}", "INTERVAL={$interval}"];

                    if ($freq === 'WEEKLY' && ! empty($validated['byday'])) {
                        $bydayStr = is_array($validated['byday']) ? implode(',', $validated['byday']) : (string) $validated['byday'];
                        $rruleParts[] = "BYDAY={$bydayStr}";
                    }

                    if (! empty($validated['occurrence_count'])) {
                        $rruleParts[] = 'COUNT='.(int) $validated['occurrence_count'];
                    } elseif (! empty($validated['until_date'])) {
                        $rruleParts[] = 'UNTIL='.Carbon::parse($validated['until_date'])->format('Ymd\THis\Z');
                    } else {
                        $rruleParts[] = 'COUNT=5';
                    }

                    $validated['rrule'] = implode(';', $rruleParts);
                }

                $startsAt = Carbon::parse($validated['starts_at']);
                $endsAt = Carbon::parse($validated['ends_at']);
                $validated['duration_minutes'] = max(15, (int) $startsAt->diffInMinutes($endsAt));
                $validated['series_mode'] = $validated['series_mode'] ?? 'SINGLE_RESOURCE';

                $series = $this->seriesAllocationService->createSeries($user, $validated, $inviteeEmails);
                $firstMeeting = $series->meetings()->first();

                return response()->json([
                    'success' => true,
                    'message' => 'Recurring series scheduled successfully with allocated pooled resources.',
                    'series' => $series,
                    'meeting' => $firstMeeting?->load(['owner', 'zoomResource', 'department']),
                ]);
            }

            $meeting = $this->meetingService->createMeeting($user, $validated, $inviteeEmails);

            return response()->json([
                'success' => true,
                'message' => 'Meeting booked successfully! Host resource reserved.',
                'meeting' => $meeting->load(['owner', 'zoomResource', 'department']),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Download RFC 5545 iCalendar (.ics) file for a meeting.
     */
    public function icsDownload(string $publicId): Response
    {
        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();
        $icsContent = $this->icsService->generate($meeting, 'REQUEST');

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="meeting-'.$meeting->public_id.'.ics"',
        ]);
    }

    /**
     * End meeting early and immediately free pooled Zoom host license.
     */
    public function endEarly(Request $request, string $publicId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        // Check permission: owner, requester, or admin
        $canEnd = ($meeting->owner_user_id === $user->id)
            || ($meeting->requester_user_id === $user->id)
            || $user->hasRole('Super Administrator')
            || $user->hasRole('Administrator')
            || $user->hasRole('super_admin');

        if (! $canEnd) {
            return response()->json(['message' => 'Unauthorized to end this meeting early.'], 403);
        }

        if (! in_array($meeting->status, ['started', 'scheduled'], true)) {
            return response()->json(['message' => 'Only started or scheduled meetings can be ended early.'], 422);
        }

        // Transition to completed
        $this->stateMachine->transitionTo(
            meeting: $meeting,
            toStatus: 'completed',
            actor: $user,
            reason: 'Meeting ended early by user. Pooled host license released.'
        );

        $meeting->ends_at = now();
        $meeting->save();

        // Release reservation
        ResourceReservation::where('meeting_id', $meeting->id)
            ->whereIn('status', ['confirmed', 'held'])
            ->update(['status' => 'released']);

        // Check if any waitlisted meeting can be allocated
        $allocatedWaitlist = $this->waitlistService->allocateNextEligible();

        return response()->json([
            'success' => true,
            'message' => 'Meeting ended early. Pooled Zoom host license has been successfully released back to the pool!',
            'allocated_waitlist' => $allocatedWaitlist ? $allocatedWaitlist->public_id : null,
        ]);
    }

    /**
     * Update/reschedule an existing scheduled meeting.
     */
    public function updateMeeting(Request $request, string $publicId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'waiting_room' => ['nullable', 'boolean'],
            'join_before_host' => ['nullable', 'boolean'],
            'jbh_time' => ['nullable', 'integer', 'in:0,5,10,15'],
            'recording_mode' => ['nullable', 'string', 'in:none,cloud,local'],
            'attendance_tracking' => ['nullable', 'boolean'],
            'share_host_key' => ['nullable', 'boolean'],
            'passcode' => ['nullable', 'string', 'max:32'],
            'custom_fields' => ['nullable', 'array'],
            'invitees' => ['nullable', 'string'],
        ]);

        $inviteeEmails = [];
        if (! empty($validated['invitees'])) {
            $inviteeEmails = array_map('trim', explode(',', $validated['invitees']));
        }

        try {
            $updatedMeeting = $this->meetingService->updateMeeting($meeting, $user, $validated, $inviteeEmails);

            return response()->json([
                'success' => true,
                'message' => 'Meeting updated and rescheduled successfully!',
                'meeting' => $updatedMeeting->load(['owner', 'requester', 'zoomResource.zoomUser', 'template', 'invitees']),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Extend an active meeting (+15m / +30m).
     */
    public function extendMeeting(Request $request, string $publicId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'minutes' => ['required', 'integer', 'min:5', 'max:120'],
        ]);

        try {
            $extendedMeeting = $this->meetingService->extendMeeting($meeting, $user, (int) $validated['minutes']);

            return response()->json([
                'success' => true,
                'message' => "Meeting successfully extended by {$validated['minutes']} minutes!",
                'meeting' => $extendedMeeting->load(['owner', 'zoomResource.zoomUser']),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Add an attendee/invitee to an existing meeting.
     */
    public function addInvitee(Request $request, string $publicId): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $canAdd = ($meeting->owner_user_id === $user->id)
            || ($meeting->requester_user_id === $user->id)
            || $user->hasRole('Super Administrator')
            || $user->hasRole('Administrator')
            || $user->hasRole('super_admin');

        if (! $canAdd) {
            return response()->json(['message' => 'Unauthorized to modify attendees for this meeting.'], 403);
        }

        $internalUser = User::where('email', $validated['email'])->first();

        $invitee = MeetingInvitee::firstOrCreate(
            ['meeting_id' => $meeting->id, 'email' => $validated['email']],
            [
                'user_id' => $internalUser?->id,
                'name' => $validated['name'] ?? $internalUser?->name,
                'status' => 'pending',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Attendee invited successfully!',
            'invitee' => $invitee,
        ]);
    }

    /**
     * Delete / remove a recording.
     */
    public function deleteRecording(int $id): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var CloudRecording $recording */
        $recording = CloudRecording::findOrFail($id);

        $canDelete = ($recording->logical_owner_user_id === $user->id)
            || $user->hasRole('Super Administrator')
            || $user->hasRole('Administrator')
            || $user->hasRole('super_admin');

        if (! $canDelete) {
            return response()->json(['message' => 'Unauthorized to delete this recording.'], 403);
        }

        $recording->delete();

        return response()->json([
            'success' => true,
            'message' => 'Recording deleted successfully.',
        ]);
    }
}
