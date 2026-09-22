<?php

namespace App\Http\Controllers;

use App\Domain\Communication\Services\IcsCalendarService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use App\Domain\Zoom\Models\ZoomResource;
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
        protected MeetingService $meetingService
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

        return view('meetings.index', [
            'meetings' => $meetings,
            'user' => $user,
        ]);
    }

    /**
     * Show the meeting booking request form.
     */
    public function create(Request $request): View
    {
        $templates = MeetingTemplate::where('is_active', true)->get();
        $profiles = SecurityProfile::all();
        $pools = ResourcePool::where('is_active', true)->get();

        return view('meetings.create', [
            'templates' => $templates,
            'profiles' => $profiles,
            'pools' => $pools,
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
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'meeting_type' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'participant_count' => ['required', 'integer', 'min:1'],
            'template_id' => ['nullable', 'integer'],
            'security_profile_id' => ['nullable', 'integer'],
            'preferred_pool_id' => ['nullable', 'integer'],
            'invitees' => ['nullable', 'string'], // comma-separated emails
        ]);

        /** @var User $user */
        $user = $request->user();

        $inviteeEmails = [];
        if (! empty($validated['invitees'])) {
            $inviteeEmails = array_map('trim', explode(',', $validated['invitees']));
        }

        try {
            $meeting = $this->meetingService->createMeeting($user, $validated, $inviteeEmails);

            return redirect()->route('meetings.show', $meeting->public_id)
                ->with('status', 'Meeting booked successfully.');
        } catch (Exception $e) {
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

        return view('meetings.show', [
            'meeting' => $meeting,
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
        $resources = ZoomResource::where('managed', true)->with('zoomUser')->get();

        return view('meetings.calendar', [
            'resources' => $resources,
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
