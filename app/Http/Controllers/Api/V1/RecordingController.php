<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Recordings\Services\CloudRecordingService;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordingController extends Controller
{
    public function __construct(
        protected CloudRecordingService $recordingService
    ) {}

    /**
     * List recordings accessible to the authenticated API user.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = CloudRecording::with(['meeting', 'logicalOwner', 'files']);

        if (! $user->can('recordings.view-all') && ! $user->hasRole(['Super Administrator', 'IT Administrator'])) {
            $query->where('logical_owner_user_id', $user->id);
        }

        $recordings = $query->orderBy('recording_start', 'desc')->paginate(20);

        return response()->json($recordings);
    }

    /**
     * Get cloud recording details and playback access URL.
     */
    public function show(string $publicId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var CloudRecording $recording */
        $recording = CloudRecording::where('public_id', $publicId)
            ->with(['meeting', 'files'])
            ->firstOrFail();

        if (
            ! $user->can('recordings.view-all')
            && ! $user->hasRole(['Super Administrator', 'IT Administrator'])
            && $recording->logical_owner_user_id !== $user->id
        ) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You are not authorized to access this recording.',
            ], 403);
        }

        $playbackUrl = $this->recordingService->authorizeAndGetPlayUrl(
            $recording,
            $user,
            (string) ($request->ip() ?? '127.0.0.1'),
            $request->userAgent()
        );

        return response()->json([
            'recording' => [
                'public_id' => $recording->public_id,
                'topic' => $recording->topic,
                'recording_start' => $recording->recording_start?->toIso8601String(),
                'recording_end' => $recording->recording_end?->toIso8601String(),
                'duration_minutes' => $recording->duration_minutes,
                'file_size_bytes' => $recording->file_size_bytes,
                'playback_url' => $playbackUrl,
                'passcode' => $recording->passcode,
                'status' => $recording->status,
                'files' => $recording->files->map(fn ($f) => [
                    'file_type' => $f->file_type,
                    'file_extension' => $f->file_extension,
                    'file_size_bytes' => $f->file_size_bytes,
                ]),
            ],
        ]);
    }
}
