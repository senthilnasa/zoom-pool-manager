<?php

namespace App\Domain\Attendance\Services;

use App\Domain\Attendance\Models\MeetingAttendance;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Zoom\Models\ZoomConnection;
use Carbon\Carbon;
use Exception;
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
        $meetings = Meeting::whereNotNull('zoom_meeting_id')
            ->whereIn('status', ['ended', 'completed'])
            ->where('starts_at', '>=', now()->subDays(7))
            ->get();

        if ($meetings->isEmpty()) {
            // Also check scheduled meetings that have already passed their end time
            $meetings = Meeting::whereNotNull('zoom_meeting_id')
                ->where('ends_at', '<=', now())
                ->where('starts_at', '>=', now()->subDays(7))
                ->limit(10)
                ->get();
        }

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

            // 2. Query Zoom Reports: Participant attendance API
            $zoomMeetingId = $meeting->zoom_meeting_id;
            $reportUrl = "https://api.zoom.us/v2/report/meetings/{$zoomMeetingId}/participants";

            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => 'Bearer '.$accessToken,
                    'User-Agent' => 'Zoom-Pool-Manager/'.config('zpm.version', '1.0.0'),
                ])
                ->get($reportUrl, [
                    'page_size' => 300,
                ]);

            if (! $response->successful()) {
                // If past meeting report returns 404/400 (not completed or basic plan), fallback gracefully
                return [
                    'success' => false,
                    'attendees_count' => 0,
                    'message' => "Zoom report API returned HTTP {$response->status()}: {$response->body()}",
                ];
            }

            /** @var array{participants?: array<int, array<string, mixed>>} $reportData */
            $reportData = $response->json();
            $participants = $reportData['participants'] ?? [];
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

        return [
            'success' => true,
            'attendees_count' => $count,
            'message' => "Simulated {$count} attendance records for meeting #{$meeting->id}.",
        ];
    }
}
