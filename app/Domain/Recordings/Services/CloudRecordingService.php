<?php

namespace App\Domain\Recordings\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\NotificationCenterService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Recordings\Models\RecordingAccessLog;
use App\Domain\Recordings\Models\RecordingFile;
use App\Domain\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;

class CloudRecordingService
{
    public function __construct(
        protected AuditService $auditService,
        protected NotificationCenterService $notificationCenter
    ) {}

    /**
     * Ingest a completed recording payload from Zoom webhook or API sync.
     *
     * @param  array<string, mixed>  $payload
     */
    public function ingestCompletedRecording(array $payload): CloudRecording
    {
        /** @var array<string, mixed> $object */
        $object = $payload['payload']['object'] ?? $payload;

        $zoomMeetingId = (string) ($object['id'] ?? '');
        $zoomUuid = (string) ($object['uuid'] ?? '');
        $topic = (string) ($object['topic'] ?? 'Zoom Cloud Recording');
        $shareUrl = (string) ($object['share_url'] ?? '');
        $playUrl = (string) ($object['recording_play_passcode'] ?? $object['password'] ?? '');
        $durationMinutes = (int) ($object['duration'] ?? 0);
        $totalSize = (int) ($object['total_size'] ?? 0);

        $recordingStartTime = ! empty($object['start_time']) ? Carbon::parse($object['start_time']) : null;
        $recordingEndTime = ($recordingStartTime && $durationMinutes > 0)
            ? (clone $recordingStartTime)->addMinutes($durationMinutes)
            : null;

        // Map to logical meeting by Zoom Meeting ID or UUID
        $meeting = null;
        if ($zoomMeetingId !== '' || $zoomUuid !== '') {
            $meeting = Meeting::where(function ($query) use ($zoomMeetingId, $zoomUuid) {
                if ($zoomMeetingId !== '') {
                    $query->where('zoom_meeting_id', $zoomMeetingId);
                }
                if ($zoomUuid !== '') {
                    $query->orWhere('zoom_uuid', $zoomUuid);
                }
            })->first();
        }

        $logicalOwnerUserId = $meeting?->owner_user_id ?: $meeting?->requester_user_id;
        $zoomResourceId = $meeting?->zoom_resource_id;

        // Upsert the recording
        $zoomRecordingUuid = ! empty($object['uuid']) ? (string) $object['uuid'] : null;
        $existing = null;

        if ($zoomRecordingUuid) {
            $existing = CloudRecording::where('zoom_recording_id', $zoomRecordingUuid)->first();
        }

        if (! $existing && $zoomMeetingId !== '' && $recordingStartTime) {
            $existing = CloudRecording::where('zoom_meeting_id', $zoomMeetingId)
                ->where('recording_start', $recordingStartTime)
                ->first();
        }

        $recordingData = [
            'meeting_id' => $meeting?->id,
            'zoom_resource_id' => $zoomResourceId,
            'logical_owner_user_id' => $logicalOwnerUserId,
            'zoom_meeting_id' => $zoomMeetingId ?: 'UNKNOWN',
            'zoom_recording_id' => $zoomRecordingUuid,
            'topic' => $topic,
            'storage_provider' => 'zoom',
            'recording_start' => $recordingStartTime,
            'recording_end' => $recordingEndTime,
            'duration_minutes' => $durationMinutes,
            'file_size_bytes' => $totalSize,
            'share_url' => $shareUrl ?: null,
            'play_url' => $shareUrl ?: null,
            'passcode' => ! empty($object['password']) ? (string) $object['password'] : null,
            'status' => 'completed',
        ];

        if ($existing) {
            $existing->update($recordingData);
            /** @var CloudRecording $recording */
            $recording = $existing;
        } else {
            /** @var CloudRecording $recording */
            $recording = CloudRecording::create($recordingData);
        }

        // Ingest files
        /** @var array<int, array<string, mixed>> $recordingFiles */
        $recordingFiles = $object['recording_files'] ?? [];
        foreach ($recordingFiles as $fileData) {
            $zoomFileId = (string) ($fileData['id'] ?? '');
            RecordingFile::updateOrCreate(
                [
                    'recording_id' => $recording->id,
                    'zoom_file_id' => $zoomFileId ?: null,
                ],
                [
                    'file_type' => (string) ($fileData['file_type'] ?? 'MP4'),
                    'file_extension' => strtolower((string) ($fileData['file_extension'] ?? 'mp4')),
                    'file_size_bytes' => (int) ($fileData['file_size'] ?? 0),
                    'play_url' => (string) ($fileData['play_url'] ?? null),
                    'download_url' => (string) ($fileData['download_url'] ?? null),
                    'status' => (string) ($fileData['status'] ?? 'completed'),
                ]
            );
        }

        // Update meeting state to completed if currently ended or recording_processing
        if ($meeting && in_array($meeting->status, ['ended', 'recording_processing', 'scheduled'])) {
            $meeting->update(['status' => 'completed']);
        }

        // Notify logical owner
        if ($logicalOwnerUserId) {
            $owner = User::find($logicalOwnerUserId);
            if ($owner) {
                $this->notificationCenter->notify(
                    $owner,
                    'recording',
                    'Cloud Recording Ready',
                    "Cloud recording for '{$topic}' is now ready to view.",
                    [
                        'recording_id' => $recording->id,
                        'recording_public_id' => $recording->public_id,
                        'meeting_id' => $meeting->id,
                        'meeting_title' => $topic,
                        'duration_minutes' => $durationMinutes,
                    ]
                );
            }
        }

        $this->auditService->log(
            'recording.ingested',
            $recording,
            null,
            [
                'recording_id' => $recording->id,
                'meeting_id' => $meeting?->id,
                'logical_owner_id' => $logicalOwnerUserId,
                'files_count' => count($recordingFiles),
                'duration' => $durationMinutes,
            ]
        );

        return $recording;
    }

    /**
     * Authorize user access and obtain secure playback redirect URL.
     *
     * @throws AuthorizationException
     */
    public function authorizeAndGetPlayUrl(CloudRecording $recording, User $user, string $ipAddress, ?string $userAgent): string
    {
        // 1. Authorize: User must be logical owner, meeting requester, or hold recording.view_any / recording.view permission
        $isOwner = $recording->logical_owner_user_id === $user->id;
        $isMeetingRequester = $recording->meeting && $recording->meeting->requester_user_id === $user->id;
        $canViewAny = $user->can('recording.view_any');
        $canViewDept = $user->can('recording.view') && $recording->meeting && $user->department_id === $recording->meeting->department_id;

        if (! $isOwner && ! $isMeetingRequester && ! $canViewAny && ! $canViewDept) {
            throw new AuthorizationException('You are not authorized to view or access this cloud recording.');
        }

        // 2. Audit access log
        RecordingAccessLog::create([
            'recording_id' => $recording->id,
            'user_id' => $user->id,
            'action' => 'play_redirect',
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);

        $this->auditService->log(
            'recording.played',
            $recording,
            null,
            [
                'recording_id' => $recording->id,
                'user_id' => $user->id,
                'ip_address' => $ipAddress,
            ],
            $user,
            $ipAddress,
            $userAgent
        );

        // 3. Return play URL or share URL
        $targetUrl = $recording->play_url ?: $recording->share_url;
        if (empty($targetUrl)) {
            // Find first playable file
            $playableFile = $recording->files()->whereNotNull('play_url')->first();
            $targetUrl = $playableFile?->play_url;
        }

        if (empty($targetUrl)) {
            throw new \RuntimeException('Playback URL is currently unavailable for this recording.');
        }

        return $targetUrl;
    }
}
