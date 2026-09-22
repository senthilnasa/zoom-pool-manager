<?php

namespace App\Domain\Webhooks\Jobs;

use App\Domain\Audit\Services\AuditService;
use App\Domain\HostControl\Services\HostControlService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Reconciliation\Models\DriftConflict;
use App\Domain\Recordings\Services\CloudRecordingService;
use App\Domain\Users\Models\User;
use App\Domain\Webhooks\Models\ZoomWebhookEvent;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessZoomWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public int $webhookEventId
    ) {}

    public function handle(
        CloudRecordingService $recordingService,
        HostControlService $hostControlService,
        AuditService $auditService
    ): void {
        $event = ZoomWebhookEvent::find($this->webhookEventId);
        if (! $event || $event->status === 'processed') {
            return;
        }

        $event->increment('attempts');

        try {
            $payload = $event->payload;
            $eventType = $event->event_type;
            /** @var array<string, mixed> $object */
            $object = $payload['payload']['object'] ?? [];

            switch ($eventType) {
                case 'meeting.started':
                    $this->handleMeetingStarted($object);
                    break;

                case 'meeting.ended':
                    $this->handleMeetingEnded($object, $hostControlService);
                    break;

                case 'meeting.updated':
                    $this->handleMeetingUpdated($object);
                    break;

                case 'meeting.deleted':
                    $this->handleMeetingDeleted($object);
                    break;

                case 'recording.completed':
                    $recordingService->ingestCompletedRecording($payload);
                    break;

                default:
                    Log::channel('webhooks')->info("Unhandled Zoom webhook event: {$eventType}", [
                        'event_id' => $event->event_id,
                    ]);
                    break;
            }

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);

            $auditService->log(
                'webhook.processed',
                $event,
                null,
                ['webhook_event_id' => $event->id, 'event_type' => $event->event_type]
            );
        } catch (\Throwable $e) {
            $event->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            Log::channel('webhooks')->error("Failed processing Zoom webhook event: {$e->getMessage()}", [
                'webhook_event_id' => $event->id,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function handleMeetingStarted(array $object): void
    {
        $meeting = $this->findMeetingByZoomObject($object);
        if ($meeting && in_array($meeting->status, ['scheduled', 'approved', 'allocating'])) {
            $meeting->update(['status' => 'started']);
            $meeting->statusHistory()->create([
                'from_status' => 'scheduled',
                'to_status' => 'started',
                'reason' => 'Zoom webhook: meeting.started',
                'created_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function handleMeetingEnded(array $object, HostControlService $hostControlService): void
    {
        $meeting = $this->findMeetingByZoomObject($object);
        if ($meeting && $meeting->status !== 'completed' && $meeting->status !== 'cancelled') {
            $targetStatus = ($meeting->recording_mode && $meeting->recording_mode !== 'none')
                ? 'recording_processing'
                : 'ended';

            $fromStatus = $meeting->status;
            $meeting->update(['status' => $targetStatus]);

            $meeting->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => $targetStatus,
                'reason' => 'Zoom webhook: meeting.ended',
                'created_at' => now(),
            ]);

            // SPEC Part F5 & F6: Rotate the host key of the resource after meeting ends
            if ($meeting->zoomResource) {
                // Use system user or first super-admin as actor
                $actor = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Administrator'))->first()
                    ?: User::first();

                if ($actor) {
                    $hostControlService->rotateHostKey($meeting->zoomResource, $actor, 'post_meeting_webhook_rotation');
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function handleMeetingUpdated(array $object): void
    {
        $meeting = $this->findMeetingByZoomObject($object);
        if (! $meeting) {
            return;
        }

        if (! empty($object['start_time'])) {
            $zoomStart = Carbon::parse($object['start_time']);
            $diffMinutes = abs($meeting->starts_at->diffInMinutes($zoomStart, false));

            if ($diffMinutes > 5) {
                // Flag drift conflict
                DriftConflict::firstOrCreate(
                    [
                        'meeting_id' => $meeting->id,
                        'incident_type' => 'time_mismatch',
                        'status' => 'open',
                    ],
                    [
                        'zoom_resource_id' => $meeting->zoom_resource_id,
                        'zoom_meeting_id' => (string) ($object['id'] ?? $meeting->zoom_meeting_id),
                        'details' => [
                            'zpm_start' => $meeting->starts_at->toIso8601String(),
                            'zoom_start' => $zoomStart->toIso8601String(),
                            'diff_minutes' => $diffMinutes,
                        ],
                    ]
                );
            }
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function handleMeetingDeleted(array $object): void
    {
        $meeting = $this->findMeetingByZoomObject($object);
        if (! $meeting) {
            return;
        }

        if (! in_array($meeting->status, ['cancelled', 'completed', 'ended'])) {
            // Meeting was deleted directly on Zoom! Flag drift conflict
            DriftConflict::firstOrCreate(
                [
                    'meeting_id' => $meeting->id,
                    'incident_type' => 'deleted_on_zoom',
                    'status' => 'open',
                ],
                [
                    'zoom_resource_id' => $meeting->zoom_resource_id,
                    'zoom_meeting_id' => (string) ($object['id'] ?? $meeting->zoom_meeting_id),
                    'details' => [
                        'reason' => 'Meeting was deleted in Zoom console outside of ZPM',
                        'deleted_at' => now()->toIso8601String(),
                    ],
                ]
            );
        }
    }

    /**
     * @param  array<string, mixed>  $object
     */
    protected function findMeetingByZoomObject(array $object): ?Meeting
    {
        $zoomMeetingId = (string) ($object['id'] ?? '');
        $zoomUuid = (string) ($object['uuid'] ?? '');

        if ($zoomMeetingId === '' && $zoomUuid === '') {
            return null;
        }

        return Meeting::where(function ($query) use ($zoomMeetingId, $zoomUuid) {
            if ($zoomMeetingId !== '') {
                $query->where('zoom_meeting_id', $zoomMeetingId);
            }
            if ($zoomUuid !== '') {
                $query->orWhere('zoom_uuid', $zoomUuid);
            }
        })->first();
    }
}
