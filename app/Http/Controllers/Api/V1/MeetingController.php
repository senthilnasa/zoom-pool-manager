<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Api\Services\OutboundWebhookService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MeetingController extends Controller
{
    public function __construct(
        protected MeetingService $meetingService,
        protected OutboundWebhookService $webhookService
    ) {}

    /**
     * List meetings accessible to the authenticated API user.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Meeting::with(['owner', 'requester', 'department', 'zoomResource']);

        if (! $user->can('meetings.view-all') && ! $user->hasRole(['Super Administrator', 'IT Administrator'])) {
            $query->where(function ($q) use ($user) {
                $q->where('requester_user_id', $user->id)
                    ->orWhere('owner_user_id', $user->id);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->where('starts_at', '>=', Carbon::parse((string) $request->query('from')));
        }

        if ($request->filled('to')) {
            $query->where('ends_at', '<=', Carbon::parse((string) $request->query('to')));
        }

        $meetings = $query->orderBy('starts_at', 'asc')->paginate(20);

        return response()->json($meetings);
    }

    /**
     * Create and book a new meeting programmatically.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'meeting_type' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'participant_count' => ['required', 'integer', 'min:1'],
            'template_id' => ['nullable', 'integer'],
            'security_profile_id' => ['nullable', 'integer'],
            'preferred_pool_id' => ['nullable', 'integer'],
            'invitees' => ['nullable', 'array'],
            'invitees.*' => ['email'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $validated['meeting_type'] = $validated['meeting_type'] ?? 'scheduled';
        $inviteeEmails = $validated['invitees'] ?? [];

        try {
            $meeting = $this->meetingService->createMeeting($user, $validated, $inviteeEmails);

            $payload = [
                'public_id' => $meeting->public_id,
                'title' => $meeting->title,
                'status' => $meeting->status,
                'starts_at' => $meeting->starts_at->toIso8601String(),
                'ends_at' => $meeting->ends_at->toIso8601String(),
                'join_url' => $meeting->join_url,
                'resource' => $meeting->zoomResource ? [
                    'public_id' => $meeting->zoomResource->public_id,
                    'name' => $meeting->zoomResource->name,
                ] : null,
            ];

            $this->webhookService->dispatch('meeting.created', [
                'meeting' => $payload,
                'actor' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'Meeting created successfully.',
                'meeting' => $payload,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Unprocessable Entity',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get meeting details by public ID.
     */
    public function show(string $publicId, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)
            ->with(['owner', 'requester', 'department', 'zoomResource', 'invitees'])
            ->firstOrFail();

        if (
            ! $user->can('meetings.view-all')
            && ! $user->hasRole(['Super Administrator', 'IT Administrator'])
            && $meeting->requester_user_id !== $user->id
            && $meeting->owner_user_id !== $user->id
        ) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You are not authorized to view this meeting.',
            ], 403);
        }

        return response()->json([
            'meeting' => [
                'public_id' => $meeting->public_id,
                'title' => $meeting->title,
                'description' => $meeting->description,
                'status' => $meeting->status,
                'starts_at' => $meeting->starts_at->toIso8601String(),
                'ends_at' => $meeting->ends_at->toIso8601String(),
                'buffer_minutes' => $meeting->buffer_minutes,
                'participant_count' => $meeting->participant_count,
                'join_url' => $meeting->join_url,
                'zoom_meeting_id' => $meeting->zoom_meeting_id,
                'resource' => $meeting->zoomResource ? [
                    'public_id' => $meeting->zoomResource->public_id,
                    'name' => $meeting->zoomResource->name,
                    'capacity' => $meeting->zoomResource->participant_capacity,
                ] : null,
                'owner' => [
                    'name' => $meeting->owner->name,
                    'email' => $meeting->owner->email,
                ],
                'invitees' => $meeting->invitees->pluck('email'),
                'created_at' => $meeting->created_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Cancel a meeting.
     */
    public function cancel(string $publicId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        /** @var User $user */
        $user = $request->user();

        /** @var Meeting $meeting */
        $meeting = Meeting::where('public_id', $publicId)->firstOrFail();

        if (
            ! $user->can('meetings.manage-all')
            && ! $user->hasRole(['Super Administrator', 'IT Administrator'])
            && $meeting->requester_user_id !== $user->id
            && $meeting->owner_user_id !== $user->id
        ) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'You are not authorized to cancel this meeting.',
            ], 403);
        }

        try {
            $reason = $validated['reason'] ?? 'Cancelled via API';
            $this->meetingService->cancelMeeting($meeting, $user, $reason);

            $this->webhookService->dispatch('meeting.cancelled', [
                'meeting' => [
                    'public_id' => $meeting->public_id,
                    'title' => $meeting->title,
                    'status' => $meeting->status,
                    'cancellation_reason' => $reason,
                ],
                'actor' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
                'timestamp' => now()->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'Meeting cancelled successfully.',
                'meeting' => [
                    'public_id' => $meeting->public_id,
                    'status' => $meeting->status,
                    'cancellation_reason' => $meeting->cancellation_reason,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'error' => 'Unprocessable Entity',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
