<?php

namespace App\Domain\Integrations\GoogleWorkspace\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ResourcePool;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use ZipArchive;

class GoogleWorkspaceService
{
    public function __construct(
        protected AuditService $auditService,
        protected MeetingService $meetingService
    ) {}

    /**
     * Get default Gmail compose / email insertion template.
     */
    public function getDefaultEmailTemplate(): string
    {
        return <<<'TPL'
<div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 14px; line-height: 1.6; color: #1e293b; border-left: 4px solid #0284c7; padding: 12px 16px; background-color: #f8fafc; border-radius: 4px; margin: 12px 0;">
  <p style="margin: 0 0 8px 0; font-size: 15px; font-weight: 600; color: #0369a1;">
    📹 Zoom Meeting: {meeting_title}
  </p>
  <p style="margin: 0 0 8px 0;">
    <strong>Date & Time:</strong> {starts_at} - {ends_at} ({timezone})<br>
    <strong>Duration:</strong> {duration_minutes} minutes
  </p>
  <p style="margin: 0 0 10px 0;">
    <a href="{join_url}" target="_blank" style="display: inline-block; background-color: #0284c7; color: #ffffff; text-decoration: none; padding: 6px 14px; border-radius: 6px; font-weight: 500; font-size: 13px;">
      Join Zoom Meeting
    </a>
  </p>
  <p style="margin: 0; font-size: 12px; color: #64748b;">
    <strong>Meeting ID:</strong> {meeting_id} &nbsp;|&nbsp; <strong>Passcode:</strong> {passcode}
    {host_key_section}
  </p>
</div>
TPL;
    }

    /**
     * Get current safe Google Workspace configuration for SPA.
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        $apiToken = (string) Setting::get('google_workspace.api_token');
        if (empty($apiToken)) {
            $apiToken = 'zpm_gw_'.bin2hex(random_bytes(24));
            Setting::set('google_workspace.api_token', $apiToken);
        }

        $serverUrl = (string) Setting::get('google_workspace.server_url');
        if (empty($serverUrl)) {
            $serverUrl = rtrim((string) config('app.url', url('/')), '/');
        }

        $defaultPoolId = Setting::get('google_workspace.default_pool_id');
        $defaultTemplateId = Setting::get('google_workspace.default_template_id');

        return [
            'enabled' => (bool) Setting::get('google_workspace.enabled', true),
            'server_url' => $serverUrl,
            'api_token' => $apiToken,
            'allowed_domains' => (string) Setting::get('google_workspace.allowed_domains', ''),
            'default_pool_id' => $defaultPoolId ? (int) $defaultPoolId : null,
            'default_template_id' => $defaultTemplateId ? (int) $defaultTemplateId : null,
            'default_duration_minutes' => (int) Setting::get('google_workspace.default_duration_minutes', 60),
            'share_host_key' => (bool) Setting::get('google_workspace.share_host_key', true),
            'email_template' => (string) Setting::get('google_workspace.email_template', $this->getDefaultEmailTemplate()),
        ];
    }

    /**
     * Regenerate Google Workspace API authentication token.
     */
    public function regenerateToken(?User $actor = null): string
    {
        $newToken = 'zpm_gw_'.bin2hex(random_bytes(24));
        Setting::set('google_workspace.api_token', $newToken);

        $this->auditService->log(
            'google_workspace.token_regenerated',
            null,
            null,
            ['action' => 'api_token_regenerated'],
            $actor
        );

        return $newToken;
    }

    /**
     * Update Google Workspace configuration settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateConfiguration(array $data, ?User $actor = null): void
    {
        if (isset($data['enabled'])) {
            Setting::set('google_workspace.enabled', (bool) $data['enabled']);
        }

        if (isset($data['server_url'])) {
            Setting::set('google_workspace.server_url', rtrim((string) $data['server_url'], '/'));
        }

        if (! empty($data['api_token'])) {
            Setting::set('google_workspace.api_token', trim((string) $data['api_token']));
        }

        if (array_key_exists('allowed_domains', $data)) {
            Setting::set('google_workspace.allowed_domains', trim((string) $data['allowed_domains']));
        }

        if (array_key_exists('default_pool_id', $data)) {
            $poolId = ! empty($data['default_pool_id']) ? (int) $data['default_pool_id'] : null;
            Setting::set('google_workspace.default_pool_id', $poolId);
        }

        if (array_key_exists('default_template_id', $data)) {
            $templateId = ! empty($data['default_template_id']) ? (int) $data['default_template_id'] : null;
            Setting::set('google_workspace.default_template_id', $templateId);
        }

        if (isset($data['default_duration_minutes'])) {
            Setting::set('google_workspace.default_duration_minutes', max(15, (int) $data['default_duration_minutes']));
        }

        if (isset($data['share_host_key'])) {
            Setting::set('google_workspace.share_host_key', (bool) $data['share_host_key']);
        }

        if (isset($data['email_template'])) {
            Setting::set('google_workspace.email_template', (string) $data['email_template']);
        }

        $this->auditService->log(
            'settings.google_workspace.updated',
            null,
            null,
            [
                'server_url' => Setting::get('google_workspace.server_url'),
                'allowed_domains' => Setting::get('google_workspace.allowed_domains'),
            ],
            $actor
        );
    }

    /**
     * Test connection for Google Workspace integration.
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        $enabled = (bool) Setting::get('google_workspace.enabled', true);
        if (! $enabled) {
            return [
                'success' => false,
                'message' => 'Google Workspace Integration is currently disabled in settings.',
            ];
        }

        $apiToken = (string) Setting::get('google_workspace.api_token');
        if (empty($apiToken)) {
            return [
                'success' => false,
                'message' => 'Google Workspace API token is missing.',
            ];
        }

        $activePoolsCount = ResourcePool::where('is_active', true)->count();

        return [
            'success' => true,
            'message' => "Google Workspace integration endpoint is healthy and ready! Active pools available: {$activePoolsCount}.",
        ];
    }

    /**
     * Validate user domain against allowed_domains whitelist.
     */
    public function validateUserDomain(string $email): void
    {
        $email = strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('A valid email address is required.');
        }

        $allowedDomainsStr = (string) Setting::get('google_workspace.allowed_domains', '');
        if (! empty(trim($allowedDomainsStr))) {
            $allowedDomains = array_filter(array_map('trim', explode(',', strtolower($allowedDomainsStr))));
            $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
            if (! in_array($domain, $allowedDomains, true)) {
                throw new \InvalidArgumentException("Google account domain '{$domain}' is not permitted by organizational security policy. Allowed domains: {$allowedDomainsStr}");
            }
        }
    }

    /**
     * Resolve or create User from Google account identity.
     */
    public function resolveGoogleUser(string $email, ?string $name = null): User
    {
        $this->validateUserDomain($email);

        $cleanEmail = strtolower(trim($email));
        $cleanName = trim((string) $name);
        if (empty($cleanName)) {
            $cleanName = explode('@', $cleanEmail)[0];
        }

        /** @var User $user */
        $user = User::firstOrCreate(
            ['email' => $cleanEmail],
            [
                'name' => $cleanName,
                'password' => Hash::make(Str::random(32)),
                'timezone' => Setting::get('org.timezone', 'Asia/Kolkata'),
            ]
        );

        if (! $user->hasAnyRole(Role::all())) {
            $defaultRole = Role::where('name', 'User')->first() ?? Role::first();
            if ($defaultRole) {
                $user->assignRole($defaultRole);
            }
        }

        return $user;
    }

    /**
     * Render HTML & text snippets for email insertion.
     *
     * @return array{html: string, plain_text: string}
     */
    public function renderEmailSnippets(Meeting $meeting, bool $shareHostKey = true): array
    {
        $template = (string) Setting::get('google_workspace.email_template', $this->getDefaultEmailTemplate());
        $duration = max(15, (int) $meeting->starts_at->diffInMinutes($meeting->ends_at));

        $hostKeySection = '';
        $hostKeyPlain = '';
        if (! empty($meeting->host_key) && ($meeting->share_host_key || $shareHostKey)) {
            $hostKeySection = "<br><strong>Host Key PIN:</strong> {$meeting->host_key} (Claim Host in Zoom: Participants &gt; Claim Host)";
            $hostKeyPlain = "Host Key PIN: {$meeting->host_key} (Claim Host in Zoom: Participants > Claim Host)\n";
        }

        $appUrl = rtrim((string) config('app.url', url('/')), '/');

        $replacements = [
            '{meeting_title}' => htmlspecialchars($meeting->title, ENT_QUOTES, 'UTF-8'),
            '{starts_at}' => $meeting->starts_at->format('Y-m-d H:i'),
            '{ends_at}' => $meeting->ends_at->format('Y-m-d H:i'),
            '{start_time}' => $meeting->starts_at->format('h:i A'),
            '{end_time}' => $meeting->ends_at->format('h:i A'),
            '{timezone}' => $meeting->timezone ?: 'Asia/Kolkata',
            '{duration_minutes}' => (string) $duration,
            '{join_url}' => (string) ($meeting->join_url ?? "https://zoom.us/j/{$meeting->zoom_meeting_id}"),
            '{meeting_id}' => (string) ($meeting->zoom_meeting_id ?? 'N/A'),
            '{passcode}' => (string) ($meeting->passcode ?? 'Protected'),
            '{host_key}' => (string) ($meeting->host_key ?? ''),
            '{host_key_section}' => $hostKeySection,
            '{app_url}' => $appUrl,
        ];

        $html = str_replace(array_keys($replacements), array_values($replacements), $template);

        $plainText = "Zoom Meeting: {$meeting->title}\n"
            ."Time: {$meeting->starts_at->format('Y-m-d H:i')} - {$meeting->ends_at->format('Y-m-d H:i')} ({$meeting->timezone})\n"
            .'Join Link: '.($meeting->join_url ?? "https://zoom.us/j/{$meeting->zoom_meeting_id}")."\n"
            .'Meeting ID: '.($meeting->zoom_meeting_id ?? 'N/A')."\n"
            .'Passcode: '.($meeting->passcode ?? 'Protected')."\n"
            .$hostKeyPlain;

        return [
            'html' => trim($html),
            'plain_text' => trim($plainText),
        ];
    }

    /**
     * Book a meeting from Google Workspace (Calendar or Gmail).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function bookMeeting(array $data, ?User $actor = null): array
    {
        $userEmail = strtolower(trim((string) ($data['user_email'] ?? '')));
        if (empty($userEmail) && $actor) {
            $userEmail = $actor->email;
        }

        $userName = trim((string) ($data['user_name'] ?? ''));
        if (empty($userName) && $actor) {
            $userName = $actor->name;
        }

        $owner = $this->resolveGoogleUser($userEmail, $userName);

        // Timing
        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : Carbon::now()->addMinutes(15);
        $durationMinutes = isset($data['duration_minutes'])
            ? max(15, (int) $data['duration_minutes'])
            : (int) Setting::get('google_workspace.default_duration_minutes', 60);

        $endsAt = isset($data['ends_at'])
            ? Carbon::parse($data['ends_at'])
            : $startsAt->copy()->addMinutes($durationMinutes);

        $channel = trim((string) ($data['channel'] ?? 'google_workspace')); // calendar or gmail
        $title = ! empty($data['title']) ? trim((string) $data['title']) : "Zoom Meeting with {$owner->name}";
        $poolId = ! empty($data['pool_id']) ? (int) $data['pool_id'] : (int) Setting::get('google_workspace.default_pool_id');
        $templateId = ! empty($data['template_id']) ? (int) $data['template_id'] : (int) Setting::get('google_workspace.default_template_id');

        $shareHostKey = array_key_exists('share_host_key', $data)
            ? (bool) $data['share_host_key']
            : (bool) Setting::get('google_workspace.share_host_key', true);

        $calendarEventId = (string) ($data['calendar_event_id'] ?? '');

        $customFields = [
            'booking_channel' => $channel,
            'google_user_email' => $owner->email,
            'google_user_name' => $owner->name,
            'google_calendar_event_id' => $calendarEventId,
        ];

        $meetingData = [
            'title' => $title,
            'description' => "Scheduled via Google Workspace ({$channel}) by {$owner->name} ({$owner->email})",
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'participant_count' => isset($data['participant_count']) ? (int) $data['participant_count'] : 10,
            'owner_user_id' => $owner->id,
            'preferred_pool_id' => $poolId ?: null,
            'template_id' => $templateId ?: null,
            'share_host_key' => $shareHostKey,
            'custom_fields' => $customFields,
            'source' => 'google_workspace',
        ];

        $invitees = [];
        if (! empty($data['invitees']) && is_array($data['invitees'])) {
            foreach ($data['invitees'] as $inv) {
                if (is_string($inv) && filter_var(trim($inv), FILTER_VALIDATE_EMAIL)) {
                    $invitees[] = strtolower(trim($inv));
                }
            }
        }
        if (! in_array($owner->email, $invitees, true)) {
            $invitees[] = $owner->email;
        }

        // Schedule meeting
        $bookingActor = $actor ?? $owner;
        $meeting = $this->meetingService->createMeeting($bookingActor, $meetingData, $invitees);
        $meeting->refresh();

        $snippets = $this->renderEmailSnippets($meeting, $shareHostKey);

        $this->auditService->log(
            'google_workspace.meeting_booked',
            $meeting,
            null,
            [
                'channel' => $channel,
                'calendar_event_id' => $calendarEventId,
                'zoom_meeting_id' => $meeting->zoom_meeting_id,
                'owner_email' => $owner->email,
            ],
            $bookingActor
        );

        return [
            'success' => true,
            'meeting' => [
                'id' => $meeting->id,
                'public_id' => $meeting->public_id,
                'title' => $meeting->title,
                'zoom_meeting_id' => (string) ($meeting->zoom_meeting_id ?? ''),
                'join_url' => (string) ($meeting->join_url ?? ''),
                'passcode' => (string) ($meeting->passcode ?? ''),
                'host_key' => $shareHostKey ? (string) ($meeting->host_key ?? '') : '',
                'starts_at' => $meeting->starts_at->toIso8601String(),
                'ends_at' => $meeting->ends_at->toIso8601String(),
                'duration_minutes' => $durationMinutes,
                'timezone' => $meeting->timezone,
            ],
            'conference_data' => [
                'conference_id' => (string) ($meeting->zoom_meeting_id ?? $meeting->public_id),
                'entry_points' => [
                    [
                        'entry_point_type' => 'VIDEO',
                        'uri' => (string) ($meeting->join_url ?? ''),
                        'label' => 'Join Zoom Meeting',
                        'meeting_code' => (string) ($meeting->zoom_meeting_id ?? ''),
                        'password' => (string) ($meeting->passcode ?? ''),
                        'pin' => $shareHostKey ? (string) ($meeting->host_key ?? '') : null,
                    ],
                ],
                'notes' => "Zoom Meeting ID: {$meeting->zoom_meeting_id}\nPasscode: {$meeting->passcode}".($shareHostKey && $meeting->host_key ? "\nHost Key: {$meeting->host_key}" : ''),
            ],
            'email_snippets' => $snippets,
        ];
    }

    /**
     * Generate the complete Google Apps Script Add-on ZIP package for download.
     */
    public function generateAddonZip(): string
    {
        $serverUrl = (string) Setting::get('google_workspace.server_url');
        if (empty($serverUrl)) {
            $serverUrl = rtrim((string) config('app.url', url('/')), '/');
        }
        $apiToken = (string) Setting::get('google_workspace.api_token');

        $zipPath = tempnam(sys_get_temp_dir(), 'zpm_gw_addon_').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Failed to create Google Workspace Add-on ZIP file.');
        }

        $manifestJson = $this->generateAppsscriptJson();
        $codeGs = $this->generateCodeGs($serverUrl, $apiToken);
        $readmeMd = $this->generateReadmeMd($serverUrl);

        $zip->addFromString('appsscript.json', $manifestJson);
        $zip->addFromString('Code.gs', $codeGs);
        $zip->addFromString('README.md', $readmeMd);
        $zip->close();

        return $zipPath;
    }

    /**
     * Generate appsscript.json manifest file for Google Workspace Add-on.
     */
    public function generateAppsscriptJson(): string
    {
        $manifest = [
            'timeZone' => 'Asia/Kolkata',
            'dependencies' => [
                'enabledAdvancedServices' => [],
            ],
            'exceptionLogging' => 'STACKDRIVER',
            'runtimeVersion' => 'V8',
            'oauthScopes' => [
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
                'https://www.googleapis.com/auth/script.external_request',
                'https://www.googleapis.com/auth/calendar.addons.current.event.read',
                'https://www.googleapis.com/auth/calendar.addons.current.event.write',
                'https://www.googleapis.com/auth/calendar.addons.execute',
                'https://www.googleapis.com/auth/gmail.addons.current.action.compose',
                'https://www.googleapis.com/auth/gmail.addons.execute',
            ],
            'addOns' => [
                'common' => [
                    'name' => 'Zoom (Zoom Pool Manager)',
                    'logoUrl' => 'https://raw.githubusercontent.com/senthilnasa/zoom-pool-manager/main/public/images/zpm-logo-icon.png',
                    'layoutProperties' => [
                        'primaryColor' => '#0284c7',
                        'secondaryColor' => '#0369a1',
                    ],
                    'homepageTrigger' => [
                        'runFunction' => 'onHomepage',
                        'enabled' => true,
                    ],
                ],
                'calendar' => [
                    'conferenceSolution' => [
                        [
                            'id' => 'zpm_zoom_conference',
                            'name' => 'Zoom Meeting (Zoom Pool Manager)',
                            'logoUrl' => 'https://raw.githubusercontent.com/senthilnasa/zoom-pool-manager/main/public/images/zpm-logo-icon.png',
                            'onCreateFunction' => 'createConference',
                        ],
                    ],
                    'currentEventAccess' => 'READ_WRITE',
                ],
                'gmail' => [
                    'composeTrigger' => [
                        'selectActions' => [
                            [
                                'text' => 'Add Zoom Meeting Link',
                                'runFunction' => 'onGmailCompose',
                            ],
                        ],
                        'draftAccess' => 'METADATA',
                    ],
                ],
            ],
        ];

        return json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Generate Code.gs for Google Apps Script.
     */
    public function generateCodeGs(string $serverUrl, string $apiToken): string
    {
        return <<<GS
/**
 * Zoom Pool Manager - Google Workspace Add-on (Calendar & Gmail)
 * Automatically schedule pooled Zoom meetings from Google Calendar & Gmail Compose.
 */

// Configuration defaults (can also be overridden in Script Properties or user settings)
var DEFAULT_CONFIG = {
  serverUrl: '{$serverUrl}',
  apiToken: '{$apiToken}',
  conferenceSolutionId: 'zpm_zoom_conference'
};

function getConfig() {
  var props = PropertiesService.getScriptProperties();
  var userProps = PropertiesService.getUserProperties();
  return {
    serverUrl: userProps.getProperty('zpm_server_url') || props.getProperty('zpm_server_url') || DEFAULT_CONFIG.serverUrl,
    apiToken: userProps.getProperty('zpm_api_token') || props.getProperty('zpm_api_token') || DEFAULT_CONFIG.apiToken,
    conferenceSolutionId: DEFAULT_CONFIG.conferenceSolutionId
  };
}

/**
 * Common Homepage Card (Sidebar).
 */
function onHomepage(e) {
  var config = getConfig();
  var card = CardService.newCardBuilder();
  card.setHeader(CardService.newCardHeader()
    .setTitle("Zoom Pool Manager")
    .setSubtitle("Smart Pooled Zoom Licensing")
    .setImageStyle(CardService.ImageStyle.SQUARE)
    .setImageUrl("https://raw.githubusercontent.com/senthilnasa/zoom-pool-manager/main/public/images/zpm-logo-icon.png"));

  var section = CardService.newCardSection();
  section.setHeader("Google Workspace Integration");
  section.addWidget(CardService.newTextParagraph()
    .setText("<b>Google Calendar:</b> When creating an event, click <i>'Add conferencing'</i> and select <b>Zoom Meeting (Zoom Pool Manager)</b>.<br><br><b>Gmail:</b> When composing an email, click the Zoom icon in the toolbar to insert meeting credentials with 1-click."));

  var serverWidget = CardService.newKeyValue()
    .setTopLabel("Connected ZPM Server")
    .setContent(config.serverUrl)
    .setMultiline(true);
  section.addWidget(serverWidget);

  card.addSection(section);
  return card.build();
}

/**
 * -------------------------------------------------------------
 * 1. Google Calendar Conferencing Integration
 * -------------------------------------------------------------
 * Triggered automatically when user selects "Zoom Meeting (Zoom Pool Manager)" in Calendar.
 */
function createConference(e) {
  var config = getConfig();
  var userEmail = Session.getActiveUser().getEmail() || (e.calendar && e.calendar.calendarId) || '';
  var eventData = (e.calendar && e.calendar.event) ? e.calendar.event : {};

  var title = (eventData.summary && eventData.summary.trim()) ? eventData.summary : "Google Calendar Zoom Meeting";
  var startTime = (eventData.startTime && eventData.startTime.dateTime) ? eventData.startTime.dateTime : new Date().toISOString();
  var endTime = (eventData.endTime && eventData.endTime.dateTime) ? eventData.endTime.dateTime : null;
  var calendarEventId = (e.calendar && e.calendar.id) ? e.calendar.id : '';

  // Extract attendee emails
  var invitees = [];
  if (eventData.attendees && Array.isArray(eventData.attendees)) {
    for (var i = 0; i < eventData.attendees.length; i++) {
      if (eventData.attendees[i].email) {
        invitees.push(eventData.attendees[i].email);
      }
    }
  }

  var payload = {
    channel: 'google_calendar',
    calendar_event_id: calendarEventId,
    user_email: userEmail,
    title: title,
    starts_at: startTime,
    ends_at: endTime,
    invitees: invitees
  };

  var endpoint = config.serverUrl + '/api/v1/integrations/google-workspace/book-conference';
  var options = {
    method: 'post',
    contentType: 'application/json',
    headers: {
      'Authorization': 'Bearer ' + config.apiToken,
      'Accept': 'application/json'
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  };

  try {
    var response = UrlFetchApp.fetch(endpoint, options);
    var statusCode = response.getResponseCode();
    var resJson = JSON.parse(response.getContentText());

    if (statusCode >= 200 && statusCode < 300 && resJson.success) {
      var conf = resJson.conference_data;
      var entry = conf.entry_points && conf.entry_points[0] ? conf.entry_points[0] : null;

      if (!entry || !entry.uri) {
        throw new Error("Invalid conference entry point returned by Zoom Pool Manager.");
      }

      var videoEntryPoint = ConferenceDataService.newEntryPoint()
        .setEntryPointType(ConferenceDataService.EntryPointType.VIDEO)
        .setUri(entry.uri);

      if (entry.meeting_code) {
        videoEntryPoint.setMeetingCode(entry.meeting_code);
      }
      if (entry.password) {
        videoEntryPoint.setPassword(entry.password);
      }
      if (entry.pin) {
        videoEntryPoint.setPin(entry.pin);
      }

      var confBuilder = ConferenceDataService.newConferenceDataBuilder()
        .setConferenceId(conf.conference_id || resJson.meeting.zoom_meeting_id)
        .setConferenceSolutionId(config.conferenceSolutionId)
        .addEntryPoint(videoEntryPoint);

      if (conf.notes) {
        confBuilder.setNotes(conf.notes);
      }

      return CardService.newCalendarEventActionResponseBuilder()
        .setConferenceData(confBuilder.build())
        .build();
    } else {
      var errMsg = (resJson && resJson.message) ? resJson.message : "HTTP " + statusCode;
      var confError = ConferenceDataService.newConferenceError()
        .setConferenceErrorType(ConferenceDataService.ConferenceErrorType.PERMANENT)
        .setAuthenticationUrl(config.serverUrl);

      return CardService.newCalendarEventActionResponseBuilder()
        .setConferenceData(ConferenceDataService.newConferenceDataBuilder().setError(confError).build())
        .build();
    }
  } catch (err) {
    Logger.log("Zoom Pool Manager Conference Error: " + err.toString());
    var confError = ConferenceDataService.newConferenceError()
      .setConferenceErrorType(ConferenceDataService.ConferenceErrorType.TEMPORARY);

    return CardService.newCalendarEventActionResponseBuilder()
      .setConferenceData(ConferenceDataService.newConferenceDataBuilder().setError(confError).build())
      .build();
  }
}

/**
 * -------------------------------------------------------------
 * 2. Gmail Compose Integration
 * -------------------------------------------------------------
 * Triggered when clicking the Add-on action in Gmail compose toolbar.
 */
function onGmailCompose(e) {
  var card = CardService.newCardBuilder();
  card.setHeader(CardService.newCardHeader()
    .setTitle("Insert Zoom Meeting Link")
    .setImageUrl("https://raw.githubusercontent.com/senthilnasa/zoom-pool-manager/main/public/images/zpm-logo-icon.png")
    .setImageStyle(CardService.ImageStyle.SQUARE));

  var section = CardService.newCardSection();
  section.addWidget(CardService.newTextParagraph()
    .setText("Quickly book an available Zoom host from your organization's resource pool and insert meeting credentials into this draft."));

  var topicInput = CardService.newTextInput()
    .setFieldName("meeting_topic")
    .setTitle("Meeting Topic (Optional)")
    .setHint("e.g., Quick Discussion");
  section.addWidget(topicInput);

  var durationSelection = CardService.newSelectionInput()
    .setFieldName("meeting_duration")
    .setTitle("Duration")
    .setType(CardService.SelectionInputType.DROPDOWN)
    .addItem("30 Minutes", "30", false)
    .addItem("45 Minutes", "45", false)
    .addItem("1 Hour (Standard)", "60", true)
    .addItem("90 Minutes", "90", false)
    .addItem("2 Hours", "120", false);
  section.addWidget(durationSelection);

  var insertAction = CardService.newAction()
    .setFunctionName("insertMeetingLinkToDraft");

  var insertButton = CardService.newTextButton()
    .setText("📹 Generate & Insert Link")
    .setTextButtonStyle(CardService.TextButtonStyle.FILLED)
    .setOnClickAction(insertAction);
  section.addWidget(insertButton);

  card.addSection(section);
  return card.build();
}

/**
 * Callback action to create meeting and insert into Gmail compose draft.
 */
function insertMeetingLinkToDraft(e) {
  var config = getConfig();
  var userEmail = Session.getActiveUser().getEmail() || '';
  var formInputs = e.formInput || {};
  var topic = formInputs.meeting_topic || "Zoom Meeting";
  var duration = parseInt(formInputs.meeting_duration || "60", 10);

  var payload = {
    channel: 'gmail_compose',
    user_email: userEmail,
    title: topic,
    duration_minutes: duration
  };

  var endpoint = config.serverUrl + '/api/v1/integrations/google-workspace/book-link';
  var options = {
    method: 'post',
    contentType: 'application/json',
    headers: {
      'Authorization': 'Bearer ' + config.apiToken,
      'Accept': 'application/json'
    },
    payload: JSON.stringify(payload),
    muteHttpExceptions: true
  };

  try {
    var response = UrlFetchApp.fetch(endpoint, options);
    var statusCode = response.getResponseCode();
    var resJson = JSON.parse(response.getContentText());

    if (statusCode >= 200 && statusCode < 300 && resJson.success) {
      var htmlSnippet = resJson.email_snippets.html;

      var updateBodyAction = CardService.newUpdateDraftBodyAction()
        .addUpdateContent(htmlSnippet, CardService.ContentType.MUTABLE_HTML)
        .setUpdateType(CardService.UpdateDraftBodyType.IN_PLACE_INSERT);

      return CardService.newUpdateDraftActionResponseBuilder()
        .setUpdateDraftBodyAction(updateBodyAction)
        .build();
    } else {
      var errMsg = (resJson && resJson.message) ? resJson.message : "Error " + statusCode;
      return CardService.newActionResponseBuilder()
        .setNotification(CardService.newNotification().setText("Failed to schedule meeting: " + errMsg))
        .build();
    }
  } catch (err) {
    return CardService.newActionResponseBuilder()
      .setNotification(CardService.newNotification().setText("Network error: " + err.toString()))
      .build();
  }
}
GS;
    }

    /**
     * Generate README.md with deployment steps.
     */
    public function generateReadmeMd(string $serverUrl): string
    {
        return <<<MD
# Zoom Pool Manager — Google Workspace Add-on

Integrate Zoom Pool Manager directly with **Google Calendar** and **Gmail**.

## Capabilities
1. **Google Calendar:** Native conferencing button ("Add conferencing" > "Zoom Meeting (Zoom Pool Manager)") dynamically provisions a Zoom host, attaches meeting join links, passcode, and host key.
2. **Gmail Compose:** 1-Click button in the compose window to book a Zoom meeting from your pooled licenses and insert rich formatted invite text directly into your draft.

---

## Deployment Guide (Google Apps Script)

### Step 1: Create Apps Script Project
1. Go to [https://script.google.com](https://script.google.com) and click **New project**.
2. Rename the project to **Zoom Pool Manager Add-on**.

### Step 2: Show Manifest File
1. In the Apps Script editor, click the **Project Settings (Gear)** icon on the left navigation bar.
2. Check the box: **"Show 'appsscript.json' manifest file in editor"**.
3. Return to the **Editor (< >)** tab.

### Step 3: Copy Code and Manifest
1. Replace the entire content of `appsscript.json` with the contents of `appsscript.json` from this package.
2. Replace the entire content of `Code.gs` with the contents of `Code.gs` from this package.
3. Click the **Save** disk icon (or press Ctrl+S / Cmd+S).

### Step 4: Test & Install (Developer Mode)
1. Click **Deploy** (top-right) > **Test deployments**.
2. Under Application, click **Install** next to the Add-on.
3. Open **Google Calendar** ([calendar.google.com](https://calendar.google.com)). Create an event and verify **Zoom Meeting (Zoom Pool Manager)** appears under "Add video conferencing".
4. Open **Gmail** ([mail.google.com](https://mail.google.com)). Click **Compose** and look for the Zoom icon in the bottom compose toolbar.

---

## Domain-Wide Deployment (Google Workspace Admins)
To roll this out to all staff or faculty across your entire Google Workspace domain:
1. In Google Cloud Console, link the Apps Script project to your organizational Google Cloud project.
2. Enable the **Google Workspace Marketplace SDK**.
3. Configure the listing as **Private / Domain-installed** for your Google Workspace domain.
4. Go to **Google Admin Console** ([admin.google.com](https://admin.google.com)) > **Apps** > **Google Workspace Marketplace apps** > **Apps list** > **Install app**.
5. All domain users will automatically have the Zoom button in Google Calendar and Gmail without individual installation!

Server URL: {$serverUrl}
MD;
    }
}
