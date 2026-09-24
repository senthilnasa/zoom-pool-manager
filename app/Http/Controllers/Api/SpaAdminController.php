<?php

namespace App\Http\Controllers\Api;

use App\Domain\Attendance\Models\MeetingAttendance;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Auth\Services\RoleManagementService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Operations\Models\DataExportRequest;
use App\Domain\Operations\Services\PrivacyAndRetentionService;
use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Scheduling\Models\BlackoutPeriod;
use App\Domain\Scheduling\Models\BookingPolicy;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\MeetingApproval;
use App\Domain\Workflow\Models\Quota;
use App\Domain\Workflow\Models\WaitlistEntry;
use App\Domain\Workflow\Services\WaitlistService;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomResource;
use App\Domain\Zoom\Services\ZoomUserSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class SpaAdminController extends Controller
{
    public function __construct(
        protected AuditService $auditService,
        protected PrivacyAndRetentionService $privacyService,
        protected WaitlistService $waitlistService,
        protected ZoomUserSyncService $zoomUserSyncService
    ) {}

    // ==========================================
    // 1. RESOURCE POOLS & ZOOM RESOURCES
    // ==========================================

    public function pools(): JsonResponse
    {
        $pools = ResourcePool::with(['resources.zoomUser'])
            ->withCount('resources')
            ->orderBy('name')
            ->get();

        return response()->json($pools);
    }

    public function storePool(Request $request): JsonResponse
    {
        /** @var array{id?: ?int, name: string, code: string, description?: ?string, pool_strategy: string, is_emergency_pool?: bool, is_active?: bool, resource_ids?: array<int, int>} $validated */
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:resource_pools,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'pool_strategy' => 'required|string|in:least_hours_today,priority,random,round_robin',
            'is_emergency_pool' => 'boolean',
            'is_active' => 'boolean',
            'resource_ids' => 'nullable|array',
            'resource_ids.*' => 'integer|exists:zoom_resources,id',
        ]);

        $resourceIds = $validated['resource_ids'] ?? null;
        unset($validated['resource_ids']);

        if (! empty($validated['id'])) {
            $pool = ResourcePool::findOrFail($validated['id']);
            $pool->update($validated);
        } else {
            $pool = ResourcePool::create($validated);
        }

        if ($resourceIds !== null) {
            $pool->resources()->sync($resourceIds);
        }

        return response()->json([
            'success' => true,
            'pool' => $pool->load(['resources.zoomUser']),
        ]);
    }

    public function togglePool(string $id): JsonResponse
    {
        $pool = ResourcePool::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
        $pool->update(['is_active' => ! $pool->is_active]);

        return response()->json(['success' => true, 'pool' => $pool]);
    }

    public function resources(): JsonResponse
    {
        $resources = ZoomResource::with(['zoomUser', 'pools'])->orderBy('id')->get();

        return response()->json($resources);
    }

    public function toggleResource(string $id): JsonResponse
    {
        $resource = ZoomResource::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
        $resource->update(['managed' => ! $resource->managed]);

        return response()->json(['success' => true, 'resource' => $resource]);
    }

    public function syncZoomUsers(): JsonResponse
    {
        $result = $this->zoomUserSyncService->syncUsers();

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function assignResourcePools(Request $request, string $id): JsonResponse
    {
        /** @var array{pool_ids?: array<int, int>} $validated */
        $validated = $request->validate([
            'pool_ids' => 'nullable|array',
            'pool_ids.*' => 'integer|exists:resource_pools,id',
        ]);

        $resource = ZoomResource::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
        $resource->pools()->sync($validated['pool_ids'] ?? []);

        return response()->json([
            'success' => true,
            'message' => 'Resource pools assigned successfully.',
            'resource' => $resource->fresh(['pools', 'zoomUser']),
        ]);
    }

    // ==========================================
    // 2. USERS & DEPARTMENTS MANAGEMENT
    // ==========================================

    /**
     * Fast typeahead search for 10,000+ employees across name, email, and designation.
     */
    public function searchUsers(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $limit = min(max($request->integer('limit', 30), 5), 100);

        $query = User::with('department:id,name,code')
            ->where('is_active', true)
            ->select('id', 'public_id', 'name', 'email', 'designation', 'department_id');

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('designation', 'like', "%{$q}%");
            });
        }

        if ($request->filled('include_id')) {
            $includeId = $request->integer('include_id');
            /** @var User|null $includedUser */
            $includedUser = User::with('department:id,name,code')
                ->select('id', 'public_id', 'name', 'email', 'designation', 'department_id')
                ->find($includeId);
        } else {
            $includedUser = null;
        }

        $users = $query->orderBy('name')->limit($limit)->get();

        if ($includedUser && ! $users->contains('id', $includedUser->id)) {
            $users->prepend($includedUser);
        }

        return response()->json($users);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::with(['department', 'roles'])->orderBy('name');

        if ($request->filled('search')) {
            $search = '%'.$request->query('search').'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search)
                    ->orWhere('designation', 'like', $search);
            });
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('role')) {
            $query->role((string) $request->query('role'));
        }

        $perPage = (int) $request->query('per_page', 25);
        if (! in_array($perPage, [10, 25, 100], true)) {
            $perPage = 25;
        }

        $users = $query->paginate($perPage);

        $departments = Department::orderBy('name')->get();
        $roles = Role::orderBy('name')->pluck('name');

        return response()->json([
            'users' => $users,
            'departments' => $departments,
            'roles' => $roles,
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:users,id',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'designation' => 'nullable|string|max:150',
            'department_id' => 'nullable|integer|exists:departments,id',
            'role' => 'nullable|string',
            'password' => 'nullable|string|min:8',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['id'])) {
            $user = User::findOrFail($validated['id']);
            $user->name = $validated['name'];
            $user->email = $validated['email'];
            $user->designation = $validated['designation'] ?? null;
            $user->department_id = $validated['department_id'] ?? null;
            $user->is_active = $validated['is_active'] ?? true;
            if (! empty($validated['password'])) {
                $user->password = Hash::make($validated['password']);
            }
            $user->save();
        } else {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'designation' => $validated['designation'] ?? null,
                'department_id' => $validated['department_id'] ?? null,
                'password' => Hash::make($validated['password'] ?? 'Temporary@Pass123'),
                'is_active' => $validated['is_active'] ?? true,
            ]);
        }

        if (! empty($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        return response()->json(['success' => true, 'user' => $user->load(['department', 'roles'])]);
    }

    public function toggleUser(string $id): JsonResponse
    {
        $user = User::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
        $user->update(['is_active' => ! $user->is_active]);

        return response()->json(['success' => true, 'user' => $user]);
    }

    public function userProfile(string $id, RoleManagementService $roleService): JsonResponse
    {
        $user = User::where('public_id', $id)
            ->orWhere('id', $id)
            ->with(['department', 'roles'])
            ->firstOrFail();

        // Query all meetings requested or owned by this user
        $meetingsQuery = Meeting::where(function ($q) use ($user) {
            $q->where('requester_user_id', $user->id)
                ->orWhere('owner_user_id', $user->id);
        });

        $totalRequests = (clone $meetingsQuery)->count();
        $scheduledRequests = (clone $meetingsQuery)->whereIn('status', ['scheduled', 'allocating'])->count();
        $inProgressRequests = (clone $meetingsQuery)->whereIn('status', ['started', 'in_progress'])->count();
        $completedRequests = (clone $meetingsQuery)->where('status', 'completed')->count();
        $cancelledRequests = (clone $meetingsQuery)->where('status', 'cancelled')->count();

        // Calculate total hours of booked pool time
        $meetingsForDuration = (clone $meetingsQuery)->select('starts_at', 'ends_at')->get();
        $totalMinutes = 0;
        foreach ($meetingsForDuration as $m) {
            if ($m->starts_at && $m->ends_at) {
                $totalMinutes += max(0, $m->starts_at->diffInMinutes($m->ends_at));
            }
        }
        $totalHours = round($totalMinutes / 60, 1);

        // Approvals for meetings requested by this user
        $userMeetingIds = (clone $meetingsQuery)->pluck('id');
        $submittedApprovalsQuery = MeetingApproval::whereIn('meeting_id', $userMeetingIds);
        $approvalsSubmitted = (clone $submittedApprovalsQuery)->count();
        $approvalsPending = (clone $submittedApprovalsQuery)->where('decision', 'pending')->count();
        $approvalsApproved = (clone $submittedApprovalsQuery)->where('decision', 'approved')->count();
        $approvalsRejected = (clone $submittedApprovalsQuery)->where('decision', 'rejected')->count();

        // Approvals assigned to this user to review (if user is an approver)
        $assignedApprovalsCount = MeetingApproval::where('approver_user_id', $user->id)->count();
        $assignedApprovalsPending = MeetingApproval::where('approver_user_id', $user->id)->where('decision', 'pending')->count();

        // Cloud Recordings
        $recordings = CloudRecording::where('logical_owner_user_id', $user->id)
            ->orWhereIn('meeting_id', $userMeetingIds)
            ->with('meeting')
            ->latest()
            ->limit(50)
            ->get();
        $recordingsCount = $recordings->count();
        $recordingsBytes = $recordings->sum('file_size_bytes');
        $recordingsMb = round($recordingsBytes / (1024 * 1024), 2);

        // Attendance participant records across meetings requested/owned
        $attendanceSessionsCount = MeetingAttendance::whereIn('meeting_id', $userMeetingIds)->count();

        // Quotas & Usage (User scope or Department scope)
        $quota = Quota::where(function ($q) use ($user) {
            $q->where(function ($sub) use ($user) {
                $sub->where('scope_type', 'user')->where('scope_id', $user->id);
            });
            if ($user->department_id) {
                $q->orWhere(function ($sub) use ($user) {
                    $sub->where('scope_type', 'department')->where('scope_id', $user->department_id);
                });
            }
        })->with('usages')->first();

        $currentYear = (int) date('Y');
        $currentMonth = (int) date('n');
        $quotaUsage = null;
        if ($quota) {
            $quotaUsage = $quota->usages()
                ->where('period_year', $currentYear)
                ->where('period_month', $currentMonth)
                ->first();
        }

        // Recent 50 meetings
        $recentMeetings = (clone $meetingsQuery)
            ->with(['zoomResource', 'department', 'template'])
            ->orderBy('starts_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'public_id' => $m->public_id,
                    'title' => $m->title,
                    'starts_at' => $m->starts_at?->toIso8601String(),
                    'ends_at' => $m->ends_at?->toIso8601String(),
                    'duration_minutes' => $m->duration_minutes,
                    'status' => $m->status,
                    'join_url' => $m->join_url,
                    'zoom_meeting_id' => $m->zoom_meeting_id,
                    'participant_count' => $m->participant_count,
                    'resource_name' => $m->zoomResource?->name ?? 'Pooled Host',
                    'department_name' => $m->department?->name ?? '—',
                ];
            });

        // Recent Approvals list
        $recentApprovals = MeetingApproval::whereIn('meeting_id', $userMeetingIds)
            ->with(['meeting', 'approver'])
            ->latest()
            ->limit(25)
            ->get();

        // Effective permissions
        $permissionData = $roleService->getUserPermissions($user);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
                'department_id' => $user->department_id,
                'department' => $user->department,
                'is_active' => (bool) $user->is_active,
                'mfa_enabled' => (bool) $user->mfa_enabled,
                'timezone' => $user->timezone ?? 'Asia/Kolkata',
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'roles' => $user->roles->pluck('name'),
            ],
            'summary' => [
                'total_requests' => $totalRequests,
                'scheduled_requests' => $scheduledRequests,
                'in_progress_requests' => $inProgressRequests,
                'completed_requests' => $completedRequests,
                'cancelled_requests' => $cancelledRequests,
                'total_hours' => $totalHours,
                'total_minutes' => $totalMinutes,
                'approvals_submitted' => $approvalsSubmitted,
                'approvals_pending' => $approvalsPending,
                'approvals_approved' => $approvalsApproved,
                'approvals_rejected' => $approvalsRejected,
                'assigned_approvals_count' => $assignedApprovalsCount,
                'assigned_approvals_pending' => $assignedApprovalsPending,
                'recordings_count' => $recordingsCount,
                'recordings_mb' => $recordingsMb,
                'attendance_sessions_count' => $attendanceSessionsCount,
            ],
            'quota' => $quota ? [
                'scope_type' => $quota->scope_type,
                'max_meetings_per_month' => $quota->max_meetings_per_month,
                'max_hours_per_month' => $quota->max_hours_per_month,
                'current_month_meetings' => $quotaUsage?->meetings_count ?? 0,
                'current_month_hours' => $quotaUsage ? round($quotaUsage->minutes_used / 60, 1) : 0,
            ] : null,
            'meetings' => $recentMeetings,
            'approvals' => $recentApprovals,
            'recordings' => $recordings,
            'permissions' => $permissionData['permissions'],
            'is_super_admin' => $permissionData['is_super_admin'],
        ]);
    }

    public function departments(): JsonResponse
    {
        $departments = Department::withCount('users')->orderBy('name')->get();

        return response()->json($departments);
    }

    public function storeDepartment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:departments,id',
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['id'])) {
            $dept = Department::findOrFail($validated['id']);
            $dept->update($validated);
        } else {
            $dept = Department::create($validated);
        }

        return response()->json(['success' => true, 'department' => $dept]);
    }

    public function deleteDepartment(string $id): JsonResponse
    {
        $dept = Department::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
        $dept->delete();

        return response()->json(['success' => true]);
    }

    // ==========================================
    // 3. MEETING TEMPLATES & SECURITY PROFILES
    // ==========================================

    public function templates(): JsonResponse
    {
        $templates = MeetingTemplate::with('securityProfile')->orderBy('name')->get();
        $profiles = SecurityProfile::all();

        return response()->json([
            'templates' => $templates,
            'profiles' => $profiles,
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:meeting_templates,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'security_profile_id' => 'required|integer|exists:security_profiles,id',
            'default_duration_minutes' => 'required|integer|min:15|max:480',
            'max_duration_minutes' => 'required|integer|min:15|max:480',
            'max_participants' => 'required|integer|min:2|max:1000',
            'requires_approval' => 'boolean',
            'recording_mode' => 'required|string|in:none,local,cloud',
            'ai_companion_policy' => 'required|string|in:ALLOWED,DISABLED',
            'series_mode' => 'required|string|in:SINGLE_RESOURCE,PER_OCCURRENCE',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['id'])) {
            $tpl = MeetingTemplate::findOrFail($validated['id']);
            $tpl->update($validated);
        } else {
            $tpl = MeetingTemplate::create($validated);
        }

        return response()->json(['success' => true, 'template' => $tpl->load('securityProfile')]);
    }

    public function securityProfiles(): JsonResponse
    {
        $profiles = SecurityProfile::orderBy('name')->get();

        return response()->json($profiles);
    }

    public function storeSecurityProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:security_profiles,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:100',
            'is_default' => 'boolean',
            'settings' => 'required|array',
        ]);

        if (! empty($validated['id'])) {
            $profile = SecurityProfile::findOrFail($validated['id']);
            $profile->update($validated);
        } else {
            $profile = SecurityProfile::create($validated);
        }

        return response()->json(['success' => true, 'profile' => $profile]);
    }

    // ==========================================
    // 4. BLACKOUT PERIODS & BOOKING POLICIES
    // ==========================================

    public function blackouts(): JsonResponse
    {
        $blackouts = BlackoutPeriod::with('department')->orderBy('starts_at', 'desc')->get();
        $departments = Department::orderBy('name')->get();

        return response()->json([
            'blackouts' => $blackouts,
            'departments' => $departments,
        ]);
    }

    public function storeBlackout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|string|in:holiday,maintenance,exam,institutional',
            'department_id' => 'nullable|integer|exists:departments,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        $blackout = BlackoutPeriod::create($validated);

        return response()->json(['success' => true, 'blackout' => $blackout->load('department')]);
    }

    public function deleteBlackout(string $id): JsonResponse
    {
        $blackout = BlackoutPeriod::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
        $blackout->delete();

        return response()->json(['success' => true]);
    }

    public function policies(): JsonResponse
    {
        $policies = BookingPolicy::with('department')->orderBy('name')->get();
        $departments = Department::orderBy('name')->get();

        return response()->json([
            'policies' => $policies,
            'departments' => $departments,
        ]);
    }

    public function storePolicy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'nullable|integer|exists:booking_policies,id',
            'name' => 'required|string|max:100',
            'department_id' => 'nullable|integer|exists:departments,id',
            'min_notice_hours' => 'required|integer|min:0|max:168',
            'max_advance_days' => 'required|integer|min:1|max:365',
            'min_buffer_minutes' => 'required|integer|min:0|max:60',
            'default_buffer_minutes' => 'required|integer|min:0|max:60',
            'max_duration_minutes' => 'required|integer|min:15|max:720',
            'is_active' => 'boolean',
        ]);

        if (! empty($validated['id'])) {
            $policy = BookingPolicy::findOrFail($validated['id']);
            $policy->update($validated);
        } else {
            $policy = BookingPolicy::create($validated);
        }

        return response()->json(['success' => true, 'policy' => $policy->load('department')]);
    }

    // ==========================================
    // 5. WAITLIST QUEUE
    // ==========================================

    public function waitlist(): JsonResponse
    {
        $entries = WaitlistEntry::with(['meeting.owner', 'meeting.requester'])
            ->orderBy('priority')
            ->orderBy('created_at')
            ->get();

        return response()->json($entries);
    }

    public function promoteWaitlist(string $id): JsonResponse
    {
        $entry = WaitlistEntry::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
        $meeting = $this->waitlistService->allocateNextEligible();

        return response()->json([
            'success' => true,
            'entry' => $entry->fresh(['meeting']),
            'allocated_meeting' => $meeting,
        ]);
    }

    public function cancelWaitlist(string $id): JsonResponse
    {
        $entry = WaitlistEntry::where('public_id', $id)->orWhere('id', $id)->firstOrFail();
        $entry->update(['status' => 'cancelled']);

        return response()->json(['success' => true]);
    }

    // ==========================================
    // 6. CRYPTOGRAPHIC AUDIT LOG
    // ==========================================

    public function auditLogs(Request $request): JsonResponse
    {
        $query = AuditLog::with('actor')->orderBy('id', 'desc');

        if ($request->filled('event')) {
            $query->where('event', 'like', '%'.$request->query('event').'%');
        }

        if ($request->filled('actor_user_id')) {
            $query->where('actor_user_id', $request->integer('actor_user_id'));
        }

        $logs = $query->paginate($request->integer('per_page', 25));

        return response()->json($logs);
    }

    public function verifyAuditChain(): JsonResponse
    {
        $result = $this->auditService->verifyChainIntegrity();

        return response()->json($result);
    }

    // ==========================================
    // 7. PRIVACY, COMPLIANCE & RETENTION (GDPR)
    // ==========================================

    public function privacyStats(): JsonResponse
    {
        $userCount = User::count();
        $activeExports = DataExportRequest::where('status', 'completed')
            ->where('expires_at', '>', now())
            ->count();
        $totalExports = DataExportRequest::count();
        $auditLogCount = AuditLog::count();

        $recentExports = DataExportRequest::with(['user', 'requestedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'user_count' => $userCount,
            'active_exports' => $activeExports,
            'total_exports' => $totalExports,
            'audit_log_count' => $auditLogCount,
            'recent_exports' => $recentExports,
        ]);
    }

    public function exportUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $targetUser = User::findOrFail($validated['user_id']);

        $export = $this->privacyService->exportUserData($targetUser, $actor);

        return response()->json(['success' => true, 'export' => $export]);
    }

    public function anonymizeUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'reason' => 'required|string|min:5|max:255',
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        $targetUser = User::findOrFail($validated['user_id']);

        if ($targetUser->hasRole('Super Administrator') || $targetUser->hasRole('super_admin')) {
            return response()->json(['success' => false, 'message' => 'Super Administrator accounts cannot be anonymized.'], 422);
        }

        $this->privacyService->anonymizeUser($targetUser, $actor, $validated['reason']);

        return response()->json(['success' => true, 'message' => "User account for {$targetUser->id} has been permanently anonymized."]);
    }

    public function purgeRetention(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'retention_days' => 'required|integer|min:30|max:1825',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        $days = (int) $validated['retention_days'];
        $config = [
            'zoom_webhook_events' => $days,
            'notifications' => $days,
            'email_deliveries' => $days,
            'recording_access_logs' => $days,
        ];

        $purged = $this->privacyService->purgeOldRecords($config, $actor);

        return response()->json(['success' => true, 'purged' => $purged]);
    }
}
