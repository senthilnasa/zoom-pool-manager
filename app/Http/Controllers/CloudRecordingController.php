<?php

namespace App\Http\Controllers;

use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Recordings\Services\CloudRecordingService;
use App\Domain\Users\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class CloudRecordingController extends Controller
{
    public function __construct(
        protected CloudRecordingService $recordingService
    ) {}

    /**
     * List accessible cloud recordings.
     */
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = Auth::user();
        $query = CloudRecording::with(['meeting', 'resource', 'logicalOwner'])->latest('recording_start');

        // Non-admins only see recordings they logically own
        if (! $user->can('recording.view_any')) {
            $query->where(function ($q) use ($user) {
                $q->where('logical_owner_user_id', $user->id)
                    ->orWhereHas('meeting', fn ($mq) => $mq->where('requester_user_id', $user->id));
            });
        }

        if ($search = $request->query('search')) {
            $query->where('topic', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('status', (string) $status);
        }

        $recordings = $query->paginate(15)->withQueryString();

        return view('recordings.index', compact('recordings', 'search', 'status'));
    }

    /**
     * View recording details.
     */
    public function show(string $public_id): View
    {
        /** @var User $user */
        $user = Auth::user();
        $recording = CloudRecording::with(['meeting', 'resource', 'logicalOwner', 'files', 'accessLogs.user'])
            ->where('public_id', $public_id)
            ->firstOrFail();

        // Authorization check
        $isOwner = $recording->logical_owner_user_id === $user->id;
        $isMeetingRequester = $recording->meeting && $recording->meeting->requester_user_id === $user->id;
        $canViewAny = $user->can('recording.view_any');
        $canViewDept = $user->can('recording.view') && $recording->meeting && $user->department_id === $recording->meeting->department_id;

        if (! $isOwner && ! $isMeetingRequester && ! $canViewAny && ! $canViewDept) {
            abort(403, 'Unauthorized access to recording.');
        }

        return view('recordings.show', compact('recording'));
    }

    /**
     * Authorized playback redirect.
     */
    public function play(Request $request, string $public_id): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $recording = CloudRecording::with('meeting')
            ->where('public_id', $public_id)
            ->firstOrFail();

        $playUrl = $this->recordingService->authorizeAndGetPlayUrl(
            $recording,
            $user,
            (string) $request->ip(),
            $request->userAgent()
        );

        return redirect()->away($playUrl);
    }
}
