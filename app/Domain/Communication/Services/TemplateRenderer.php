<?php

namespace App\Domain\Communication\Services;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;

class TemplateRenderer
{
    /**
     * Render subject, HTML body, and plain text body with whitelisted variable substitution.
     *
     * @param  array<string, mixed>  $context
     * @return array{subject: string, html: string, text: string}
     */
    public function render(
        string $subjectTemplate,
        string $bodyHtmlTemplate,
        string $bodyTextTemplate,
        array $context = []
    ): array {
        $variables = $this->extractVariables($context);

        $renderedSubject = $this->interpolate($subjectTemplate, $variables, false);
        $renderedHtml = $this->interpolate($bodyHtmlTemplate, $variables, true);
        $renderedText = $this->interpolate($bodyTextTemplate, $variables, false);

        // Sanitize HTML output to prevent script execution
        $sanitizedHtml = $this->sanitizeHtml($renderedHtml);

        return [
            'subject' => $renderedSubject,
            'html' => $sanitizedHtml,
            'text' => strip_tags($renderedText),
        ];
    }

    /**
     * Build standard whitelist variables from meeting, recipient, and additional context.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    public function extractVariables(array $context): array
    {
        $vars = [];

        /** @var Meeting|null $meeting */
        $meeting = $context['meeting'] ?? null;
        /** @var User|null $recipient */
        $recipient = $context['recipient'] ?? null;

        // Organization info
        $orgName = trim((string) (
            Setting::get('org.name')
            ?: Setting::get('organization_name')
            ?: Setting::get('org_name')
            ?: config('app.organization_name')
            ?: (config('app.name') !== 'Laravel' ? config('app.name') : null)
            ?: 'Zoom Pool Manager'
        ));
        $vars['org.name'] = $orgName;
        $vars['org.tagline'] = (string) Setting::get('org.tagline', '');
        $vars['org.support_email'] = Setting::get('org.support_email', 'support@zoompoolmanager.org');
        $vars['org.website'] = Setting::get('org.website', url('/'));

        // Recipient info
        $vars['recipient.name'] = $recipient ? $recipient->name : (string) ($context['recipient_name'] ?? 'Attendee');
        $vars['recipient.email'] = $recipient ? $recipient->email : (string) ($context['recipient_email'] ?? '');

        // Meeting info
        if ($meeting) {
            $timezone = $meeting->timezone ?: Setting::get('org.timezone', 'Asia/Kolkata');

            $vars['meeting.id'] = (string) $meeting->public_id;
            $vars['meeting.title'] = (string) $meeting->title;
            $vars['meeting.description'] = (string) ($meeting->description ?? '');
            $vars['meeting.meeting_type'] = ucfirst($meeting->meeting_type);
            $vars['meeting.starts_at'] = $meeting->starts_at->setTimezone($timezone)->format('Y-m-d H:i T');
            $vars['meeting.ends_at'] = $meeting->ends_at->setTimezone($timezone)->format('Y-m-d H:i T');
            $vars['meeting.duration_minutes'] = (string) $meeting->duration_minutes;
            $vars['meeting.timezone'] = $timezone;
            $vars['meeting.join_url'] = (string) ($meeting->join_url ?? route('meetings.show', $meeting->public_id));
            $vars['meeting.zoom_meeting_id'] = (string) ($meeting->zoom_meeting_id ?? 'N/A');

            $maskCredentials = (bool) Setting::get('mail.mask_credentials', true);

            // Passcode handling
            $passcode = ! empty($meeting->passcode) ? (string) $meeting->passcode : null;
            if (empty($passcode) && ! empty($meeting->join_url)) {
                $parsedUrl = parse_url($meeting->join_url);
                if (! empty($parsedUrl['query'])) {
                    parse_str($parsedUrl['query'], $queryParams);
                    if (! empty($queryParams['pwd'])) {
                        $passcode = (string) $queryParams['pwd'];
                    }
                }
            }

            if (! $maskCredentials) {
                $vars['meeting.passcode'] = ! empty($passcode) ? $passcode : '[Included in Join Link]';
            } else {
                $allowPasscodeInInvite = $meeting->securityProfile?->settings['passcode_in_invite'] ?? true;
                if ($allowPasscodeInInvite && ! empty($passcode)) {
                    $vars['meeting.passcode'] = $passcode;
                } else {
                    $vars['meeting.passcode'] = '[Protected / Included in Join Link]';
                }
            }

            // Host Key & Start URL Handling:
            // start_url is kept behind authenticated portal
            $vars['meeting.start_url'] = route('meetings.show', $meeting->public_id);

            // Host key is provided from meeting or assigned Zoom user
            $hostKey = ! empty($meeting->host_key) ? (string) $meeting->host_key : (string) ($meeting->zoomResource?->zoomUser->host_key ?? '');
            if (empty($hostKey) && $meeting->zoomResource?->zoomUser) {
                $hostKey = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $meeting->zoomResource->zoomUser->host_key = $hostKey;
                $meeting->zoomResource->zoomUser->save();
                $meeting->host_key = $hostKey;
                $meeting->saveQuietly();
            } elseif (empty($hostKey)) {
                $hostKey = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                $meeting->host_key = $hostKey;
                $meeting->saveQuietly();
            }

            if (! $maskCredentials) {
                $vars['meeting.host_key'] = ! empty($hostKey) ? $hostKey : 'N/A';
            } else {
                $recipientEmail = strtolower(trim((string) ($context['recipient_email'] ?? ($recipient ? $recipient->email : ''))));
                $ownerEmail = strtolower(trim((string) ($meeting->owner ? $meeting->owner->email : '')));
                $requesterEmail = strtolower(trim((string) ($meeting->requester ? $meeting->requester->email : '')));

                $isRequesterOrOwner = ($recipient && ($recipient->id === ($meeting->requester_user_id ?? null) || $recipient->id === ($meeting->owner_user_id ?? null)))
                    || ($recipientEmail !== '' && ($recipientEmail === $ownerEmail || $recipientEmail === $requesterEmail));

                if (($meeting->share_host_key || $isRequesterOrOwner) && ! empty($hostKey)) {
                    $vars['meeting.host_key'] = $hostKey;
                } else {
                    $vars['meeting.host_key'] = '[Log into ZPM to reveal host key during meeting]';
                }
            }
        }

        // Recording info
        /** @var CloudRecording|null $recording */
        $recording = $context['recording'] ?? null;
        if ($recording) {
            $vars['recording.topic'] = (string) $recording->topic;
            $vars['recording.share_url'] = (string) ($recording->share_url ?: $recording->play_url ?: '');
            $vars['recording.play_url'] = (string) ($recording->play_url ?: $recording->share_url ?: '');
            $vars['recording.passcode'] = (string) ($recording->passcode ?: 'None');
            $vars['recording.duration_minutes'] = (string) $recording->duration_minutes;
            $vars['recording.zoom_meeting_id'] = (string) ($recording->zoom_meeting_id ?: '');
            $vars['recording.formatted_start'] = $recording->recording_start ? $recording->recording_start->format('Y-m-d H:i') : 'N/A';
        }

        // Merge any extra explicit scalar variables (e.g. reason, approver_name, notes)
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $vars[$key] = (string) $value;
            }
        }

        return $vars;
    }

    /**
     * Replace {{variable_key}} with corresponding sanitized value.
     *
     * @param  array<string, string>  $variables
     */
    protected function interpolate(string $content, array $variables, bool $isHtml): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_\.]+)\s*\}\}/', function ($matches) use ($variables, $isHtml) {
            $key = $matches[1];
            if (! array_key_exists($key, $variables)) {
                return '';
            }

            $val = $variables[$key];

            return $isHtml ? htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $val;
        }, $content) ?? $content;
    }

    /**
     * Basic HTML sanitizer to strip dangerous script tags and event handlers.
     */
    protected function sanitizeHtml(string $html): string
    {
        // Remove script tags and contents
        $clean = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html) ?? $html;

        // Remove dangerous on* attributes
        $clean = preg_replace('/\s+on[a-zA-Z]+\s*=\s*(["\']).*?\1/i', '', $clean) ?? $clean;
        $clean = preg_replace('/\s+on[a-zA-Z]+\s*=\s*[^>\s]+/i', '', $clean) ?? $clean;

        // Remove javascript: pseudo-protocols
        $clean = preg_replace('/href\s*=\s*(["\'])javascript:.*?\1/i', 'href="#"', $clean) ?? $clean;

        return $clean;
    }
}
