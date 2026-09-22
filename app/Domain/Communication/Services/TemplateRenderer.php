<?php

namespace App\Domain\Communication\Services;

use App\Domain\Meetings\Models\Meeting;
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
        $vars['org.name'] = Setting::get('org.name', config('app.name', 'Zoom Pool Manager'));
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

            // Passcode: ONLY include if security profile permits or setting is enabled
            $allowPasscodeInInvite = $meeting->securityProfile?->settings['passcode_in_invite'] ?? true;
            if ($allowPasscodeInInvite && ! empty($meeting->passcode)) {
                $vars['meeting.passcode'] = (string) $meeting->passcode;
            } else {
                $vars['meeting.passcode'] = '[Protected / Included in Join Link]';
            }

            // CRITICAL SECURITY RULE: start_url and host_key are NEVER exposed in emails
            // If anyone requests start_url, redirect them to the authenticated portal
            $vars['meeting.start_url'] = route('meetings.show', $meeting->public_id);
            $vars['meeting.host_key'] = '[Log into ZPM to reveal host key during meeting]';
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
