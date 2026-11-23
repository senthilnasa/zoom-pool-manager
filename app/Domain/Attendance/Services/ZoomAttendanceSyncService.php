<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Models\MeetingAttendance;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\MailDeliveryService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Recordings\Services\CloudRecordingService;
use App\Domain\Zoom\Models\ZoomConnection;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZoomAttendanceSyncService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Synchronize participant attendance for all completed/ended meetings in the last 7 days.
     *
     * @return array{success: bool, synced_meetings: int, total_attendees: int, message: string}
     */
    public function syncRecentAttendance(): array
    {
        // Only meetings that actually ran in Zoom have a participant report; a booking whose
        // scheduled end has merely passed may never have been started and always fails with 3001.
        $meetings = Meeting::whereNotNull('zoom_meeting_id')
            ->where('starts_at', '>=', now()->subDays(7))
            ->where(function ($query) {
                $query->whereIn('status', ['ended', 'completed', 'recording_processing'])
                    ->orWhere(function ($query) {
                        // Started in Zoom (webhook captured a session UUID) but its end was never recorded.
                        $query->whereNotNull('zoom_uuid')
                            ->where('ends_at', '<=', now())
                            ->where('status', '!=', 'cancelled');
                    });
            })
            ->get();

        $totalAttendees = 0;
        $syncedMeetings = 0;

        foreach ($meetings as $meeting) {
            $res = $this->syncAttendanceForMeeting($meeting);
            if ($res['success']) {
                $syncedMeetings++;
                $totalAttendees += $res['attendees_count'];
            }
        }

        return [
            'success' => true,
            'synced_meetings' => $syncedMeetings,
            'total_attendees' => $totalAttendees,
            'message' => "Synchronized attendance for {$syncedMeetings} meetings ({$totalAttendees} participant records).",
        ];
    }

    /**
     * Synchronize attendance for a specific meeting.
     *
     * @return array{success: bool, attendees_count: int, message: string}
     */
    public function syncAttendanceForMeeting(Meeting $meeting): array
    {
        // Demo Simulation or Test Mode
        if (config('app.demo') || app()->environment('testing')) {
            return $this->simulateDemoAttendance($meeting);
        }

        /** @var ZoomConnection|null $connection */
        $connection = ZoomConnection::first();

        if (! $connection || empty($connection->account_id) || empty($connection->client_id) || empty($connection->client_secret)) {
            return [
                'success' => false,
                'attendees_count' => 0,
                'message' => 'Zoom credentials are not configured.',
            ];
        }

        try {
            // 1. Get OAuth token
            $basicAuth = base64_encode($connection->client_id.':'.$connection->client_secret);
            $tokenUrl = 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id='.urlencode($connection->account_id);

            $tokenResponse = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Basic '.$basicAuth,
                    'User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0'),
                ])
                ->post($tokenUrl);

            if (! $tokenResponse->successful()) {
                return [
                    'success' => false,
                    'attendees_count' => 0,
                    'message' => 'Failed to obtain Zoom access token for attendance report.',
                ];
            }

            /** @var array{access_token?: string} $tokenData */
            $tokenData = $tokenResponse->json();
            $accessToken = $tokenData['access_token'] ?? null;

            if (! $accessToken) {
                return [
                    'success' => false,
                    'attendees_count' => 0,
                    'message' => 'Missing access token in Zoom OAuth response.',
                ];
            }

            // 2. Query Zoom Reports: Participant attendance API.
            // Prefer the session UUID captured from webhooks; the numeric meeting ID only resolves
            // to a session Zoom can still associate with it, and otherwise fails with code 3001.
            $zoomMeetingId = (string) $meeting->zoom_meeting_id;
            $refs = array_values(array_unique(array_filter([(string) $meeting->zoom_uuid, $zoomMeetingId])));

            $report = null;
            foreach ($refs as $ref) {
                $report = $this->fetchParticipantReport($accessToken, $ref);
                if ($report['ok'] || ! $report['not_found']) {
                    break;
                }
            }

            if ($report !== null && ! $report['ok'] && $report['not_found']) {
                // Ask Zoom which sessions of this meeting actually ran, and report on the one for this booking.
                $instanceUuid = $this->findPastInstanceUuid($accessToken, $meeting);

                if ($instanceUuid === null) {
                    return [
                        'success' => false,
                        'attendees_count' => 0,
                        'message' => "Zoom has no ended session for meeting {$zoomMeetingId}. It was either never started in Zoom, or Zoom has not finished preparing its report yet (this can take a while after the meeting ends).",
                    ];
                }

                $report = $this->fetchParticipantReport($accessToken, $instanceUuid);
                if ($report['ok'] && $meeting->zoom_uuid !== $instanceUuid) {
                    $meeting->update(['zoom_uuid' => $instanceUuid]);
                }
            }

            if ($report === null || ! $report['ok']) {
                return [
                    'success' => false,
                    'attendees_count' => 0,
                    'message' => 'Zoom report API returned HTTP '.($report['status'] ?? 0).': '.($report['body'] ?? 'no response'),
                ];
            }

            $participants = $report['participants'];
            $count = 0;

            $meetingDurationSeconds = max(60, $meeting->starts_at->diffInSeconds($meeting->ends_at));

            foreach ($participants as $p) {
                $name = (string) ($p['name'] ?? 'Attendee');
                $email = (string) ($p['user_email'] ?? $p['email'] ?? '');
                $joinTime = ! empty($p['join_time']) ? Carbon::parse($p['join_time']) : $meeting->starts_at;
                $leaveTime = ! empty($p['leave_time']) ? Carbon::parse($p['leave_time']) : null;
                $durationSeconds = (int) ($p['duration'] ?? ($leaveTime ? $joinTime->diffInSeconds($leaveTime) : 0));

                $attendancePct = min(100.0, round(($durationSeconds / $meetingDurationSeconds) * 100, 2));

                MeetingAttendance::updateOrCreate(
                    [
                        'meeting_id' => $meeting->id,
                        'zoom_meeting_id' => $zoomMeetingId,
                        'participant_name' => $name,
                        'join_time' => $joinTime,
                    ],
                    [
                        'zoom_participant_id' => (string) ($p['id'] ?? null),
                        'participant_email' => $email ?: null,
                        'leave_time' => $leaveTime,
                        'duration_seconds' => $durationSeconds,
                        'attendance_percentage' => $attendancePct,
                        'device_type' => (string) ($p['device'] ?? null),
                        'status' => $attendancePct >= 75 ? 'present' : ($attendancePct >= 30 ? 'partial' : 'absent'),
                    ]
                );

                $count++;
            }

            $this->auditService->log(
                event: 'zoom.attendance.synced',
                auditable: $meeting,
                newValues: ['attendees_count' => $count, 'meeting_id' => $meeting->id]
            );

            if ($meeting->auto_send_attendance && $count > 0) {
                $this->sendAttendanceReportEmail($meeting);
            }

            return [
                'success' => true,
                'attendees_count' => $count,
                'message' => "Successfully imported {$count} attendance records for meeting #{$meeting->id}.",
            ];
        } catch (Exception $e) {
            Log::error("Attendance sync error for meeting {$meeting->id}: ".$e->getMessage());

            return [
                'success' => false,
                'attendees_count' => 0,
                'message' => 'Attendance sync error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Fetch every page of the participant report for a meeting ID or session UUID.
     *
     * @return array{ok: bool, not_found: bool, status: int, body: string, participants: array<int, array<string, mixed>>}
     */
    protected function fetchParticipantReport(string $accessToken, string $meetingRef): array
    {
        $url = 'https://api.zoom.us/v2/report/meetings/'.CloudRecordingService::encodeMeetingRef($meetingRef).'/participants';
        $participants = [];
        $nextPageToken = '';

        do {
            $query = ['page_size' => 300];
            if ($nextPageToken !== '') {
                $query['next_page_token'] = $nextPageToken;
            }

            $response = $this->zoomRequest($accessToken)->get($url, $query);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'not_found' => $response->status() === 404 || (int) $response->json('code') === 3001,
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'participants' => [],
                ];
            }

            /** @var array<int, array<string, mixed>> $page */
            $page = $response->json('participants') ?? [];
            $participants = array_merge($participants, $page);
            $nextPageToken = (string) ($response->json('next_page_token') ?? '');
        } while ($nextPageToken !== '');

        return ['ok' => true, 'not_found' => false, 'status' => 200, 'body' => '', 'participants' => $participants];
    }

    /**
     * Find the UUID of the ended Zoom session that best matches this booking's scheduled start.
     */
    protected function findPastInstanceUuid(string $accessToken, Meeting $meeting): ?string
    {
        $url = 'https://api.zoom.us/v2/past_meetings/'.rawurlencode((string) $meeting->zoom_meeting_id).'/instances';
        $response = $this->zoomRequest($accessToken)->get($url);

        if (! $response->successful()) {
            Log::warning("Could not list past Zoom instances for meeting #{$meeting->id}: HTTP {$response->status()} - {$response->body()}");

            return null;
        }

        /** @var array<int, array{uuid?: string, start_time?: string}> $instances */
        $instances = $response->json('meetings') ?? [];

        $best = null;
        $bestGap = PHP_INT_MAX;
        foreach ($instances as $instance) {
            if (empty($instance['uuid']) || empty($instance['start_time'])) {
                continue;
            }

            $gap = abs(Carbon::parse($instance['start_time'])->getTimestamp() - $meeting->starts_at->getTimestamp());
            if ($gap < $bestGap) {
                $bestGap = $gap;
                $best = (string) $instance['uuid'];
            }
        }

        return $best;
    }

    protected function zoomRequest(string $accessToken): PendingRequest
    {
        return Http::timeout(15)->withHeaders([
            'Authorization' => 'Bearer '.$accessToken,
            'User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0'),
        ]);
    }

    /**
     * Demo simulation of meeting attendance records.
     *
     * @return array{success: bool, attendees_count: int, message: string}
     */
    protected function simulateDemoAttendance(Meeting $meeting): array
    {
        $demoParticipants = [
            ['name' => 'Prof. Senthil Nasa', 'email' => 'senthil@univ.edu', 'pct' => 100.0, 'status' => 'present', 'device' => 'Mac'],
            ['name' => 'Dr. Jane Smith', 'email' => 'jsmith@univ.edu', 'pct' => 98.5, 'status' => 'present', 'device' => 'Windows'],
            ['name' => 'Alex Rivera', 'email' => 'arivera@student.univ.edu', 'pct' => 88.0, 'status' => 'present', 'device' => 'iOS'],
            ['name' => 'Jordan Lee', 'email' => 'jlee@student.univ.edu', 'pct' => 94.2, 'status' => 'present', 'device' => 'Android'],
            ['name' => 'Sam Taylor', 'email' => 'staylor@student.univ.edu', 'pct' => 45.0, 'status' => 'partial', 'device' => 'Windows'],
        ];

        $meetingDurationSeconds = max(60, $meeting->starts_at->diffInSeconds($meeting->ends_at));
        $count = 0;

        foreach ($demoParticipants as $p) {
            $durationSeconds = (int) round(($p['pct'] / 100) * $meetingDurationSeconds);
            $joinTime = (clone $meeting->starts_at)->addMinutes(rand(0, 5));
            $leaveTime = (clone $joinTime)->addSeconds($durationSeconds);

            MeetingAttendance::updateOrCreate(
                [
                    'meeting_id' => $meeting->id,
                    'zoom_meeting_id' => $meeting->zoom_meeting_id ?: '123456789',
                    'participant_name' => $p['name'],
                    'join_time' => $joinTime,
                ],
                [
                    'participant_email' => $p['email'],
                    'leave_time' => $leaveTime,
                    'duration_seconds' => $durationSeconds,
                    'attendance_percentage' => $p['pct'],
                    'device_type' => $p['device'],
                    'status' => $p['status'],
                ]
            );

            $count++;
        }

        if ($meeting->auto_send_attendance) {
            $this->sendAttendanceReportEmail($meeting);
        }

        return [
            'success' => true,
            'attendees_count' => $count,
            'message' => "Simulated {$count} attendance records for meeting #{$meeting->id}.",
        ];
    }

    /**
     * Dispatch attendance report email to meeting owner and requester.
     */
    protected function sendAttendanceReportEmail(Meeting $meeting): void
    {
        try {
            $attendances = MeetingAttendance::where('meeting_id', $meeting->id)
                ->orderBy('join_time', 'asc')
                ->get();

            if ($attendances->isEmpty()) {
                return;
            }

            $rowsHtml = '';
            $rowsText = '';

            foreach ($attendances as $att) {
                $durationMin = round($att->duration_seconds / 60);
                $pct = number_format($att->attendance_percentage, 1);
                $statusColor = $att->status === 'present' ? '#16a34a' : ($att->status === 'partial' ? '#d97706' : '#dc2626');

                $rowsHtml .= "<tr style=\"border-bottom: 1px solid #e2e8f0;\">
                    <td style=\"padding: 8px 10px; font-weight: 600;\">{$att->participant_name}</td>
                    <td style=\"padding: 8px 10px; color: #64748b;\">".($att->participant_email ?: 'N/A').'</td>
                    <td style="padding: 8px 10px;">'.($att->join_time ? $att->join_time->format('H:i:s') : 'N/A')."</td>
                    <td style=\"padding: 8px 10px;\">{$durationMin} min</td>
                    <td style=\"padding: 8px 10px;\"><span style=\"color: {$statusColor}; font-weight: 600;\">{$pct}% (".ucfirst($att->status).')</span></td>
                </tr>';

                $rowsText .= "- {$att->participant_name} (".($att->participant_email ?: 'N/A')."): {$durationMin} min, {$pct}% (".ucfirst($att->status).")\n";
            }

            $tableHtml = "<table style=\"width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 12px;\">
                <thead>
                    <tr style=\"background: #f1f5f9; text-align: left; border-bottom: 2px solid #cbd5e1;\">
                        <th style=\"padding: 8px 10px;\">Participant</th>
                        <th style=\"padding: 8px 10px;\">Email</th>
                        <th style=\"padding: 8px 10px;\">Join Time</th>
                        <th style=\"padding: 8px 10px;\">Duration</th>
                        <th style=\"padding: 8px 10px;\">Attendance</th>
                    </tr>
                </thead>
                <tbody>{$rowsHtml}</tbody>
            </table>";

            $mailService = app(MailDeliveryService::class);
            $recipients = collect([$meeting->owner, $meeting->requester])->filter()->unique('id');

            foreach ($recipients as $recipient) {
                $mailService->queueEmail(
                    templateKey: 'meeting_attendance_report',
                    recipientEmail: $recipient->email,
                    recipientName: $recipient->name,
                    context: [
                        'meeting' => $meeting,
                        'attendees_count' => (string) $attendances->count(),
                        'attendance_table' => $tableHtml,
                        'attendance_summary_text' => $rowsText,
                    ],
                    meeting: $meeting,
                    eventId: "attendance_report_{$meeting->id}_{$recipient->id}"
                );
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to dispatch auto-attendance email for meeting #{$meeting->id}: {$e->getMessage()}");
        }
    }
}
