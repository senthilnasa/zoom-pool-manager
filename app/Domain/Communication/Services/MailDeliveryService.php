<?php

namespace App\Domain\Communication\Services;

use App\Domain\Communication\Jobs\SendQueuedEmailJob;
use App\Domain\Communication\Models\EmailDelivery;
use App\Domain\Communication\Models\EmailTemplate;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
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
            'meeting_requested' => 'Meeting Approval Requested: {{meeting.title}}',
            'meeting_approved' => 'Meeting Approved: {{meeting.title}}',
            'meeting_rejected' => 'Meeting Request Rejected: {{meeting.title}}',
            'meeting_confirmed' => 'Meeting Confirmed: {{meeting.title}}',
            'meeting_cancelled' => 'Meeting Cancelled: {{meeting.title}}',
            'meeting_waitlisted' => 'Placed on Waitlist: {{meeting.title}}',
            'waitlist_allocated' => 'Resource Allocated from Waitlist: {{meeting.title}}',
            'start_reminder' => 'Reminder: Your meeting starts in 15 minutes: {{meeting.title}}',
            default => 'Notification regarding {{meeting.title}}',
        };
    }

    protected function getDefaultHtmlBody(string $key): string
    {
        return <<<'HTML'
<div style="font-family: sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2563eb;">{{org.name}}</h2>
    <p>Hello {{recipient.name}},</p>
    <p>This is an automated notification regarding your meeting <strong>{{meeting.title}}</strong>.</p>
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin: 20px 0;">
        <p><strong>Starts At:</strong> {{meeting.starts_at}}</p>
        <p><strong>Ends At:</strong> {{meeting.ends_at}}</p>
        <p><strong>Duration:</strong> {{meeting.duration_minutes}} minutes</p>
        <p><strong>Join URL:</strong> <a href="{{meeting.join_url}}">{{meeting.join_url}}</a></p>
        <p><strong>Passcode:</strong> {{meeting.passcode}}</p>
    </div>
    <p>To view host controls or manage this booking, log into the <a href="{{org.website}}">Zoom Pool Manager portal</a>.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;" />
    <p style="font-size: 12px; color: #64748b;">This email was sent automatically by {{org.name}} Zoom Pool Manager.</p>
</div>
HTML;
    }

    protected function getDefaultTextBody(string $key): string
    {
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

To view host controls or manage this booking, log into Zoom Pool Manager at {{org.website}}.

--------------------------------------------------
This email was sent automatically by {{org.name}} Zoom Pool Manager.
TEXT;
    }
}
