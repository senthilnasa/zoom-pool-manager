<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Jobs\SendQueuedEmailJob;
use App\Domain\Communication\Models\EmailDelivery;
use App\Domain\Communication\Models\EmailTemplate;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailDeliveryService
{
    public function __construct(
        protected TemplateRenderer $renderer
    ) {}

    /**
     * Queue a deduplicated email for delivery.
     *
     * @param  string  $templateKey  The email template key
     * @param  string  $recipientEmail  Target email address
     * @param  string|null  $recipientName  Target user name
     * @param  array<string, mixed>  $context  Data for template variables
     * @param  Meeting|null  $meeting  Associated meeting if any
     * @param  string|null  $eventId  Unique event or action identifier for deduplication
     * @param  array<int, array{name: string, data: string, mime: string}>  $attachments
     * @param  bool  $sync  If true, dispatch synchronously (for testing or emergency)
     */
    public function queueEmail(
        string $templateKey,
        string $recipientEmail,
        ?string $recipientName = null,
        array $context = [],
        ?Meeting $meeting = null,
        ?string $eventId = null,
        array $attachments = [],
        bool $sync = false
    ): ?EmailDelivery {
        $eventId = $eventId ?: ($meeting ? "meeting_{$meeting->public_id}_{$meeting->status}" : 'event_'.bin2hex(random_bytes(8)));
        $dedupeKey = hash('sha256', "{$eventId}:{$recipientEmail}:{$templateKey}");

        // 1. Deduplication check: if an identical event was already sent or is in transit, skip
        $existing = EmailDelivery::where('dedupe_key', $dedupeKey)->first();
        if ($existing && in_array($existing->status, ['sent', 'sending'], true)) {
            Log::channel('single')->info("Email deduplicated and skipped: [{$dedupeKey}] to [{$recipientEmail}]");

            return $existing;
        }

        // 2. Fetch template
        /** @var EmailTemplate|null $template */
        $template = EmailTemplate::active()
            ->where('key', $templateKey)
            ->first();

        $subjectTemplate = $template ? $template->subject_template : $this->getDefaultSubject($templateKey);
        $htmlTemplate = $template ? $template->body_html_template : $this->getDefaultHtmlBody($templateKey);
        $textTemplate = $template ? $template->body_text_template : $this->getDefaultTextBody($templateKey);

        // 3. Render content
        $context['recipient_email'] = $recipientEmail;
        $context['recipient_name'] = $recipientName;
        if ($meeting && ! isset($context['meeting'])) {
            $context['meeting'] = $meeting;
        }
        if (! isset($context['recipient'])) {
            if ($meeting && $meeting->owner && strcasecmp($meeting->owner->email, $recipientEmail) === 0) {
                $context['recipient'] = $meeting->owner;
            } elseif ($meeting && $meeting->requester && strcasecmp($meeting->requester->email, $recipientEmail) === 0) {
                $context['recipient'] = $meeting->requester;
            } else {
                $context['recipient'] = User::where('email', $recipientEmail)->first();
            }
        }

        $rendered = $this->renderer->render(
            subjectTemplate: $subjectTemplate,
            bodyHtmlTemplate: $htmlTemplate,
            bodyTextTemplate: $textTemplate,
            context: $context
        );

        // 4. Create or update delivery record
        $delivery = $existing ?: new EmailDelivery;
        $delivery->dedupe_key = $dedupeKey;
        $delivery->meeting_id = $meeting?->id;
        $delivery->recipient_email = $recipientEmail;
        $delivery->recipient_name = $recipientName;
        $delivery->template_key = $templateKey;
        $delivery->subject = $rendered['subject'];
        $delivery->body_html = $rendered['html'];
        $delivery->body_text = $rendered['text'];
        $delivery->status = 'queued';
        $delivery->save();

        // 5. Dispatch job
        $sendImmediately = (bool) Setting::get('mail.send_immediately', true);
        if ($sync || config('queue.default') === 'sync' || $sendImmediately) {
            try {
                SendQueuedEmailJob::dispatchSync($delivery, $attachments);
            } catch (Throwable $e) {
                Log::channel('single')->warning("Synchronous email dispatch error: {$e->getMessage()}");
            }
        } else {
            SendQueuedEmailJob::dispatch($delivery, $attachments);
        }

        return $delivery;
    }

    protected function getDefaultSubject(string $key): string
    {
        return match ($key) {
            'meeting_requested' => '[{{org.name}}] Approval Requested: {{meeting.title}}',
            'meeting_approved' => '[{{org.name}}] Approved: {{meeting.title}}',
            'meeting_rejected' => '[{{org.name}}] Declined: {{meeting.title}}',
            'meeting_confirmed' => '[{{org.name}}] Confirmed: {{meeting.title}}',
            'meeting_cancelled' => '[{{org.name}}] Cancelled: {{meeting.title}}',
            'meeting_waitlisted' => '[{{org.name}}] Waitlisted: {{meeting.title}}',
            'waitlist_allocated' => '[{{org.name}}] Allocated from Waitlist: {{meeting.title}}',
            'start_reminder' => '[{{org.name}}] Reminder (15 min): {{meeting.title}}',
            'meeting_attendance_report' => '[{{org.name}}] Attendance Report: {{meeting.title}}',
            'recording_ready' => '[{{org.name}}] Cloud Recording Ready: {{recording.topic}}',
            'recording_invitation' => '[{{org.name}}] Recording Available: {{recording.topic}}',
            default => '[{{org.name}}] Notification regarding {{meeting.title}}',
        };
    }

    protected function getDefaultHtmlBody(string $key): string
    {
        if ($key === 'meeting_attendance_report') {
            return <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 650px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #1e40af; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #64748b; font-weight: 500;">Meeting Attendance Report</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">The attendance records for your meeting <strong>{{meeting.title}}</strong> have been synchronized.</p>
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Meeting:</strong> {{meeting.title}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Scheduled Time:</strong> {{meeting.starts_at}} - {{meeting.ends_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Total Attendees:</strong> <span style="background: #e0e7ff; color: #3730a3; padding: 2px 8px; border-radius: 9999px; font-weight: bold;">{{attendees_count}}</span></p>
    </div>
    <div style="margin: 20px 0;">
        {{attendance_table}}
    </div>
    <p style="font-size: 13px; color: #475569;">To view detailed analytics or download CSV attendance logs, visit the <a href="{{org.website}}" style="color: #2563eb; font-weight: 600;">{{org.name}} Portal</a>.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This report was automatically generated and delivered by {{org.name}}.</p>
</div>
HTML;
        }

        if ($key === 'recording_ready' || $key === 'recording_invitation') {
            return <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #1e40af; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #64748b; font-weight: 500;">Cloud Recording Notification</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">The cloud recording for <strong>{{recording.topic}}</strong> is now available.</p>
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Topic:</strong> {{recording.topic}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Recorded Date:</strong> {{recording.formatted_start}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Duration:</strong> {{recording.duration_minutes}} minutes</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Playback / Share URL:</strong> <a href="{{recording.share_url}}" style="color: #2563eb; word-break: break-all; font-weight: 600;">{{recording.share_url}}</a></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Passcode:</strong> <code style="background: #fef3c7; color: #92400e; padding: 3px 8px; border-radius: 4px; font-family: monospace; font-weight: bold;">{{recording.passcode}}</code></p>
    </div>
    <div style="margin: 20px 0; text-align: center;">
        <a href="{{recording.share_url}}" style="display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 13px;">Watch Recording</a>
    </div>
    <div style="background: #f1f5f9; border-radius: 8px; padding: 12px; margin: 16px 0; font-size: 12px; color: #475569;">
        <strong>Copy-Paste Invitation Text:</strong><br>
        Topic: {{recording.topic}}<br>
        Recording Link: {{recording.share_url}}<br>
        Passcode: {{recording.passcode}}
    </div>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML;
        }

        return <<<'HTML'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 16px; background-color: #ffffff;">
    <div style="border-bottom: 2px solid #2563eb; padding-bottom: 16px; margin-bottom: 20px;">
        <h2 style="color: #1e40af; margin: 0; font-size: 22px; font-weight: 700;">{{org.name}}</h2>
        <span style="font-size: 12px; color: #64748b; font-weight: 500;">Zoom Pool Management Portal</span>
    </div>
    <p style="font-size: 14px; margin-bottom: 12px;">Hello {{recipient.name}},</p>
    <p style="font-size: 14px; margin-bottom: 16px;">This is an automated notification regarding your meeting <strong>{{meeting.title}}</strong>.</p>
    <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 16px; margin: 20px 0;">
        <p style="margin: 6px 0; font-size: 13px;"><strong>Starts At:</strong> {{meeting.starts_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Ends At:</strong> {{meeting.ends_at}}</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Duration:</strong> {{meeting.duration_minutes}} minutes</p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Join URL:</strong> <a href="{{meeting.join_url}}" style="color: #2563eb; word-break: break-all;">{{meeting.join_url}}</a></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Passcode:</strong> <code style="background: #e2e8f0; padding: 2px 6px; border-radius: 4px; font-family: monospace;">{{meeting.passcode}}</code></p>
        <p style="margin: 6px 0; font-size: 13px;"><strong>Host Key PIN:</strong> <code style="background: #e0e7ff; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-family: monospace; font-weight: bold;">{{meeting.host_key}}</code> <span style="font-size: 11px; color: #64748b;">(Claim Host: In Zoom client &gt; Participants &gt; Claim Host)</span></p>
    </div>
    <p style="font-size: 13px; color: #475569;">To view host controls or manage this booking, log into the <a href="{{org.website}}" style="color: #2563eb; font-weight: 600;">{{org.name}} Portal</a>.</p>
    <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;" />
    <p style="font-size: 11px; color: #94a3b8; margin: 0;">This email was sent automatically by {{org.name}}.</p>
</div>
HTML;
    }

    protected function getDefaultTextBody(string $key): string
    {
        if ($key === 'meeting_attendance_report') {
            return <<<'TEXT'
{{org.name}} - Meeting Attendance Report
--------------------------------------------------
Hello {{recipient.name}},

The attendance records for your meeting {{meeting.title}} have been synchronized.

Meeting: {{meeting.title}}
Scheduled Time: {{meeting.starts_at}} - {{meeting.ends_at}}
Total Attendees: {{attendees_count}}

Attendance Summary:
{{attendance_summary_text}}

To view full records, log into {{org.name}} at {{org.website}}.
--------------------------------------------------
This report was automatically generated by {{org.name}}.
TEXT;
        }

        if ($key === 'recording_ready' || $key === 'recording_invitation') {
            return <<<'TEXT'
{{org.name}} - Cloud Recording Notification
--------------------------------------------------
Hello {{recipient.name}},

The cloud recording for {{recording.topic}} is now available.

Topic: {{recording.topic}}
Recorded Date: {{recording.formatted_start}}
Duration: {{recording.duration_minutes}} minutes
Recording Link: {{recording.share_url}}
Passcode: {{recording.passcode}}

Full Invitation:
Topic: {{recording.topic}}
Recording Link: {{recording.share_url}}
Passcode: {{recording.passcode}}

To view recordings or manage access, log into {{org.name}} at {{org.website}}.
--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT;
        }

        return <<<'TEXT'
{{org.name}}
--------------------------------------------------
Hello {{recipient.name}},

This is an automated notification regarding your meeting {{meeting.title}}.

Starts At: {{meeting.starts_at}}
Ends At: {{meeting.ends_at}}
Duration: {{meeting.duration_minutes}} minutes
Join URL: {{meeting.join_url}}
Passcode: {{meeting.passcode}}
Host Key PIN: {{meeting.host_key}} (Claim Host: In Zoom client > Participants > Claim Host)

To view host controls or manage this booking, log into {{org.name}} at {{org.website}}.

--------------------------------------------------
This email was sent automatically by {{org.name}}.
TEXT;
    }
}
