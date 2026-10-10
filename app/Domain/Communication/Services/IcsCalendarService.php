<?php

namespace App\Domain\Communication\Services;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Settings\Models\Setting;
use DateTimeZone;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;
use Spatie\IcalendarGenerator\Enums\EventStatus;
use Spatie\IcalendarGenerator\Enums\ParticipationStatus;

class IcsCalendarService
{
    /**
     * Generate an RFC 5545 iCalendar (.ics) string for a meeting.
     *
     * @param  Meeting  $meeting  The meeting model
     * @param  string  $method  'REQUEST', 'PUBLISH', or 'CANCEL'
     * @param  int|null  $sequence  Calendar sequence number
     * @param  string|null  $recipientEmail  Target recipient email for attendee customization
     */
    public function generate(
        Meeting $meeting,
        string $method = 'REQUEST',
        ?int $sequence = null,
        ?string $recipientEmail = null
    ): string {
        $orgName = trim((string) (
            Setting::get('org.name')
            ?: Setting::get('organization_name')
            ?: Setting::get('org_name')
            ?: config('app.organization_name')
            ?: (config('app.name') !== 'Laravel' ? config('app.name') : null)
            ?: 'Zoom Pool Manager'
        ));
        $hostDomain = parse_url(config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'zoompoolmanager.org';
        $stableUid = "zpm-{$meeting->public_id}@{$hostDomain}";

        // Sequence number: incremented when meeting changes
        if ($sequence === null) {
            $historyCount = $meeting->statusHistory()->count();
            $sequence = max(0, $historyCount - 1);
        }

        $joinUrl = $meeting->join_url ?: route('meetings.show', $meeting->public_id);
        $description = ($meeting->description ? "{$meeting->description}\n\n" : '')
            ."Join Zoom Meeting: {$joinUrl}\n"
            .'Meeting ID: '.($meeting->zoom_meeting_id ?: 'N/A')."\n";

        $maskCredentials = (bool) Setting::get('mail.mask_credentials', true);
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
        $hostKey = ! empty($meeting->host_key) ? (string) $meeting->host_key : (string) ($meeting->zoomResource?->zoomUser->host_key ?? '');

        if (! $maskCredentials) {
            if (! empty($passcode)) {
                $description .= "Passcode: {$passcode}\n";
            }
            if (! empty($hostKey)) {
                $description .= "Host Key PIN: {$hostKey} (In Zoom client: Participants > Claim Host > enter Host Key)\n";
            }
        } else {
            if (! empty($passcode) && ($meeting->securityProfile?->settings['passcode_in_invite'] ?? true)) {
                $description .= "Passcode: {$passcode}\n";
            }
            if ($meeting->share_host_key && ! empty($hostKey)) {
                $description .= "Host Key PIN: {$hostKey} (In Zoom client: Participants > Claim Host > enter Host Key)\n";
            }
        }

        // Use canonical UTC date-times for universal compatibility across Google Calendar, Gmail, Outlook, and Apple Calendar
        $utcTz = new DateTimeZone('UTC');
        $startsAtUtc = (clone $meeting->starts_at)->setTimezone($utcTz);
        $endsAtUtc = (clone $meeting->ends_at)->setTimezone($utcTz);

        $event = Event::create($meeting->title)
            ->uniqueIdentifier($stableUid)
            ->startsAt($startsAtUtc)
            ->endsAt($endsAtUtc)
            ->description($description)
            ->address($joinUrl)
            ->url($joinUrl);

        if ($sequence > 0) {
            $event->sequence($sequence);
        }

        if ($method === 'CANCEL' || $meeting->status === 'cancelled') {
            $event->status(EventStatus::cancelled());
        } else {
            $event->status(EventStatus::confirmed());
        }

        // Add organizer
        $owner = $meeting->owner ?? $meeting->requester;
        $ownerEmail = $owner ? strtolower(trim((string) $owner->email)) : '';
        if ($owner) {
            $event->organizer($owner->email, $owner->name);
        }

        // RFC 5545 / RFC 5546 attendee rules:
        // - METHOD:PUBLISH: Used when sending calendar events to the host/organizer or direct browser downloads.
        //   In PUBLISH, no attendee entries are required, and the organizer MUST NOT be listed as an attendee.
        // - METHOD:REQUEST: Used when sending invitations to attendees.
        //   Only invitees and distinct requesters are added as attendees with PARTSTAT=NEEDS-ACTION.
        //   The organizer MUST NEVER be added to the ATTENDEE list in REQUEST.
        $methodUpper = strtoupper($method);
        if ($methodUpper === 'REQUEST' || $methodUpper === 'CANCEL') {
            $requester = $meeting->requester;
            $invitees = $meeting->relationLoaded('invitees') ? $meeting->invitees : $meeting->invitees()->get();
            $inviteeEmails = $invitees->pluck('email')
                ->map(fn ($e) => strtolower(trim((string) $e)))
                ->all();

            // Add requester as attendee if distinct from owner and not already in invitees
            if ($requester && (! $owner || $requester->id !== $owner->id)) {
                $reqEmail = strtolower(trim((string) $requester->email));
                if (! in_array($reqEmail, $inviteeEmails, true)) {
                    $event->attendee($requester->email, $requester->name, ParticipationStatus::needs_action());
                }
            }

            // Add invitees as attendees (excluding owner if mistakenly present)
            foreach ($invitees as $invitee) {
                if ($ownerEmail !== '' && strtolower(trim((string) $invitee->email)) === $ownerEmail) {
                    continue;
                }
                $event->attendee($invitee->email, $invitee->name, ParticipationStatus::needs_action());
            }

            // If a specific recipient is targeted who is an invitee, ensure they are in attendee list
            if (! empty($recipientEmail)) {
                $cleanRecipient = strtolower(trim($recipientEmail));
                if ($cleanRecipient !== $ownerEmail && ! in_array($cleanRecipient, $inviteeEmails, true)) {
                    $event->attendee($recipientEmail, null, ParticipationStatus::needs_action());
                }
            }
        }

        $calendar = Calendar::create("{$orgName} Calendar")
            ->withoutAutoTimezoneComponents()
            ->event($event);

        $rawIcs = $calendar->get();

        // Inject METHOD header right after BEGIN:VCALENDAR
        $methodHeader = strtoupper($method);
        $rawIcs = preg_replace('/BEGIN:VCALENDAR\r?\n/', "BEGIN:VCALENDAR\r\nMETHOD:{$methodHeader}\r\n", $rawIcs, 1) ?? $rawIcs;

        // Inject SENT-BY parameter on ORGANIZER if email was sent from system address to satisfy Google Calendar / Outlook anti-spoofing
        $fromAddress = (string) Setting::get('mail.from_address', config('mail.from.address', ''));
        if (! empty($fromAddress) && $owner && strcasecmp($fromAddress, $owner->email) !== 0) {
            $rawIcs = preg_replace(
                '/ORGANIZER;CN=([^:\r\n]+):MAILTO:([^\r\n]+)/',
                'ORGANIZER;SENT-BY="mailto:'.$fromAddress.'";CN=$1:MAILTO:$2',
                $rawIcs,
                1
            ) ?? $rawIcs;
        }

        // Ensure strict RFC 5545 CRLF line breaks throughout
        $rawIcs = str_replace(["\r\n", "\r", "\n"], "\n", $rawIcs);
        $rawIcs = str_replace("\n", "\r\n", $rawIcs);

        return $rawIcs;
    }

    /**
     * Create an attachment array suitable for MailProviderInterface.
     *
     * @return array{name: string, data: string, mime: string}
     */
    public function getAttachment(Meeting $meeting, string $method = 'REQUEST', ?string $recipientEmail = null): array
    {
        $icsContent = $this->generate($meeting, $method, null, $recipientEmail);

        return [
            'name' => "meeting-{$meeting->public_id}.ics",
            'data' => $icsContent,
            'mime' => 'text/calendar; charset=UTF-8; method='.strtoupper($method),
        ];
    }
}
