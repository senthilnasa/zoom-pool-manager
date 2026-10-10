<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Models\EmailTemplate;
use App\Domain\Settings\Models\Setting;

class EmailTemplateRegistry
{
    /**
     * Get the master definitions for all core system email templates.
     *
     * @return array<string, array{
     *     key: string,
     *     name: string,
     *     category: string,
     *     description: string,
     *     subject_template: string,
     *     body_html_template: string,
     *     body_text_template: string,
     *     available_variables: array<int, string>
     * }>
     */
    public static function getDefaultTemplates(): array
    {
        return [
            'meeting_confirmed' => [
                'key' => 'meeting_confirmed',
                'name' => 'Meeting Booking Confirmed',
                'category' => 'bookings',
                'description' => 'Sent to meeting organizers and invitees immediately when a meeting is booked and pooled Zoom host credentials are provisioned.',
                'subject_template' => '[{{org.name}}] Confirmed: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #1e40af; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #64748b; font-weight: 500;">Meeting Confirmation &amp; Host Credentials</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">Your meeting <strong>{{meeting.title}}</strong> has been confirmed. A calendar invitation (.ics) is attached to this email.</p>
    
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Starts At:</strong> {{meeting.starts_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Ends At:</strong> {{meeting.ends_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Duration:</strong> {{meeting.duration_minutes}} minutes</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Join URL:</strong> <a href="{{meeting.join_url}}" style="color: #2563eb; word-break: break-all; font-weight: 600;">{{meeting.join_url}}</a></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Passcode:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace;">{{meeting.passcode}}</code></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Host Key PIN:</strong> <code style="background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: bold;">{{meeting.host_key}}</code> <span style="font-size: 11px; color: #64748b;">(Claim Host: In Zoom &gt; Participants &gt; Claim Host)</span></p>
    </div>

    <!-- 1-Click Calendar Add Options -->
    <div style="margin: 20px 0; padding: 16px; background-color: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 12px; text-align: center;">
        <p style="font-size: 11px; color: #64748b; margin: 0 0 10px 0; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Add To Your Calendar</p>
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
            <tr>
                <td style="padding: 0 4px;">
                    <a href="{{meeting.google_calendar_url}}" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 8px 14px; background-color: #2563eb; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 12px; font-weight: 600;">+ Google Calendar</a>
                </td>
                <td style="padding: 0 4px;">
                    <a href="{{meeting.outlook_calendar_url}}" target="_blank" rel="noopener noreferrer" style="display: inline-block; padding: 8px 14px; background-color: #0284c7; color: #ffffff; text-decoration: none; border-radius: 8px; font-size: 12px; font-weight: 600;">+ Outlook</a>
                </td>
                <td style="padding: 0 4px;">
                    <a href="{{meeting.ics_url}}" style="display: inline-block; padding: 8px 14px; background-color: #ffffff; color: #334155; text-decoration: none; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 12px; font-weight: 600;">Download .ICS</a>
                </td>
            </tr>
        </table>
    </div>

    <!-- Schema.org Event Reservation -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "EventReservation",
      "reservationNumber": "{{meeting.id}}",
      "reservationStatus": "https://schema.org/Confirmed",
      "underName": {
        "@type": "Person",
        "name": "{{recipient.name}}"
      },
      "reservationFor": {
        "@type": "Event",
        "name": "{{meeting.title}}",
        "startDate": "{{meeting.starts_at_iso}}",
        "endDate": "{{meeting.ends_at_iso}}",
        "location": {
          "@type": "VirtualLocation",
          "url": "{{meeting.join_url}}"
        }
      }
    }
    </script>

    <p style="font-size: 13px; color: #475569;">To view host controls or manage this booking, log into the <a href="{{org.website}}" style="color: #2563eb; font-weight: 600;">{{org.name}} Portal</a>.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Meeting Confirmed
--------------------------------------------------
Hello {{recipient.name}},

Your meeting {{meeting.title}} has been confirmed.

Starts At: {{meeting.starts_at}}
Ends At: {{meeting.ends_at}}
Duration: {{meeting.duration_minutes}} minutes
Join URL: {{meeting.join_url}}
Passcode: {{meeting.passcode}}
Host Key PIN: {{meeting.host_key}} (Claim Host: In Zoom client > Participants > Claim Host)

Calendar Links:
Google Calendar: {{meeting.google_calendar_url}}
Outlook: {{meeting.outlook_calendar_url}}

To manage this booking, log into {{org.name}} at {{org.website}}.
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'org.support_email',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.description',
                    'meeting.starts_at',
                    'meeting.ends_at',
                    'meeting.duration_minutes',
                    'meeting.timezone',
                    'meeting.join_url',
                    'meeting.passcode',
                    'meeting.host_key',
                    'meeting.google_calendar_url',
                    'meeting.outlook_calendar_url',
                    'meeting.ics_url',
                ],
            ],

            'start_reminder' => [
                'key' => 'start_reminder',
                'name' => '15-Minute Meeting Start Reminder',
                'category' => 'bookings',
                'description' => 'Automated countdown reminder sent 15 minutes before scheduled start time with direct 1-click launch links.',
                'subject_template' => '[{{org.name}}] Starting in 15 Minutes: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #1e40af; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #e11d48; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Upcoming Meeting Reminder</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">Your meeting <strong>{{meeting.title}}</strong> will start in <strong>15 minutes</strong>.</p>
    
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Scheduled Time:</strong> {{meeting.starts_at}} – {{meeting.ends_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Passcode:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace;">{{meeting.passcode}}</code></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Host Key PIN:</strong> <code style="background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: bold;">{{meeting.host_key}}</code></p>
    </div>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{meeting.join_url}}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 10px; font-weight: 700; font-size: 14px; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);">Launch Zoom Meeting</a>
    </div>

    <p style="font-size: 13px; color: #475569;">If you are hosting, remember to claim host using the 6-digit PIN in the Zoom client.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Meeting Starts in 15 Minutes
--------------------------------------------------
Hello {{recipient.name}},

Your meeting {{meeting.title}} starts in 15 minutes.

Scheduled: {{meeting.starts_at}} - {{meeting.ends_at}}
Join URL: {{meeting.join_url}}
Passcode: {{meeting.passcode}}
Host Key PIN: {{meeting.host_key}}

Launch Zoom: {{meeting.join_url}}
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.starts_at',
                    'meeting.ends_at',
                    'meeting.duration_minutes',
                    'meeting.join_url',
                    'meeting.passcode',
                    'meeting.host_key',
                ],
            ],

            'meeting_requested' => [
                'key' => 'meeting_requested',
                'name' => 'Meeting Approval Sign-off Requested',
                'category' => 'approvals',
                'description' => 'Sent to department administrators and approvers when an intake booking requires formal sign-off.',
                'subject_template' => '[{{org.name}}] Approval Requested: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #1e40af; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #d97706; font-weight: 700; text-transform: uppercase;">Approval Sign-Off Required</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">A meeting booking request requires your administrative review and sign-off.</p>
    
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Meeting Title:</strong> {{meeting.title}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Requested By:</strong> {{recipient.name}} ({{recipient.email}})</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Requested Time:</strong> {{meeting.starts_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Duration:</strong> {{meeting.duration_minutes}} minutes</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Department:</strong> {{meeting.department_name}}</p>
    </div>

    <div style="text-align: center; margin: 24px 0;">
        <a href="{{review_url}}" style="display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 10px; font-weight: 700; font-size: 13px;">Review &amp; Approve Request</a>
    </div>

    <p style="font-size: 12px; color: #64748b;">You can approve, reject, or assign a specific Zoom resource pool through the portal.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Approval Sign-Off Required
--------------------------------------------------
Hello {{recipient.name}},

A meeting request requires your sign-off: {{meeting.title}}

Requested By: {{recipient.name}} ({{recipient.email}})
Requested Time: {{meeting.starts_at}}
Duration: {{meeting.duration_minutes}} minutes

Review and take action at: {{review_url}}
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.starts_at',
                    'meeting.ends_at',
                    'meeting.duration_minutes',
                    'meeting.department_name',
                    'review_url',
                ],
            ],

            'meeting_approved' => [
                'key' => 'meeting_approved',
                'name' => 'Meeting Request Approved',
                'category' => 'approvals',
                'description' => 'Sent to the requester when an administrative approver confirms their booking request.',
                'subject_template' => '[{{org.name}}] Approved: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #16a34a; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #15803d; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #16a34a; font-weight: 700; text-transform: uppercase;">Request Approved</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">Good news! Your meeting request <strong>{{meeting.title}}</strong> has been approved.</p>
    
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Meeting Title:</strong> {{meeting.title}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Starts At:</strong> {{meeting.starts_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Join URL:</strong> <a href="{{meeting.join_url}}" style="color: #2563eb; word-break: break-all; font-weight: 600;">{{meeting.join_url}}</a></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Passcode:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace;">{{meeting.passcode}}</code></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Approved By:</strong> {{approver.name}}</p>
    </div>

    <p style="font-size: 13px; color: #475569;">A calendar invite (.ics) with complete host details has been dispatched.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Meeting Request Approved
--------------------------------------------------
Hello {{recipient.name}},

Your meeting request {{meeting.title}} has been approved.

Starts At: {{meeting.starts_at}}
Join URL: {{meeting.join_url}}
Passcode: {{meeting.passcode}}
Approved By: {{approver.name}}

To manage this meeting, log into {{org.name}} at {{org.website}}.
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.starts_at',
                    'meeting.ends_at',
                    'meeting.duration_minutes',
                    'meeting.join_url',
                    'meeting.passcode',
                    'approver.name',
                ],
            ],

            'meeting_rejected' => [
                'key' => 'meeting_rejected',
                'name' => 'Meeting Request Declined',
                'category' => 'approvals',
                'description' => 'Sent to the requester when an administrative approver declines their booking request.',
                'subject_template' => '[{{org.name}}] Declined: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #e11d48; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #be123c; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #e11d48; font-weight: 700; text-transform: uppercase;">Request Declined</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">Your meeting request <strong>{{meeting.title}}</strong> for {{meeting.starts_at}} could not be approved at this time.</p>
    
    <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 0; font-size: 13px; color: #9f1239;"><strong>Reason Provided:</strong></p>
        <p style="margin: 8px 0 0 0; font-size: 13px; color: #881337;">{{reason}}</p>
    </div>

    <p style="font-size: 13px; color: #475569;">You are welcome to submit a request for an alternate time slot or contact your department administrator.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Meeting Request Declined
--------------------------------------------------
Hello {{recipient.name}},

Your meeting request {{meeting.title}} for {{meeting.starts_at}} was declined.

Reason: {{reason}}

You are welcome to select an alternate time on {{org.website}}.
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.starts_at',
                    'reason',
                    'approver.name',
                ],
            ],

            'meeting_cancelled' => [
                'key' => 'meeting_cancelled',
                'name' => 'Meeting Booking Cancelled',
                'category' => 'cancellations',
                'description' => 'Sent to participants and organizers when an active or scheduled meeting is cancelled.',
                'subject_template' => '[{{org.name}}] Cancelled: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #64748b; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #334155; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #64748b; font-weight: 700; text-transform: uppercase;">Meeting Cancelled</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">The meeting <strong>{{meeting.title}}</strong> originally scheduled for {{meeting.starts_at}} has been cancelled.</p>
    
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 0; font-size: 13px; color: #475569;"><strong>Cancellation Reason:</strong></p>
        <p style="margin: 6px 0 0 0; font-size: 13px; color: #1e293b;">{{reason}}</p>
    </div>

    <p style="font-size: 13px; color: #475569;">A calendar cancellation update has been dispatched to free the time on your calendar.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Meeting Cancelled
--------------------------------------------------
Hello {{recipient.name}},

The meeting {{meeting.title}} scheduled for {{meeting.starts_at}} has been cancelled.

Reason: {{reason}}
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.starts_at',
                    'reason',
                ],
            ],

            'meeting_waitlisted' => [
                'key' => 'meeting_waitlisted',
                'name' => 'Meeting Placed on Priority Waitlist',
                'category' => 'waitlist',
                'description' => 'Sent when all pooled resources are busy and the meeting is placed into the priority waitlist queue.',
                'subject_template' => '[{{org.name}}] Waitlisted: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #8b5cf6; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #6d28d9; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #8b5cf6; font-weight: 700; text-transform: uppercase;">Waitlist Notification</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">All Zoom resource accounts are currently fully booked for your requested time slot. Your meeting <strong>{{meeting.title}}</strong> has been added to our priority waitlist.</p>
    
    <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Requested Time:</strong> {{meeting.starts_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Duration:</strong> {{meeting.duration_minutes}} minutes</p>
        <p style="margin: 6px 0; font-size: 13px; color: #7c3aed;"><strong>How it works:</strong> If another meeting in this time slot cancels or ends early, your booking will be automatically allocated and confirmed.</p>
    </div>

    <p style="font-size: 13px; color: #475569;">You will receive an instant email confirmation as soon as a host license frees up.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Priority Waitlist
--------------------------------------------------
Hello {{recipient.name}},

All Zoom host resources are currently reserved. Your meeting {{meeting.title}} for {{meeting.starts_at}} has been queued on the priority waitlist.

If another meeting cancels or ends early, your meeting will be allocated and confirmed automatically.
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.starts_at',
                    'meeting.duration_minutes',
                ],
            ],

            'waitlist_allocated' => [
                'key' => 'waitlist_allocated',
                'name' => 'Waitlist Slot Allocated & Confirmed',
                'category' => 'waitlist',
                'description' => 'Sent when a pooled resource frees up and a waitlisted meeting is automatically promoted and confirmed.',
                'subject_template' => '[{{org.name}}] Allocated from Waitlist: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #16a34a; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #15803d; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #16a34a; font-weight: 700; text-transform: uppercase;">Waitlist Promoted &amp; Confirmed</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">A Zoom host account has freed up! Your waitlisted meeting <strong>{{meeting.title}}</strong> has been confirmed.</p>
    
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Starts At:</strong> {{meeting.starts_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Join URL:</strong> <a href="{{meeting.join_url}}" style="color: #2563eb; word-break: break-all; font-weight: 600;">{{meeting.join_url}}</a></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Passcode:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace;">{{meeting.passcode}}</code></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Host Key PIN:</strong> <code style="background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: bold;">{{meeting.host_key}}</code></p>
    </div>

    <p style="font-size: 13px; color: #475569;">A calendar invitation (.ics) has been attached to this email.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Slot Allocated from Waitlist
--------------------------------------------------
Hello {{recipient.name}},

A Zoom resource has opened up! Your meeting {{meeting.title}} is now confirmed.

Starts At: {{meeting.starts_at}}
Join URL: {{meeting.join_url}}
Passcode: {{meeting.passcode}}
Host Key PIN: {{meeting.host_key}}
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.starts_at',
                    'meeting.ends_at',
                    'meeting.duration_minutes',
                    'meeting.join_url',
                    'meeting.passcode',
                    'meeting.host_key',
                ],
            ],

            'meeting_attendance_report' => [
                'key' => 'meeting_attendance_report',
                'name' => 'Meeting Attendance Report',
                'category' => 'attendance',
                'description' => 'Delivered to meeting organizers and requesters with a complete participant summary table after attendance synchronization completes.',
                'subject_template' => '[{{org.name}}] Attendance Report: {{meeting.title}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 650px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #1e40af; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #64748b; font-weight: 500;">Meeting Attendance &amp; Participation Report</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">The attendee records for your meeting <strong>{{meeting.title}}</strong> have concluded and been synchronized.</p>
    
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 4px 0; font-size: 13px;"><strong>Meeting:</strong> {{meeting.title}}</p>
        <p style="margin: 4px 0; font-size: 13px;"><strong>Scheduled Time:</strong> {{meeting.starts_at}} – {{meeting.ends_at}}</p>
        <p style="margin: 4px 0; font-size: 13px;"><strong>Total Participants Synced:</strong> <span style="font-weight: 700; color: #2563eb;">{{attendees_count}}</span></p>
    </div>

    <h3 style="font-size: 14px; font-weight: 700; color: #0f172a; margin: 20px 0 10px 0;">Participant Breakdown</h3>
    <div style="margin: 16px 0; overflow-x: auto;">
        {{attendance_table_html}}
    </div>

    <p style="font-size: 13px; color: #475569;">To view detailed analytics or download CSV logs, visit the <a href="{{org.website}}" style="color: #2563eb; font-weight: 600;">{{org.name}} Portal</a>.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This report was automatically generated and delivered by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Meeting Attendance Report
--------------------------------------------------
Hello {{recipient.name}},

The attendance records for your meeting {{meeting.title}} have been synchronized.

Meeting: {{meeting.title}}
Scheduled Time: {{meeting.starts_at}} - {{meeting.ends_at}}
Total Attendees: {{attendees_count}}

Attendance Summary:
{{attendance_summary_text}}

To download full CSV logs, visit {{org.website}}.
--------------------------------------------------
This report was automatically generated by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'meeting.title',
                    'meeting.starts_at',
                    'meeting.ends_at',
                    'meeting.duration_minutes',
                    'attendees_count',
                    'attendance_table_html',
                    'attendance_summary_text',
                ],
            ],

            'recording_ready' => [
                'key' => 'recording_ready',
                'name' => 'Cloud Recording Ready Notification',
                'category' => 'recordings',
                'description' => 'Automatically sent to the meeting host/organizer when Zoom Cloud completes transcoding the session recording.',
                'subject_template' => '[{{org.name}}] Cloud Recording Ready: {{recording.topic}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #1e40af; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #64748b; font-weight: 500;">Cloud Recording Available</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">The cloud recording for <strong>{{recording.topic}}</strong> has finished processing and is ready for playback.</p>
    
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Topic:</strong> {{recording.topic}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Recorded Date:</strong> {{recording.formatted_start}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Duration:</strong> {{recording.duration_minutes}} minutes</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Share URL:</strong> <a href="{{recording.share_url}}" style="color: #2563eb; word-break: break-all; font-weight: 600;">{{recording.share_url}}</a></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Passcode:</strong> <code style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 4px; font-family: monospace; font-weight: bold;">{{recording.passcode}}</code></p>
    </div>

    <div style="margin: 20px 0; text-align: center;">
        <a href="{{recording.share_url}}" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 13px;">Watch Recording</a>
    </div>

    <div style="background: #f1f5f9; border-radius: 8px; padding: 12px; margin: 16px 0; font-size: 12px; color: #475569;">
        <strong>Full Share Text:</strong><br>
        Topic: {{recording.topic}}<br>
        Date: {{recording.formatted_start}}<br>
        Link: {{recording.share_url}}<br>
        Passcode: {{recording.passcode}}
    </div>

    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Cloud Recording Ready
--------------------------------------------------
Hello {{recipient.name}},

The cloud recording for {{recording.topic}} is now ready.

Topic: {{recording.topic}}
Recorded Date: {{recording.formatted_start}}
Duration: {{recording.duration_minutes}} minutes
Recording Link: {{recording.share_url}}
Passcode: {{recording.passcode}}

Full Invitation:
Topic: {{recording.topic}}
Recording Link: {{recording.share_url}}
Passcode: {{recording.passcode}}
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'recording.topic',
                    'recording.formatted_start',
                    'recording.duration_minutes',
                    'recording.share_url',
                    'recording.passcode',
                ],
            ],

            'recording_invitation' => [
                'key' => 'recording_invitation',
                'name' => 'Cloud Recording Shared Invitation',
                'category' => 'recordings',
                'description' => 'Sent to students, team members, or attendees when an organizer shares a recording link with custom notes.',
                'subject_template' => '[{{org.name}}] Recording Available: {{recording.topic}}',
                'body_html_template' => <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #0284c7; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #0369a1; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #0284c7; font-weight: 700; text-transform: uppercase;">Session Recording Shared With You</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">You have been invited to view the recording for <strong>{{recording.topic}}</strong>.</p>
    
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Topic:</strong> {{recording.topic}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Date:</strong> {{recording.formatted_start}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Duration:</strong> {{recording.duration_minutes}} minutes</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Recording Link:</strong> <a href="{{recording.share_url}}" style="color: #0284c7; word-break: break-all; font-weight: 600;">{{recording.share_url}}</a></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Passcode:</strong> <code style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 4px; font-family: monospace; font-weight: bold;">{{recording.passcode}}</code></p>
    </div>

    <div style="margin: 20px 0; text-align: center;">
        <a href="{{recording.share_url}}" style="display: inline-block; background: #0284c7; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 700; font-size: 13px;">Watch Recording</a>
    </div>

    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML,
                'body_text_template' => <<<'TEXT'
{{org.name}} - Recording Shared
--------------------------------------------------
Hello {{recipient.name}},

You have been invited to view the recording for {{recording.topic}}.

Topic: {{recording.topic}}
Date: {{recording.formatted_start}}
Duration: {{recording.duration_minutes}} minutes
Recording Link: {{recording.share_url}}
Passcode: {{recording.passcode}}
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT,
                'available_variables' => [
                    'org.name',
                    'org.website',
                    'recipient.name',
                    'recipient.email',
                    'recording.topic',
                    'recording.formatted_start',
                    'recording.duration_minutes',
                    'recording.share_url',
                    'recording.passcode',
                    'custom_message',
                ],
            ],
        ];
    }

    /**
     * Get default definition for a specific template key.
     *
     * @return array{
     *     key: string,
     *     name: string,
     *     category: string,
     *     description: string,
     *     subject_template: string,
     *     body_html_template: string,
     *     body_text_template: string,
     *     available_variables: array<int, string>
     * }|null
     */
    public static function getDefaultTemplate(string $key): ?array
    {
        $all = self::getDefaultTemplates();

        return $all[$key] ?? null;
    }

    /**
     * Seed or backfill all missing templates into the database.
     *
     * @return int Number of templates created or updated.
     */
    public static function seedMissingTemplates(): int
    {
        $defaults = self::getDefaultTemplates();
        $count = 0;

        foreach ($defaults as $key => $data) {
            /** @var EmailTemplate|null $existing */
            $existing = EmailTemplate::where('key', $key)->first();

            if (! $existing) {
                EmailTemplate::create([
                    'key' => $data['key'],
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'subject_template' => $data['subject_template'],
                    'body_html_template' => $data['body_html_template'],
                    'body_text_template' => $data['body_text_template'],
                    'available_variables' => $data['available_variables'],
                    'is_active' => true,
                    'locale' => 'en',
                ]);
                $count++;
            } else {
                // If existing template is missing category or description, backfill them
                $needsSave = false;
                if (empty($existing->category)) {
                    $existing->category = $data['category'];
                    $needsSave = true;
                }
                if (empty($existing->description)) {
                    $existing->description = $data['description'];
                    $needsSave = true;
                }
                if (empty($existing->available_variables)) {
                    $existing->available_variables = $data['available_variables'];
                    $needsSave = true;
                }
                if ($needsSave) {
                    $existing->save();
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Reset a single template back to its factory default.
     */
    public static function resetToDefault(EmailTemplate $template): EmailTemplate
    {
        $default = self::getDefaultTemplate($template->key);
        if (! $default) {
            return $template;
        }

        $template->update([
            'name' => $default['name'],
            'category' => $default['category'],
            'description' => $default['description'],
            'subject_template' => $default['subject_template'],
            'body_html_template' => $default['body_html_template'],
            'body_text_template' => $default['body_text_template'],
            'available_variables' => $default['available_variables'],
            'is_active' => true,
        ]);

        return $template->fresh();
    }

    /**
     * Reset all templates in the database to factory defaults.
     */
    public static function resetAllToDefaults(): int
    {
        $defaults = self::getDefaultTemplates();
        $updated = 0;

        foreach ($defaults as $key => $data) {
            EmailTemplate::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $data['name'],
                    'category' => $data['category'],
                    'description' => $data['description'],
                    'subject_template' => $data['subject_template'],
                    'body_html_template' => $data['body_html_template'],
                    'body_text_template' => $data['body_text_template'],
                    'available_variables' => $data['available_variables'],
                    'is_active' => true,
                    'locale' => 'en',
                ]
            );
            $updated++;
        }

        return $updated;
    }

    /**
     * Generate rich, realistic mock context for testing and previewing templates.
     *
     * @return array<string, mixed>
     */
    public static function getMockContext(string $templateKey): array
    {
        $orgName = (string) (Setting::get('org.name') ?: 'Reference University');
        $orgWebsite = url('/');

        $sampleStart = now()->addDay()->setHour(14)->setMinute(0);
        $sampleEnd = (clone $sampleStart)->addHour();

        $attendanceTableHtml = <<<'HTML'
<table style="width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 8px;">
    <thead>
        <tr style="background-color: #f1f5f9; text-align: left; color: #475569;">
            <th style="padding: 8px 10px; border-bottom: 2px solid #cbd5e1;">Participant</th>
            <th style="padding: 8px 10px; border-bottom: 2px solid #cbd5e1;">Join Time</th>
            <th style="padding: 8px 10px; border-bottom: 2px solid #cbd5e1;">Duration</th>
            <th style="padding: 8px 10px; border-bottom: 2px solid #cbd5e1;">Attendance</th>
            <th style="padding: 8px 10px; border-bottom: 2px solid #cbd5e1;">Device</th>
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom: 1px solid #e2e8f0;">
            <td style="padding: 8px 10px;"><strong>Prof. Einstein</strong><br><span style="color: #64748b; font-size: 11px;">einstein@univ.edu</span></td>
            <td style="padding: 8px 10px; font-family: monospace;">14:00</td>
            <td style="padding: 8px 10px;">60 mins</td>
            <td style="padding: 8px 10px;"><span style="color: #16a34a; font-weight: bold;">100%</span></td>
            <td style="padding: 8px 10px; color: #64748b;">Mac</td>
        </tr>
        <tr style="border-bottom: 1px solid #e2e8f0; background-color: #f8fafc;">
            <td style="padding: 8px 10px;"><strong>Dr. Niels Bohr</strong><br><span style="color: #64748b; font-size: 11px;">bohr@univ.edu</span></td>
            <td style="padding: 8px 10px; font-family: monospace;">14:02</td>
            <td style="padding: 8px 10px;">58 mins</td>
            <td style="padding: 8px 10px;"><span style="color: #16a34a; font-weight: bold;">97%</span></td>
            <td style="padding: 8px 10px; color: #64748b;">Windows</td>
        </tr>
        <tr style="border-bottom: 1px solid #e2e8f0;">
            <td style="padding: 8px 10px;"><strong>Marie Curie</strong><br><span style="color: #64748b; font-size: 11px;">curie@univ.edu</span></td>
            <td style="padding: 8px 10px; font-family: monospace;">14:05</td>
            <td style="padding: 8px 10px;">55 mins</td>
            <td style="padding: 8px 10px;"><span style="color: #16a34a; font-weight: bold;">92%</span></td>
            <td style="padding: 8px 10px; color: #64748b;">iPad</td>
        </tr>
    </tbody>
</table>
HTML;

        $attendanceSummaryText = <<<'TEXT'
1. Prof. Einstein (einstein@univ.edu) - 60 mins (100%) - Mac
2. Dr. Niels Bohr (bohr@univ.edu) - 58 mins (97%) - Windows
3. Marie Curie (curie@univ.edu) - 55 mins (92%) - iPad
TEXT;

        return [
            'org.name' => $orgName,
            'org.tagline' => 'Enterprise Zoom Resource Allocation & Governance',
            'org.support_email' => Setting::get('org.support_email', 'it-support@univ.edu'),
            'org.website' => $orgWebsite,

            'recipient.name' => 'Dr. Jane Sample',
            'recipient.email' => 'jane.sample@univ.edu',
            'recipient_name' => 'Dr. Jane Sample',
            'recipient_email' => 'jane.sample@univ.edu',

            'meeting.id' => '01J9SAMPLEMEETINGID',
            'meeting.title' => 'Advanced Robotics & Machine Learning Lab',
            'meeting.description' => 'Discussion on autonomous trajectory planning and neural kinematics.',
            'meeting.starts_at' => $sampleStart->format('Y-m-d H:i T'),
            'meeting.ends_at' => $sampleEnd->format('Y-m-d H:i T'),
            'meeting.starts_at_iso' => $sampleStart->toIso8601String(),
            'meeting.ends_at_iso' => $sampleEnd->toIso8601String(),
            'meeting.duration_minutes' => '60',
            'meeting.timezone' => 'Asia/Kolkata',
            'meeting.department_name' => 'Computer Science & Engineering',
            'meeting.join_url' => 'https://krea-edu-in.zoom.us/j/84920491823?pwd=samplepasscode',
            'meeting.passcode' => '581920',
            'meeting.host_key' => '618492',
            'meeting.google_calendar_url' => 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=Advanced+Robotics',
            'meeting.outlook_calendar_url' => 'https://outlook.office.com/calendar/0/deeplink/compose?subject=Advanced+Robotics',
            'meeting.ics_url' => url('/meetings/01J9SAMPLEMEETINGID/ics'),

            'review_url' => url('/app/approvals'),
            'reason' => 'Requested pooled capacity exceeds peak concurrent limits during exam window.',
            'approver.name' => 'Admin Controller (IT Office)',

            'attendees_count' => '3',
            'attendance_table_html' => $attendanceTableHtml,
            'attendance_summary_text' => $attendanceSummaryText,

            'recording.topic' => 'Advanced Robotics & Machine Learning Lab',
            'recording.formatted_start' => now()->subDay()->format('M d, Y h:i A'),
            'recording.duration_minutes' => '58',
            'recording.share_url' => 'https://krea-edu-in.zoom.us/rec/share/sample_token_84920491823',
            'recording.passcode' => 'SecureRec@2026',
            'custom_message' => 'Please review the lecture before our lab evaluation on Thursday.',
        ];
    }
}
