<?php

namespace App\Domain\Communication\Services;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;

class MeetingNotificationService
{
    public function __construct(
        protected MailDeliveryService $mailService,
        protected IcsCalendarService $icsService,
        protected NotificationCenterService $notificationCenter
    ) {}

    /**
     * Send notifications when a meeting is booked and confirmed (or auto-approved).
     */
    public function notifyMeetingConfirmed(Meeting $meeting, bool $sync = false): void
    {
        $icsAttachment = $this->icsService->getAttachment($meeting, 'REQUEST');
        $owner = $meeting->owner ?? $meeting->requester;

        // 1. Notify Owner
        if ($owner) {
            $this->mailService->queueEmail(
                templateKey: 'meeting_confirmed',
                recipientEmail: $owner->email,
                recipientName: $owner->name,
                context: ['meeting' => $meeting],
                meeting: $meeting,
                eventId: "confirmed_{$meeting->id}_{$meeting->status}",
                attachments: [$icsAttachment],
                sync: $sync
            );

            $this->notificationCenter->notify(
                user: $owner,
                type: 'meeting_confirmed',
                title: 'Meeting Confirmed: '.$meeting->title,
                message: "Your meeting has been scheduled for {$meeting->starts_at->format('M d, Y H:i')}.",
                data: ['meeting_id' => $meeting->public_id]
            );
        }

        // 2. Notify Invitees
        foreach ($meeting->invitees as $invitee) {
            $this->mailService->queueEmail(
                templateKey: 'meeting_confirmed',
                recipientEmail: $invitee->email,
                recipientName: $invitee->name,
                context: ['meeting' => $meeting],
                meeting: $meeting,
                eventId: "confirmed_invitee_{$invitee->id}_{$meeting->id}",
                attachments: [$icsAttachment],
                sync: $sync
            );

            if ($invitee->user) {
                $this->notificationCenter->notify(
                    user: $invitee->user,
                    type: 'meeting_invitation',
                    title: 'Meeting Invitation: '.$meeting->title,
                    message: "You have been invited to {$meeting->title} on {$meeting->starts_at->format('M d, Y H:i')}.",
                    data: ['meeting_id' => $meeting->public_id]
                );
            }
        }
    }

    /**
     * Send notifications when a meeting requires approval.
     *
     * @param  iterable<User>  $approvers
     */
    public function notifyMeetingRequested(Meeting $meeting, iterable $approvers, bool $sync = false): void
    {
        foreach ($approvers as $approver) {
            $this->mailService->queueEmail(
                templateKey: 'meeting_requested',
                recipientEmail: $approver->email,
                recipientName: $approver->name,
                context: [
                    'meeting' => $meeting,
                    'review_url' => route('approvals.index'),
                ],
                meeting: $meeting,
                eventId: "requested_{$meeting->id}_{$approver->id}",
                sync: $sync
            );

            $this->notificationCenter->notify(
                user: $approver,
                type: 'approval_required',
                title: 'Approval Required: '.$meeting->title,
                message: "{$meeting->requester?->name} requested a meeting requiring your sign-off.",
                data: ['meeting_id' => $meeting->public_id]
            );
        }

        // Notify requester that meeting was submitted for approval
        $requester = $meeting->requester;
        if ($requester) {
            $this->notificationCenter->notify(
                user: $requester,
                type: 'approval_pending',
                title: 'Meeting Request Submitted: '.$meeting->title,
                message: 'Your meeting request has been submitted and is pending workflow approval.',
                data: ['meeting_id' => $meeting->public_id]
            );
        }
    }

    /**
     * Send notifications when a meeting request is approved.
     */
    public function notifyMeetingApproved(Meeting $meeting, bool $sync = false): void
    {
        $requester = $meeting->requester;
        if ($requester) {
            $this->mailService->queueEmail(
                templateKey: 'meeting_approved',
                recipientEmail: $requester->email,
                recipientName: $requester->name,
                context: ['meeting' => $meeting],
                meeting: $meeting,
                eventId: "approved_{$meeting->id}",
                sync: $sync
            );

            $this->notificationCenter->notify(
                user: $requester,
                type: 'meeting_approved',
                title: 'Meeting Approved: '.$meeting->title,
                message: 'Your meeting request has been approved and resource allocated.',
                data: ['meeting_id' => $meeting->public_id]
            );
        }
    }

    /**
     * Send notifications when a meeting request is rejected.
     */
    public function notifyMeetingRejected(Meeting $meeting, string $reason, bool $sync = false): void
    {
        $requester = $meeting->requester;
        if ($requester) {
            $this->mailService->queueEmail(
                templateKey: 'meeting_rejected',
                recipientEmail: $requester->email,
                recipientName: $requester->name,
                context: [
                    'meeting' => $meeting,
                    'reason' => $reason,
                ],
                meeting: $meeting,
                eventId: "rejected_{$meeting->id}",
                sync: $sync
            );

            $this->notificationCenter->notify(
                user: $requester,
                type: 'meeting_rejected',
                title: 'Meeting Rejected: '.$meeting->title,
                message: "Your meeting request was rejected. Reason: {$reason}",
                data: ['meeting_id' => $meeting->public_id, 'reason' => $reason]
            );
        }
    }

    /**
     * Send notifications when a meeting is cancelled.
     */
    public function notifyMeetingCancelled(Meeting $meeting, string $reason, bool $sync = false): void
    {
        $icsAttachment = $this->icsService->getAttachment($meeting, 'CANCEL');
        $owner = $meeting->owner ?? $meeting->requester;

        // 1. Notify Owner
        if ($owner) {
            $this->mailService->queueEmail(
                templateKey: 'meeting_cancelled',
                recipientEmail: $owner->email,
                recipientName: $owner->name,
                context: [
                    'meeting' => $meeting,
                    'reason' => $reason,
                ],
                meeting: $meeting,
                eventId: "cancelled_{$meeting->id}",
                attachments: [$icsAttachment],
                sync: $sync
            );

            $this->notificationCenter->notify(
                user: $owner,
                type: 'meeting_cancelled',
                title: 'Meeting Cancelled: '.$meeting->title,
                message: "Meeting was cancelled. Reason: {$reason}",
                data: ['meeting_id' => $meeting->public_id, 'reason' => $reason]
            );
        }

        // 2. Notify Invitees
        foreach ($meeting->invitees as $invitee) {
            $this->mailService->queueEmail(
                templateKey: 'meeting_cancelled',
                recipientEmail: $invitee->email,
                recipientName: $invitee->name,
                context: [
                    'meeting' => $meeting,
                    'reason' => $reason,
                ],
                meeting: $meeting,
                eventId: "cancelled_invitee_{$invitee->id}_{$meeting->id}",
                attachments: [$icsAttachment],
                sync: $sync
            );

            if ($invitee->user) {
                $this->notificationCenter->notify(
                    user: $invitee->user,
                    type: 'meeting_cancelled',
                    title: 'Meeting Cancelled: '.$meeting->title,
                    message: "{$meeting->title} scheduled for {$meeting->starts_at->format('M d, Y H:i')} was cancelled.",
                    data: ['meeting_id' => $meeting->public_id]
                );
            }
        }
    }

    /**
     * Send notification when placed on waitlist.
     */
    public function notifyMeetingWaitlisted(Meeting $meeting, bool $sync = false): void
    {
        $requester = $meeting->requester;
        if ($requester) {
            $this->mailService->queueEmail(
                templateKey: 'meeting_waitlisted',
                recipientEmail: $requester->email,
                recipientName: $requester->name,
                context: ['meeting' => $meeting],
                meeting: $meeting,
                eventId: "waitlisted_{$meeting->id}",
                sync: $sync
            );

            $this->notificationCenter->notify(
                user: $requester,
                type: 'meeting_waitlisted',
                title: 'Placed on Waitlist: '.$meeting->title,
                message: 'No Zoom resources were available for the requested time. You have been placed on the priority waitlist.',
                data: ['meeting_id' => $meeting->public_id]
            );
        }
    }

    /**
     * Send notification when allocated from waitlist.
     */
    public function notifyWaitlistAllocated(Meeting $meeting, bool $sync = false): void
    {
        $icsAttachment = $this->icsService->getAttachment($meeting, 'REQUEST');
        $requester = $meeting->requester;

        if ($requester) {
            $this->mailService->queueEmail(
                templateKey: 'waitlist_allocated',
                recipientEmail: $requester->email,
                recipientName: $requester->name,
                context: ['meeting' => $meeting],
                meeting: $meeting,
                eventId: "waitlist_allocated_{$meeting->id}",
                attachments: [$icsAttachment],
                sync: $sync
            );

            $this->notificationCenter->notify(
                user: $requester,
                type: 'waitlist_allocated',
                title: 'Resource Allocated from Waitlist: '.$meeting->title,
                message: 'A Zoom resource slot opened up! Your meeting is now confirmed and scheduled.',
                data: ['meeting_id' => $meeting->public_id]
            );
        }
    }

    /**
     * Send reminder 15 minutes before meeting start.
     */
    public function notifyStartReminder(Meeting $meeting, bool $sync = false): void
    {
        $owner = $meeting->owner ?? $meeting->requester;
        if ($owner) {
            $this->mailService->queueEmail(
                templateKey: 'start_reminder',
                recipientEmail: $owner->email,
                recipientName: $owner->name,
                context: ['meeting' => $meeting],
                meeting: $meeting,
                eventId: "reminder_{$meeting->id}",
                sync: $sync
            );

            $this->notificationCenter->notify(
                user: $owner,
                type: 'start_reminder',
                title: 'Meeting Starting Soon: '.$meeting->title,
                message: 'Your meeting starts in 15 minutes. Join links and host controls are now active.',
                data: ['meeting_id' => $meeting->public_id]
            );
        }

        foreach ($meeting->invitees as $invitee) {
            $this->mailService->queueEmail(
                templateKey: 'start_reminder',
                recipientEmail: $invitee->email,
                recipientName: $invitee->name,
                context: ['meeting' => $meeting],
                meeting: $meeting,
                eventId: "reminder_invitee_{$invitee->id}_{$meeting->id}",
                sync: $sync
            );
        }
    }
}
