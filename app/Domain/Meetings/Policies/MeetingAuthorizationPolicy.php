<?php

namespace App\Domain\Meetings\Policies;

use App\Domain\Users\Models\User;

class MeetingAuthorizationPolicy
{
    /**
     * Check if a user can approve a given meeting.
     * Enforces the anti-self-approval rule and department scoping.
     *
     * @param  object{id?: int, requester_user_id: int, owner_user_id: int, department_id?: int|null}  $meeting
     */
    public function approve(User $approver, object $meeting): bool
    {
        // 1. Mandatory Anti-Self-Approval Rule:
        // Under no circumstances may a user approve their own meeting request.
        if ($meeting->requester_user_id === $approver->id || $meeting->owner_user_id === $approver->id) {
            return false;
        }

        // 2. Must possess meeting.approve permission
        if (! $approver->hasPermissionTo('meeting.approve')) {
            return false;
        }

        // 3. Department Administrators cannot approve meetings outside their department
        if ($approver->hasRole('dept_admin')) {
            if (empty($approver->department_id) || empty($meeting->department_id) || (int) $approver->department_id !== (int) $meeting->department_id) {
                return false;
            }
        }

        return true;
    }
}
