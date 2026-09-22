<?php

namespace App\Domain\HostControl\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\HostControl\Contracts\MeetingHostProviderInterface;
use App\Domain\HostControl\Models\Override;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

class HostControlService
{
    public function __construct(
        protected MeetingHostProviderInterface $hostProvider,
        protected AuditService $auditService
    ) {}

    /**
     * Determine if the user is authorized to access host controls for the meeting.
     */
    public function canAccessHostControls(Meeting $meeting, User $user): bool
    {
        // Owner or requester can access
        if ($meeting->owner_user_id === $user->id || $meeting->requester_user_id === $user->id) {
            return true;
        }

        // IT Administrator or Super Admin can access via emergency override
        if ($user->hasRole('super_admin') || $user->hasRole('it_admin') || $user->hasPermissionTo('emergency_override')) {
            return true;
        }

        return false;
    }

    /**
     * Check whether current time is within the allowed start meeting window.
     * Default: 15 minutes before meeting starts until the meeting ends.
     */
    public function isWithinStartWindow(Meeting $meeting): bool
    {
        $leadMinutes = (int) Setting::get('host.lead_minutes', 15);

        $windowStart = $meeting->starts_at->copy()->subMinutes($leadMinutes);
        $windowEnd = $meeting->ends_at->copy();

        $now = Carbon::now();

        return $now->greaterThanOrEqualTo($windowStart) && $now->lessThanOrEqualTo($windowEnd);
    }

    /**
     * Generate a short-lived JIT start URL and redirect.
     * The URL is never persisted, emailed, or exposed in plain text in logs.
     *
     * @throws AuthorizationException|RuntimeException|InvalidArgumentException
     */
    public function getJitStartUrl(Meeting $meeting, User $user, ?string $emergencyReason = null): string
    {
        if (! $this->canAccessHostControls($meeting, $user)) {
            throw new AuthorizationException('You are not authorized to start this meeting.');
        }

        $isOwner = ($meeting->owner_user_id === $user->id || $meeting->requester_user_id === $user->id);

        if ($isOwner) {
            if (! $this->isWithinStartWindow($meeting)) {
                throw new RuntimeException('The meeting start button is only available from 15 minutes prior to scheduled start time until the meeting concludes.');
            }

            $this->auditService->log('meeting.host_started', $meeting, null, [
                'type' => 'owner_start',
            ], $user);
        } else {
            // Emergency IT override
            $reason = trim($emergencyReason ?? '');
            if (empty($reason)) {
                throw new InvalidArgumentException('An emergency override reason is mandatory for IT administrator access.');
            }

            Override::create([
                'actor_user_id' => $user->id,
                'target_type' => 'meeting',
                'target_id' => $meeting->id,
                'field' => 'emergency_host_start',
                'old_value' => null,
                'new_value' => 'started_by_it',
                'reason' => $reason,
            ]);

            $this->auditService->log('meeting.it_emergency_start', $meeting, null, [
                'reason' => $reason,
            ], $user);
        }

        return $this->hostProvider->getStartUrl($meeting);
    }

    /**
     * Reveal the host key for the allocated Zoom resource during active meeting window.
     *
     * @throws AuthorizationException|RuntimeException|InvalidArgumentException
     */
    public function revealHostKey(Meeting $meeting, User $user, ?string $emergencyReason = null): string
    {
        if (! $this->canAccessHostControls($meeting, $user)) {
            throw new AuthorizationException('You are not authorized to view the host key for this meeting.');
        }

        $resource = $meeting->zoomResource;
        if (! $resource || ! $resource->zoomUser) {
            throw new RuntimeException('No Zoom resource is assigned to this meeting.');
        }

        $isOwner = ($meeting->owner_user_id === $user->id || $meeting->requester_user_id === $user->id);

        if ($isOwner) {
            if (! $this->isWithinStartWindow($meeting)) {
                throw new RuntimeException('Host key is only accessible during the active meeting window.');
            }
        } else {
            $reason = trim($emergencyReason ?? '');
            if (empty($reason)) {
                throw new InvalidArgumentException('An emergency override reason is mandatory for IT administrator access.');
            }

            Override::create([
                'actor_user_id' => $user->id,
                'target_type' => 'meeting',
                'target_id' => $meeting->id,
                'field' => 'host_key_reveal',
                'old_value' => null,
                'new_value' => 'revealed_by_it',
                'reason' => $reason,
            ]);
        }

        $this->auditService->log('host_key.revealed', $meeting, null, [
            'resource_id' => $resource->id,
            'is_emergency_override' => ! $isOwner,
        ], $user);

        $hostKey = $resource->zoomUser->host_key;

        // If not set, generate initial key
        if (empty($hostKey)) {
            return $this->rotateHostKey($resource, $user, 'initial_assignment');
        }

        return $hostKey;
    }

    /**
     * Automatically rotate the Zoom host key for a resource.
     */
    public function rotateHostKey(ZoomResource $resource, User $actor, string $reason = 'post_meeting_rotation'): string
    {
        $newHostKey = (string) random_int(100000, 999999);

        $this->hostProvider->rotateHostKey($resource, $newHostKey);

        if ($resource->zoomUser) {
            $resource->zoomUser->host_key = $newHostKey;
            $resource->zoomUser->save();
        }

        $this->auditService->log('host_key.rotated', $resource, null, [
            'reason' => $reason,
        ], $actor);

        return $newHostKey;
    }
}
