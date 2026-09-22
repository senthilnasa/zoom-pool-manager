<?php

namespace App\Http\Controllers;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Models\MeetingSeries;
use App\Domain\Meetings\Services\OccurrenceDetachmentService;
use App\Domain\Meetings\Services\SeriesAllocationService;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MeetingSeriesController extends Controller
{
    public function __construct(
        protected SeriesAllocationService $seriesAllocationService,
        protected OccurrenceDetachmentService $detachmentService
    ) {}

    /**
     * Display listing of recurring series.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        $series = MeetingSeries::where(function ($query) use ($user) {
            $query->where('owner_user_id', $user->id)
                ->orWhere('requester_user_id', $user->id);
        })
            ->with(['zoomResource', 'department'])
            ->withCount('meetings')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('series.index', [
            'series' => $series,
        ]);
    }

    /**
     * Show form for creating a recurring series.
     */
    public function create(Request $request): View
    {
        $templates = MeetingTemplate::where('is_active', true)->get();
        $profiles = SecurityProfile::all();
        $pools = ResourcePool::where('is_active', true)->get();

        return view('series.create', [
            'templates' => $templates,
            'profiles' => $profiles,
            'pools' => $pools,
        ]);
    }

    /**
     * Store new recurring series and allocate resources.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'meeting_type' => ['required', 'string'],
            'rrule' => ['required', 'string'],
            'starts_at' => ['required', 'date'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'participant_count' => ['required', 'integer', 'min:1'],
            'series_mode' => ['required', 'string', 'in:SINGLE_RESOURCE,SPLIT_WHEN_NEEDED,PER_OCCURRENCE'],
            'template_id' => ['nullable', 'integer'],
            'security_profile_id' => ['nullable', 'integer'],
            'preferred_pool_id' => ['nullable', 'integer'],
            'invitees' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $inviteeEmails = [];
        if (! empty($validated['invitees'])) {
            $inviteeEmails = array_map('trim', explode(',', $validated['invitees']));
        }

        try {
            $series = $this->seriesAllocationService->createSeries($user, $validated, $inviteeEmails);

            return redirect()->route('series.show', $series->public_id)
                ->with('status', 'Recurring series scheduled successfully with allocated pooled resources.');
        } catch (Exception $e) {
            return back()->withInput()->withErrors(['series' => $e->getMessage()]);
        }
    }

    /**
     * Display details and occurrences of a recurring series.
     */
    public function show(string $publicId, Request $request): View
    {
        $series = MeetingSeries::where('public_id', $publicId)
            ->with(['requester', 'owner', 'department', 'zoomResource', 'meetings' => function ($q) {
                $q->orderBy('starts_at', 'asc')->with(['zoomResource']);
            }])
            ->firstOrFail();

        return view('series.show', [
            'series' => $series,
        ]);
    }

    /**
     * Detach an individual occurrence from the recurring series.
     */
    public function detachOccurrence(string $seriesPublicId, string $meetingPublicId, Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $series = MeetingSeries::where('public_id', $seriesPublicId)->firstOrFail();
        $meeting = Meeting::where('public_id', $meetingPublicId)->where('series_id', $series->id)->firstOrFail();

        try {
            $this->detachmentService->detachOccurrence($meeting, $user);

            return redirect()->route('meetings.show', $meeting->public_id)
                ->with('status', 'Occurrence detached from series. It can now be managed and rescheduled independently.');
        } catch (Exception $e) {
            return back()->withErrors(['detach' => $e->getMessage()]);
        }
    }

    /**
     * Cancel an entire recurring series.
     */
    public function cancel(string $publicId, Request $request): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $series = MeetingSeries::where('public_id', $publicId)->firstOrFail();

        $this->detachmentService->cancelSeries($series, $user, (string) $request->input('reason'));

        return redirect()->route('series.show', $series->public_id)
            ->with('status', 'Entire recurring series and future occurrences have been cancelled.');
    }
}
