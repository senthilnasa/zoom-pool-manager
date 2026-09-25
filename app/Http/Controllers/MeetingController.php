<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Services\IcsCalendarService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Meetings\Services\SeriesAllocationService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class MeetingController extends Controller
{
    public function __construct(
        protected MeetingService $meetingService,
        protected SeriesAllocationService $seriesAllocationService
    ) {}

    /**
     * Display a listing of the user's meetings.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $meetings = Meeting::where(function ($query) use ($user) {
            $query->where('owner_user_id', $user->id)
                ->orWhere('requester_user_id', $user->id);
        })
            ->with(['template', 'securityProfile', 'zoomResource'])
            ->orderBy('starts_at', 'desc')
            ->paginate(15);

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Scheduled Meetings</h1> '.$meetings->pluck('title')->implode(' '),
        ]);
    }

    /**
     * Show the meeting booking request form.
     */
    public function create(Request $request): View
    {
        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Request Pooled Zoom Meeting</h1>',
        ]);
    }

    /**
     * Live AJAX preview conflict check before form submission.
     */
    public function previewConflicts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'participant_count' => ['required', 'integer', 'min:1'],
            'template_id' => ['nullable', 'integer'],
            'security_profile_id' => ['nullable', 'integer'],
            'preferred_pool_id' => ['nullable', 'integer'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var MeetingTemplate|null $template */
        $template = ! empty($validated['template_id']) ? MeetingTemplate::find($validated['template_id']) : null;
        /** @var SecurityProfile|null $profile */
        $profile = ! empty($validated['security_profile_id']) ? SecurityProfile::find($validated['security_profile_id']) : null;
        /** @var ResourcePool|null $pool */
        $pool = ! empty($validated['preferred_pool_id']) ? ResourcePool::find($validated['preferred_pool_id']) : null;

        $result = $this->meetingService->previewConflicts(
            startsAt: Carbon::parse($validated['starts_at']),
            endsAt: Carbon::parse($validated['ends_at']),
            participantCount: (int) $validated['participant_count'],
            user: $user,
            template: $template,
            securityProfile: $profile,
            pool: $pool,
            requestInput: $request->all()
        );

        return response()->json([
            'has_conflict' => $result->hasConflict,
            'conflicts' => $result->conflicts,
            'available_resource_count' => $result->availableResourceCount,
        ]);
    }

    /**
     * Store a newly created meeting request.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'agenda' => ['nullable', 'string'],
            'meeting_type' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'participant_count' => ['nullable', 'integer', 'min:1'],
            'template_id' => ['nullable', 'integer'],
            'security_profile_id' => ['nullable', 'integer'],
            'preferred_pool_id' => ['nullable', 'integer'],
            'pool_id' => ['nullable', 'integer'],
            'invitees' => ['nullable', 'string'], // comma-separated emails
            'owner_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recording_mode' => ['nullable', 'string', 'in:none,cloud,local'],
            'waiting_room' => ['nullable', 'boolean'],
            'join_before_host' => ['nullable', 'boolean'],
            'jbh_time' => ['nullable', 'integer', 'in:0,5,10,15'],
            'attendance_tracking' => ['nullable', 'boolean'],
            'share_host_key' => ['nullable', 'boolean'],
            'passcode' => ['nullable', 'string', 'max:32'],
            'custom_fields' => ['nullable', 'array'],
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
        $user = $request->user();

        // Handle field aliases
        $validated['description'] = $validated['description'] ?? $validated['agenda'] ?? null;
        $validated['preferred_pool_id'] = $validated['preferred_pool_id'] ?? $validated['pool_id'] ?? null;
        $validated['participant_count'] = (int) ($validated['participant_count'] ?? 10);
        $validated['meeting_type'] = $validated['meeting_type'] ?? 'meeting';

        // Check permission if booking on behalf of another user
        if (! empty($validated['owner_user_id']) && (int) $validated['owner_user_id'] !== $user->id) {
            $canBookOnBehalf = $user->can('meeting.book_on_behalf')
                || $user->hasRole('Super Administrator')
                || $user->hasRole('Administrator')
                || $user->hasRole('super_admin');

            if (! $canBookOnBehalf) {
                if ($request->wantsJson()) {
                    return response()->json(['message' => 'You are not authorized to book meetings on behalf of other users.'], 403);
                }
                abort(403, 'You are not authorized to book meetings on behalf of other users.');
            }
        }

        $inviteeEmails = [];
        if (! empty($validated['invitees'])) {
            $inviteeEmails = array_map('trim', explode(',', $validated['invitees']));
        }

        try {
            if ($request->boolean('is_recurring')) {
                // Build standard RFC 5545 RRULE if not explicitly provided
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

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Recurring series scheduled successfully with allocated pooled resources.',
                        'series' => $series,
                        'meeting' => $firstMeeting,
                    ]);
                }

                return redirect()->route('series.show', $series->public_id)
                    ->with('status', 'Recurring series scheduled successfully with allocated pooled resources.');
            }

            $meeting = $this->meetingService->createMeeting($user, $validated, $inviteeEmails);

            $message = $meeting->status === 'pending_approval'
                ? 'Meeting request submitted successfully. It is pending review and approval.'
                : ($meeting->status === 'waitlisted'
                    ? 'Meeting request placed on waitlist due to resource conflict.'
                    : 'Meeting booked successfully.');

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'meeting' => $meeting,
                ]);
            }

            return redirect()->route('meetings.show', $meeting->public_id)
                ->with('status', $message);
        } catch (Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withInput()->withErrors(['booking' => $e->getMessage()]);
        }
    }

    /**
     * Display meeting details.
     */
    public function show(string $publicId, Request $request): View
    {
        $meeting = Meeting::where('public_id', $publicId)
            ->with(['requester', 'owner', 'department', 'template', 'securityProfile', 'zoomResource', 'invitees', 'statusHistory'])
            ->firstOrFail();

        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>'.e($meeting->title).'</h1> <button>Cancel Meeting</button>',
        ]);
    }

    /**
     * Cancel a meeting.
     */
    public function cancel(string $publicId, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        $this->meetingService->cancelMeeting($meeting, $user, (string) $request->input('reason'));

        return redirect()->route('meetings.show', $meeting->public_id)
            ->with('status', 'Meeting has been cancelled.');
    }

    /**
     * Show the resource timeline calendar view.
     */
    public function calendar(Request $request): View
    {
        return app(SpaController::class)->index($request, [
            'fallbackHtml' => '<h1>Resource Timeline Calendar</h1>',
        ]);
    }

    /**
     * Download iCalendar (.ics) invite file for the meeting.
     */
    public function ics(string $publicId): Response
    {
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        $icsService = app(IcsCalendarService::class);
        $icsContent = $icsService->generate($meeting);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"meeting-{$meeting->public_id}.ics\"",
        ]);
    }
}
