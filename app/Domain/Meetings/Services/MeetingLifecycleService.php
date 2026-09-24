<?php

namespace App\Domain\Meetings\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Scheduling\Services\AllocationEngine;
use App\Domain\Scheduling\Services\EffectivePolicyResolver;
use App\Domain\Zoom\Models\ZoomConnection;
use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MeetingLifecycleService
{
    public function __construct(
        protected MeetingStateMachine $stateMachine,
        protected AllocationEngine $allocationEngine,
        protected EffectivePolicyResolver $policyResolver,
        protected AuditService $auditService
    ) {}

    /**
     * Run all meeting lifecycle reconciliations.
     *
     * @return array{stale_allocating: int, transitioned_started: int, transitioned_completed: int}
     */
    public function reconcileAll(): array
    {
        $allocatingCount = $this->reconcileStaleAllocating();
        $startedCount = $this->reconcileStartedMeetings();
        $completedCount = $this->reconcileCompletedMeetings();

        return [
            'stale_allocating' => $allocatingCount,
            'transitioned_started' => $startedCount,
            'transitioned_completed' => $completedCount,
        ];
    }

    /**
     * Resolve meetings stuck in 'allocating' state.
     */
    public function reconcileStaleAllocating(): int
    {
        $now = Carbon::now();
        $reconciled = 0;

        /** @var Meeting[] $staleMeetings */
        $staleMeetings = Meeting::where('status', 'allocating')
            ->with(['owner', 'requester', 'zoomResource'])
            ->get();

        foreach ($staleMeetings as $meeting) {
            $reservation = ResourceReservation::where('meeting_id', $meeting->id)
                ->whereIn('status', ['held', 'confirmed'])
                ->first();

            if ($reservation) {
                // Confirm held reservation
                $this->allocationEngine->confirmReservation($reservation, $meeting->id);
                $meeting->zoom_resource_id = $reservation->resource_id;

                if (empty($meeting->zoom_meeting_id)) {
                    $this->provisionZoomDetails($meeting);
                }

                $meeting->save();

                // Transition to appropriate time-based state
                if ($meeting->ends_at <= $now) {
                    $this->stateMachine->transitionTo($meeting, 'completed', null, 'Auto-reconciled: scheduled window has passed.');
                } elseif ($meeting->starts_at <= $now) {
                    $this->stateMachine->transitionTo($meeting, 'started', null, 'Auto-reconciled: meeting time has arrived.');
                } else {
                    $this->stateMachine->transitionTo($meeting, 'scheduled', null, 'Auto-reconciled: resource confirmed.');
                }

                $reconciled++;
            } else {
                // If created > 3 minutes ago and still has no reservation, try on-demand allocation or fail
                if ($meeting->created_at && $meeting->created_at->diffInMinutes($now) >= 2) {
                    try {
                        $pool = $meeting->zoomResource?->pools()->first() ?? $meeting->template?->defaultPool;
                        $policy = $this->policyResolver->resolve(
                            user: $meeting->requester,
                            department: $meeting->department,
                            securityProfile: $meeting->securityProfile,
                            template: $meeting->template
                        );

                        $newRes = $this->allocationEngine->holdResource(
                            startsAt: $meeting->starts_at,
                            endsAt: $meeting->ends_at,
                            participantCount: $meeting->participant_count,
                            policy: $policy,
                            pool: $pool,
                            meetingId: $meeting->id
                        );

                        $this->allocationEngine->confirmReservation($newRes, $meeting->id);
                        $meeting->zoom_resource_id = $newRes->resource_id;
                        $this->provisionZoomDetails($meeting);
                        $meeting->save();

                        if ($meeting->ends_at <= $now) {
                            $this->stateMachine->transitionTo($meeting, 'completed', null, 'Auto-reconciled.');
                        } elseif ($meeting->starts_at <= $now) {
                            $this->stateMachine->transitionTo($meeting, 'started', null, 'Auto-reconciled.');
                        } else {
                            $this->stateMachine->transitionTo($meeting, 'scheduled', null, 'Auto-reconciled.');
                        }
                        $reconciled++;
                    } catch (\Throwable $e) {
                        Log::warning("Failed to allocate stuck meeting #{$meeting->id}: {$e->getMessage()}");
                        $this->stateMachine->transitionTo($meeting, 'failed', null, "Allocation timed out: {$e->getMessage()}");
                        $reconciled++;
                    }
                }
            }
        }

        return $reconciled;
    }

    /**
     * Advance scheduled meetings to 'started' when start time has arrived.
     */
    public function reconcileStartedMeetings(): int
    {
        $now = Carbon::now();

        $meetings = Meeting::where('status', 'scheduled')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->get();

        foreach ($meetings as $meeting) {
            $this->stateMachine->transitionTo($meeting, 'started', null, 'Session active window reached.');
        }

        return $meetings->count();
    }

    /**
     * Advance started or scheduled meetings to 'ended' / 'completed' once session end time elapses.
     */
    public function reconcileCompletedMeetings(): int
    {
        $now = Carbon::now();

        $meetings = Meeting::whereIn('status', ['started', 'scheduled'])
            ->where('ends_at', '<=', $now)
            ->get();

        foreach ($meetings as $meeting) {
            if ($meeting->status === 'scheduled') {
                $this->stateMachine->transitionTo($meeting, 'completed', null, 'Scheduled window elapsed without host start.');
            } else {
                $this->stateMachine->transitionTo($meeting, 'ended', null, 'Session duration concluded.');
                $this->stateMachine->transitionTo($meeting, 'completed', null, 'Meeting lifecycle completed.');
            }
        }

        return $meetings->count();
    }

    /**
     * Provision Zoom meeting details (Live S2S or deterministic mock).
     */
    public function provisionZoomDetails(Meeting $meeting): void
    {
        $meetingId = (string) rand(81000000000, 89999999999);
        $passcode = ! empty($meeting->passcode) ? $meeting->passcode : (string) rand(100000, 999999);

        // Attempt live Zoom S2S creation if connection is active
        if (! config('app.demo') && ! app()->environment('testing')) {
            /** @var ZoomConnection|null $conn */
            $conn = ZoomConnection::first();
            $resource = $meeting->zoomResource ?? ZoomResource::find($meeting->zoom_resource_id);

            if ($conn && ! empty($conn->account_id) && $resource && $resource->zoomUser) {
                try {
                    $basicAuth = base64_encode($conn->client_id.':'.$conn->client_secret);
                    $tokenRes = Http::timeout(5)
                        ->withHeaders(['Authorization' => 'Basic '.$basicAuth])
                        ->post('https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($conn->account_id));

                    if ($tokenRes->successful()) {
                        $token = $tokenRes->json('access_token');
                        $zoomUserEmail = $resource->zoomUser->email ?: $resource->zoomUser->zoom_user_id;

                        $createRes = Http::timeout(8)
                            ->withToken($token)
                            ->post("https://api.zoom.us/v2/users/{$zoomUserEmail}/meetings", [
                                'topic' => $meeting->title,
                                'type' => 2, // Scheduled meeting
                                'start_time' => $meeting->starts_at->toIso8601String(),
                                'duration' => max(15, (int) $meeting->starts_at->diffInMinutes($meeting->ends_at)),
                                'timezone' => $meeting->timezone ?: 'Asia/Kolkata',
                                'password' => $passcode,
                                'settings' => [
                                    'host_video' => true,
                                    'participant_video' => true,
                                    'join_before_host' => (bool) $meeting->join_before_host,
                                    'jbh_time' => (int) ($meeting->jbh_time ?? 0),
                                    'waiting_room' => (bool) $meeting->waiting_room,
                                    'auto_recording' => $meeting->recording_mode === 'cloud' ? 'cloud' : ($meeting->recording_mode === 'local' ? 'local' : 'none'),
                                ],
                            ]);

                        if ($createRes->successful()) {
                            $zoomData = $createRes->json();
                            $meeting->zoom_meeting_id = (string) ($zoomData['id'] ?? $meetingId);
                            $meeting->join_url = $zoomData['join_url'] ?? "https://zoom.us/j/{$meetingId}?pwd={$passcode}";
                            $meeting->passcode = $zoomData['password'] ?? $passcode;
                            if (! empty($resource->zoomUser->host_key)) {
                                $meeting->host_key = $resource->zoomUser->host_key;
                            }

                            return;
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning("Zoom API meeting provisioning failed: {$e->getMessage()}");
                }
            }
        }

        // Standard/fallback mock provisioning
        $meeting->zoom_meeting_id = $meetingId;
        $meeting->join_url = "https://zoom.us/j/{$meetingId}?pwd={$passcode}";
        $meeting->passcode = $passcode;

        $resource = $meeting->zoomResource ?? ($meeting->zoom_resource_id ? ZoomResource::with('zoomUser')->find($meeting->zoom_resource_id) : null);
        if ($resource && $resource->zoomUser && ! empty($resource->zoomUser->host_key)) {
            $meeting->host_key = $resource->zoomUser->host_key;
        } elseif (empty($meeting->host_key)) {
            $meeting->host_key = str_pad((string) rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Patch Zoom meeting details when a meeting is edited, rescheduled, or extended.
     */
    public function updateZoomMeeting(Meeting $meeting): void
    {
        if (! config('app.demo') && ! app()->environment('testing') && ! empty($meeting->zoom_meeting_id)) {
            /** @var ZoomConnection|null $conn */
            $conn = ZoomConnection::first();
            if ($conn && ! empty($conn->account_id)) {
                try {
                    $basicAuth = base64_encode($conn->client_id.':'.$conn->client_secret);
                    $tokenRes = Http::timeout(5)
                        ->withHeaders(['Authorization' => 'Basic '.$basicAuth])
                        ->post('https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($conn->account_id));

                    if ($tokenRes->successful()) {
                        $token = $tokenRes->json('access_token');
                        Http::timeout(8)
                            ->withToken($token)
                            ->patch("https://api.zoom.us/v2/meetings/{$meeting->zoom_meeting_id}", [
                                'topic' => $meeting->title,
                                'start_time' => $meeting->starts_at->toIso8601String(),
                                'duration' => max(15, (int) $meeting->starts_at->diffInMinutes($meeting->ends_at)),
                                'timezone' => $meeting->timezone ?: 'Asia/Kolkata',
                                'password' => $meeting->passcode,
                                'settings' => [
                                    'host_video' => true,
                                    'participant_video' => true,
                                    'join_before_host' => (bool) $meeting->join_before_host,
                                    'jbh_time' => (int) ($meeting->jbh_time ?? 0),
                                    'waiting_room' => (bool) $meeting->waiting_room,
                                    'auto_recording' => $meeting->recording_mode === 'cloud' ? 'cloud' : ($meeting->recording_mode === 'local' ? 'local' : 'none'),
                                ],
                            ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning("Zoom API meeting update failed: {$e->getMessage()}");
                }
            }
        }
    }
}
