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
     * @param  string  $method  'REQUEST' or 'CANCEL'
     * @param  int|null  $sequence  Calendar sequence number
     */
    public function generate(Meeting $meeting, string $method = 'REQUEST', ?int $sequence = null): string
    {
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

        $timezoneStr = $meeting->timezone ?: Setting::get('org.timezone', 'Asia/Kolkata');
        $tz = new DateTimeZone($timezoneStr);

        $startsAt = (clone $meeting->starts_at)->setTimezone($tz);
        $endsAt = (clone $meeting->ends_at)->setTimezone($tz);

        $event = Event::create($meeting->title)
            ->uniqueIdentifier($stableUid)
            ->startsAt($startsAt)
            ->endsAt($endsAt)
            ->description($description)
            ->address($joinUrl);

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
        if ($owner) {
            $event->organizer($owner->email, $owner->name);
        }

        // Add requester as attendee if distinct from owner and not already in invitees
        $requester = $meeting->requester;
        $inviteeEmails = $meeting->invitees->pluck('email')->map(fn ($e) => strtolower(trim((string) $e)))->all();

        if ($requester && (! $owner || $requester->id !== $owner->id)) {
            if (! in_array(strtolower(trim($requester->email)), $inviteeEmails, true)) {
                $event->attendee($requester->email, $requester->name, ParticipationStatus::accepted());
            }
        }

        // Add invitees as attendees
        foreach ($meeting->invitees as $invitee) {
            $event->attendee($invitee->email, $invitee->name, ParticipationStatus::needs_action());
        }

        $calendar = Calendar::create("{$orgName} Calendar")
            ->event($event);

        $rawIcs = $calendar->get();

        // Inject METHOD header right after BEGIN:VCALENDAR if needed
        if ($method === 'CANCEL') {
            $rawIcs = preg_replace('/BEGIN:VCALENDAR\r?\n/', "BEGIN:VCALENDAR\r\nMETHOD:CANCEL\r\n", $rawIcs, 1) ?? $rawIcs;
        } else {
            $rawIcs = preg_replace('/BEGIN:VCALENDAR\r?\n/', "BEGIN:VCALENDAR\r\nMETHOD:REQUEST\r\n", $rawIcs, 1) ?? $rawIcs;
        }

        return $rawIcs;
    }

    /**
     * Create an attachment array suitable for MailProviderInterface.
     *
     * @return array{name: string, data: string, mime: string}
     */
    public function getAttachment(Meeting $meeting, string $method = 'REQUEST'): array
    {
        $icsContent = $this->generate($meeting, $method);

        return [
            'name' => "meeting-{$meeting->public_id}.ics",
            'data' => $icsContent,
            'mime' => 'text/calendar; charset=UTF-8; method='.strtoupper($method),
        ];
    }
}
