<?php

namespace App\Domain\Operations\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Communication\Services\NotificationCenterService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Scheduling\Models\ResourceReservation;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

class EmergencyOverrideService
{
    public function __construct(
        protected AuditService $auditService,
        protected NotificationCenterService $notificationCenter
    ) {}

    /**
     * Force-reallocate a meeting to a different resource, bypassing normal buffers and quotas.
     */
    public function emergencyReallocate(Meeting $meeting, ZoomResource $newResource, User $actor, string $reason): void
    {
        $this->ensureAuthorizedAndReasonProvided($actor, $reason);

        $oldResourceId = $meeting->zoom_resource_id;

        DB::transaction(function () use ($meeting, $newResource, $actor, $reason, $oldResourceId) {
            // Update meeting resource
            $meeting->zoom_resource_id = $newResource->id;
            $meeting->save();

            // Update or recreate reservation
            ResourceReservation::where('meeting_id', $meeting->id)->delete();
            ResourceReservation::create([
                'resource_id' => $newResource->id,
                'meeting_id' => $meeting->id,
                'occupied_from' => $meeting->starts_at,
                'occupied_until' => (clone $meeting->ends_at)->addMinutes(10),
                'status' => 'confirmed',
            ]);

            // Write to overrides table (M6/M11 requirement)
            DB::table('overrides')->insert([
                'public_id' => (string) new Ulid,
                'actor_user_id' => $actor->id,
                'target_type' => 'meeting',
                'target_id' => $meeting->id,
                'field' => 'emergency_reallocation',
                'old_value' => (string) $oldResourceId,
                'new_value' => (string) $newResource->id,
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // Audit log
        $this->auditService->log(
            'meeting.emergency_override',
            $meeting,
            ['zoom_resource_id' => $oldResourceId],
            ['zoom_resource_id' => $newResource->id, 'reason' => $reason],
            $actor
        );

        // Notify meeting owner
        $owner = $meeting->owner ?: $meeting->requester;
        if ($owner) {
            $this->notificationCenter->notify(
                $owner,
                'security',
                'Emergency IT Override: Host Resource Changed',
                "An administrator performed an emergency host resource reallocation for '{$meeting->title}'. Reason: {$reason}",
                [
                    'meeting_id' => $meeting->id,
                    'actor_id' => $actor->id,
                    'reason' => $reason,
                ]
            );
        }
    }

    /**
     * Emergency cancellation of a meeting by IT administrator with mandatory reason.
     */
    public function emergencyCancel(Meeting $meeting, User $actor, string $reason): void
    {
        $this->ensureAuthorizedAndReasonProvided($actor, $reason);

        $fromStatus = $meeting->status;

        DB::transaction(function () use ($meeting, $actor, $reason, $fromStatus) {
            $meeting->update([
                'status' => 'cancelled',
                'cancelled_reason' => "Emergency IT Override: {$reason}",
            ]);

            // Release reservation
            ResourceReservation::where('meeting_id', $meeting->id)->delete();

            // Record status history
            $meeting->statusHistory()->create([
                'from_status' => $fromStatus,
                'to_status' => 'cancelled',
                'reason' => "Emergency Override: {$reason}",
                'created_at' => now(),
            ]);

            // Record in overrides
            DB::table('overrides')->insert([
                'public_id' => (string) new Ulid,
                'actor_user_id' => $actor->id,
                'target_type' => 'meeting',
                'target_id' => $meeting->id,
                'field' => 'emergency_cancel',
                'old_value' => $fromStatus,
                'new_value' => 'cancelled',
                'reason' => $reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        // Audit log
        $this->auditService->log(
            'meeting.emergency_cancel',
            $meeting,
            ['status' => $fromStatus],
            ['status' => 'cancelled', 'reason' => $reason],
            $actor
        );

        // Notify owner
        $owner = $meeting->owner ?: $meeting->requester;
        if ($owner) {
            $this->notificationCenter->notify(
                $owner,
                'security',
                'Emergency IT Override: Meeting Cancelled',
                "An administrator performed an emergency cancellation of '{$meeting->title}'. Reason: {$reason}",
                [
                    'meeting_id' => $meeting->id,
                    'actor_id' => $actor->id,
                    'reason' => $reason,
                ]
            );
        }
    }

    /**
     * Validate permissions and non-empty reason.
     *
     * @throws AuthorizationException|\InvalidArgumentException
     */
    protected function ensureAuthorizedAndReasonProvided(User $actor, string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A specific, non-empty reason is mandatory for emergency IT overrides.');
        }

        if (! $actor->can('emergency.use') && ! $actor->hasRole('Super Administrator')) {
            throw new AuthorizationException('You lack the required emergency.use permission to execute administrative overrides.');
        }
    }
}
