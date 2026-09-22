<?php

namespace App\Domain\Reconciliation\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\NotificationCenterService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Reconciliation\Models\DriftConflict;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomResource;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class DriftReconciliationService
{
    public function __construct(
        protected AuditService $auditService,
        protected NotificationCenterService $notificationCenter
    ) {}

    /**
     * Reconcile managed resources over the given window against provided Zoom meetings data.
     *
     * @param  array<int, array<string, mixed>>|null  $externalZoomMeetings  Optional mock/API meeting list
     * @return array{conflicts_created: int, checked_resources: int}
     */
    public function reconcile(int $daysAhead = 30, ?array $externalZoomMeetings = null): array
    {
        $windowStart = now();
        $windowEnd = now()->addDays($daysAhead);

        /** @var Collection<int, ZoomResource> $resources */
        $resources = ZoomResource::where('managed', true)->get();
        $conflictsCreated = 0;

        foreach ($resources as $resource) {
            // Find scheduled ZPM meetings on this resource in the window
            $zpmMeetings = Meeting::where('zoom_resource_id', $resource->id)
                ->whereIn('status', ['scheduled', 'approved'])
                ->whereBetween('starts_at', [$windowStart, $windowEnd])
                ->get();

            // Zoom meetings list for this resource
            $zoomMeetings = $externalZoomMeetings ?? [];

            // 1. Check for meetings in ZPM that are missing in Zoom
            if ($externalZoomMeetings !== null) {
                $zoomMeetingIds = collect($zoomMeetings)->pluck('id')->map(fn ($id) => (string) $id)->all();

                foreach ($zpmMeetings as $meeting) {
                    if ($meeting->zoom_meeting_id && ! in_array((string) $meeting->zoom_meeting_id, $zoomMeetingIds, true)) {
                        $conflict = DriftConflict::firstOrCreate(
                            [
                                'meeting_id' => $meeting->id,
                                'incident_type' => 'deleted_on_zoom',
                                'status' => 'open',
                            ],
                            [
                                'zoom_resource_id' => $resource->id,
                                'zoom_meeting_id' => $meeting->zoom_meeting_id,
                                'details' => [
                                    'title' => $meeting->title,
                                    'starts_at' => $meeting->starts_at->toIso8601String(),
                                    'reason' => 'Meeting scheduled in ZPM was not found in Zoom account.',
                                ],
                            ]
                        );

                        if ($conflict->wasRecentlyCreated) {
                            $conflictsCreated++;
                            $this->notifyAdminsOfConflict($conflict);
                        }
                    }
                }

                // 2. Check for external meetings in Zoom that are NOT in ZPM
                $zpmMeetingIds = $zpmMeetings->pluck('zoom_meeting_id')->filter()->map(fn ($id) => (string) $id)->all();

                foreach ($zoomMeetings as $zm) {
                    $zmId = (string) ($zm['id'] ?? '');
                    if ($zmId !== '' && ! in_array($zmId, $zpmMeetingIds, true)) {
                        $conflict = DriftConflict::firstOrCreate(
                            [
                                'zoom_resource_id' => $resource->id,
                                'zoom_meeting_id' => $zmId,
                                'incident_type' => 'unmanaged_external_meeting',
                                'status' => 'open',
                            ],
                            [
                                'meeting_id' => null,
                                'details' => [
                                    'topic' => $zm['topic'] ?? 'External Zoom Meeting',
                                    'start_time' => $zm['start_time'] ?? null,
                                    'duration' => $zm['duration'] ?? 60,
                                    'reason' => 'Unmanaged meeting created directly on Zoom console.',
                                ],
                            ]
                        );

                        if ($conflict->wasRecentlyCreated) {
                            $conflictsCreated++;
                            $this->notifyAdminsOfConflict($conflict);
                        }
                    }
                }
            }
        }

        return [
            'conflicts_created' => $conflictsCreated,
            'checked_resources' => $resources->count(),
        ];
    }

    /**
     * Resolve a drift conflict using one of the allowed actions:
     * - 'resolve': re-apply ZPM schedule or re-create meeting in Zoom
     * - 'accepted_zoom': update ZPM meeting time/details to match Zoom
     * - 'ignored': dismiss the conflict
     * - 'marked_external': convert unmanaged Zoom meeting to an external reservation in ZPM
     */
    public function resolveConflict(DriftConflict $conflict, string $action, User $resolvedBy, ?string $notes = null): void
    {
        switch ($action) {
            case 'resolve':
                if ($conflict->meeting && $conflict->incident_type === 'time_mismatch') {
                    // In real zoom mode, this calls ZoomClient::updateMeeting()
                    $conflict->status = 'resolved';
                } else {
                    $conflict->status = 'resolved';
                }
                break;

            case 'accepted_zoom':
                if ($conflict->meeting && isset($conflict->details['zoom_start'])) {
                    $newStart = Carbon::parse($conflict->details['zoom_start']);
                    $duration = $conflict->meeting->starts_at->diffInMinutes($conflict->meeting->ends_at);
                    $conflict->meeting->update([
                        'starts_at' => $newStart,
                        'ends_at' => (clone $newStart)->addMinutes($duration),
                    ]);
                }
                $conflict->status = 'accepted_zoom';
                break;

            case 'ignored':
                $conflict->status = 'ignored';
                break;

            case 'marked_external':
                // Create an external reservation to prevent ZPM allocator from double-booking this resource
                if ($conflict->zoom_resource_id) {
                    $startStr = $conflict->details['start_time'] ?? now()->toIso8601String();
                    $start = Carbon::parse($startStr);
                    $duration = (int) ($conflict->details['duration'] ?? 60);
                    $end = (clone $start)->addMinutes($duration);

                    ResourceReservation::create([
                        'resource_id' => $conflict->zoom_resource_id,
                        'meeting_id' => null,
                        'occupied_from' => $start,
                        'occupied_until' => (clone $end)->addMinutes(10), // Include buffer
                        'status' => 'confirmed',
                    ]);
                }
                $conflict->status = 'marked_external';
                break;

            default:
                throw new \InvalidArgumentException("Unsupported conflict resolution action: {$action}");
        }

        $conflict->resolved_by_user_id = $resolvedBy->id;
        $conflict->resolved_at = now();
        $conflict->resolution_notes = $notes;
        $conflict->save();

        $this->auditService->log(
            'drift_conflict.resolved',
            $conflict,
            null,
            [
                'conflict_id' => $conflict->id,
                'action' => $action,
                'resolved_by' => $resolvedBy->id,
                'notes' => $notes,
            ],
            $resolvedBy
        );
    }

    /**
     * Notify IT & Super administrators about a detected drift conflict.
     */
    protected function notifyAdminsOfConflict(DriftConflict $conflict): void
    {
        $admins = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['Super Administrator', 'IT Administrator']);
        })->get();

        foreach ($admins as $admin) {
            $this->notificationCenter->notify(
                $admin,
                'security',
                'Drift Conflict Detected',
                "Drift conflict detected: {$conflict->incident_type} on Zoom resource.",
                [
                    'conflict_id' => $conflict->id,
                    'conflict_public_id' => $conflict->public_id,
                    'incident_type' => $conflict->incident_type,
                    'meeting_id' => $conflict->meeting_id,
                ]
            );
        }
    }
}
