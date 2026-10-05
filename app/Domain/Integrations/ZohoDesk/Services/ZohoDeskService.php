<?php

namespace App\Domain\Integrations\ZohoDesk\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Meetings\Models\Meeting;
use App\Domain\Meetings\Services\MeetingService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use ZipArchive;

class ZohoDeskService
{
    /**
     * Supported Zoho Desk Data Centers and base domains.
     *
     * @var array<string, array{desk: string, accounts: string, label: string}>
     */
    public const DATA_CENTERS = [
        'in' => [
            'desk' => 'https://desk.zoho.in',
            'accounts' => 'https://accounts.zoho.in',
            'label' => 'India (.in)',
        ],
        'com' => [
            'desk' => 'https://desk.zoho.com',
            'accounts' => 'https://accounts.zoho.com',
            'label' => 'United States (.com)',
        ],
        'eu' => [
            'desk' => 'https://desk.zoho.eu',
            'accounts' => 'https://accounts.zoho.eu',
            'label' => 'Europe (.eu)',
        ],
        'com.au' => [
            'desk' => 'https://desk.zoho.com.au',
            'accounts' => 'https://accounts.zoho.com.au',
            'label' => 'Australia (.com.au)',
        ],
        'jp' => [
            'desk' => 'https://desk.zoho.jp',
            'accounts' => 'https://accounts.zoho.jp',
            'label' => 'Japan (.jp)',
        ],
        'ca' => [
            'desk' => 'https://desk.zoho.ca',
            'accounts' => 'https://accounts.zoho.ca',
            'label' => 'Canada (.ca)',
        ],
        'com.cn' => [
            'desk' => 'https://desk.zoho.com.cn',
            'accounts' => 'https://accounts.zoho.com.cn',
            'label' => 'China (.com.cn)',
        ],
    ];

    public function __construct(
        protected AuditService $auditService,
        protected MeetingService $meetingService
    ) {}

    /**
     * Get the default comment template for ticket replies.
     */
    public function getDefaultCommentTemplate(): string
    {
        return <<<'TPL'
Hello {requester_name},

Your Zoom meeting has been scheduled via Zoom Pool Manager.

📅 **Topic:** {meeting_title}
🕒 **Date & Time:** {starts_at} - {ends_at} ({timezone})
⏱️ **Duration:** {duration_minutes} minutes

🔗 **Join Zoom Meeting:**
{join_url}

🆔 **Meeting ID:** {meeting_id}
🔑 **Passcode:** {passcode}
{host_key_section}

Please let us know if you need any additional assistance.
TPL;
    }

    /**
     * Get current safe Zoho Desk configuration for SPA.
     *
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        $apiToken = (string) Setting::get('zoho_desk.api_token');
        if (empty($apiToken)) {
            $apiToken = 'zpm_zd_'.bin2hex(random_bytes(24));
            Setting::set('zoho_desk.api_token', $apiToken);
        }

        $serverUrl = (string) Setting::get('zoho_desk.server_url');
        if (empty($serverUrl)) {
            $serverUrl = rtrim((string) config('app.url', url('/')), '/');
        }

        $clientSecret = (string) Setting::get('zoho_desk.client_secret');
        $refreshToken = (string) Setting::get('zoho_desk.refresh_token');
        $agentToken = (string) Setting::get('zoho_desk.agent_token');

        $defaultPoolId = Setting::get('zoho_desk.default_pool_id');
        $defaultTemplateId = Setting::get('zoho_desk.default_template_id');

        return [
            'enabled' => (bool) Setting::get('zoho_desk.enabled', true),
            'server_url' => $serverUrl,
            'api_token' => $apiToken,
            'dc' => (string) Setting::get('zoho_desk.dc', 'in'),
            'org_id' => (string) Setting::get('zoho_desk.org_id', ''),
            'client_id' => (string) Setting::get('zoho_desk.client_id', ''),
            'has_client_secret' => ! empty($clientSecret),
            'has_refresh_token' => ! empty($refreshToken),
            'has_agent_token' => ! empty($agentToken),
            'default_pool_id' => $defaultPoolId ? (int) $defaultPoolId : null,
            'default_template_id' => $defaultTemplateId ? (int) $defaultTemplateId : null,
            'default_duration_minutes' => (int) Setting::get('zoho_desk.default_duration_minutes', 60),
            'default_is_public' => (bool) Setting::get('zoho_desk.default_is_public', true),
            'auto_close_ticket' => (bool) Setting::get('zoho_desk.auto_close_ticket', true),
            'ticket_close_status' => (string) Setting::get('zoho_desk.ticket_close_status', 'Closed'),
            'comment_template' => (string) Setting::get('zoho_desk.comment_template', $this->getDefaultCommentTemplate()),
            'data_centers' => self::DATA_CENTERS,
        ];
    }

    /**
     * Update Zoho Desk configuration settings.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateConfiguration(array $data, ?User $actor = null): void
    {
        if (isset($data['enabled'])) {
            Setting::set('zoho_desk.enabled', (bool) $data['enabled']);
        }

        if (isset($data['server_url'])) {
            Setting::set('zoho_desk.server_url', rtrim((string) $data['server_url'], '/'));
        }

        if (! empty($data['api_token'])) {
            Setting::set('zoho_desk.api_token', trim((string) $data['api_token']));
        }

        if (isset($data['dc'])) {
            $dc = strtolower(trim((string) $data['dc']));
            if (array_key_exists($dc, self::DATA_CENTERS)) {
                Setting::set('zoho_desk.dc', $dc);
            }
        }

        if (isset($data['org_id'])) {
            Setting::set('zoho_desk.org_id', trim((string) $data['org_id']));
        }

        if (isset($data['client_id'])) {
            Setting::set('zoho_desk.client_id', trim((string) $data['client_id']));
        }

        if (! empty($data['client_secret']) && ! str_contains((string) $data['client_secret'], '***')) {
            Setting::set('zoho_desk.client_secret', trim((string) $data['client_secret']), true);
        }

        if (! empty($data['refresh_token']) && ! str_contains((string) $data['refresh_token'], '***')) {
            Setting::set('zoho_desk.refresh_token', trim((string) $data['refresh_token']), true);
        }

        if (isset($data['agent_token'])) {
            $agentToken = trim((string) $data['agent_token']);
            if (! str_contains($agentToken, '***')) {
                Setting::set('zoho_desk.agent_token', $agentToken, true);
            }
        }

        if (array_key_exists('default_pool_id', $data)) {
            $poolId = ! empty($data['default_pool_id']) ? (int) $data['default_pool_id'] : null;
            Setting::set('zoho_desk.default_pool_id', $poolId);
        }

        if (array_key_exists('default_template_id', $data)) {
            $templateId = ! empty($data['default_template_id']) ? (int) $data['default_template_id'] : null;
            Setting::set('zoho_desk.default_template_id', $templateId);
        }

        if (isset($data['default_duration_minutes'])) {
            Setting::set('zoho_desk.default_duration_minutes', max(15, (int) $data['default_duration_minutes']));
        }

        if (isset($data['default_is_public'])) {
            Setting::set('zoho_desk.default_is_public', (bool) $data['default_is_public']);
        }

        if (isset($data['auto_close_ticket'])) {
            Setting::set('zoho_desk.auto_close_ticket', (bool) $data['auto_close_ticket']);
        }

        if (isset($data['ticket_close_status'])) {
            Setting::set('zoho_desk.ticket_close_status', trim((string) $data['ticket_close_status']));
        }

        if (isset($data['comment_template'])) {
            Setting::set('zoho_desk.comment_template', (string) $data['comment_template']);
        }

        $this->auditService->log(
            'settings.zoho_desk.updated',
            null,
            null,
            ['dc' => Setting::get('zoho_desk.dc'), 'org_id' => Setting::get('zoho_desk.org_id')],
            $actor
        );
    }

    /**
     * Get or refresh active Zoho Desk access token.
     *
     * @param  array<string, mixed>|null  $overrideCredentials
     */
    public function getAccessToken(?array $overrideCredentials = null): ?string
    {
        // 1. Direct Agent token priority if set
        $agentToken = $overrideCredentials['agent_token'] ?? Setting::get('zoho_desk.agent_token');
        if (! empty($agentToken) && ! str_contains((string) $agentToken, '***')) {
            return (string) $agentToken;
        }

        $clientId = $overrideCredentials['client_id'] ?? Setting::get('zoho_desk.client_id');
        $clientSecret = $overrideCredentials['client_secret'] ?? Setting::get('zoho_desk.client_secret');
        $refreshToken = $overrideCredentials['refresh_token'] ?? Setting::get('zoho_desk.refresh_token');
        $dc = $overrideCredentials['dc'] ?? Setting::get('zoho_desk.dc', 'in');

        if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
            return null;
        }

        // 2. Check cached token if not overriding credentials
        if ($overrideCredentials === null) {
            $cachedToken = (string) Setting::get('zoho_desk._access_token');
            $expiresAt = Setting::get('zoho_desk._access_token_expires_at');

            if (! empty($cachedToken) && $expiresAt && Carbon::parse($expiresAt)->isFuture()) {
                return $cachedToken;
            }
        }

        // 3. Refresh OAuth token from Zoho Accounts API
        $accountsBase = self::DATA_CENTERS[$dc]['accounts'] ?? 'https://accounts.zoho.in';

        try {
            $response = Http::asForm()->timeout(10)->post("{$accountsBase}/oauth/v2/token", [
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'refresh_token',
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $token = (string) ($data['access_token'] ?? '');

                if (! empty($token)) {
                    $expiresIn = (int) ($data['expires_in'] ?? 3600);
                    if ($overrideCredentials === null) {
                        Setting::set('zoho_desk._access_token', $token, true);
                        Setting::set('zoho_desk._access_token_expires_at', now()->addSeconds(max(60, $expiresIn - 120))->toIso8601String());
                    }

                    return $token;
                }
            }

            Log::warning('Zoho Desk OAuth refresh failed: '.$response->body());
        } catch (\Throwable $e) {
            Log::error("Zoho Desk token request exception: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Test connection to Zoho Desk API.
     *
     * @param  array<string, mixed>|null  $credentials
     * @return array{success: bool, message: string, org_name?: string, org_id?: string}
     */
    public function testConnection(?array $credentials = null): array
    {
        $dc = $credentials['dc'] ?? Setting::get('zoho_desk.dc', 'in');
        $orgId = $credentials['org_id'] ?? Setting::get('zoho_desk.org_id');
        $deskBase = self::DATA_CENTERS[$dc]['desk'] ?? 'https://desk.zoho.in';

        $token = $this->getAccessToken($credentials);
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'Failed to obtain Zoho Desk access token. Please verify Client ID, Client Secret, and Refresh Token (or Agent Token).',
            ];
        }

        $headers = [
            'Authorization' => "Zoho-oauthtoken {$token}",
        ];
        if (! empty($orgId)) {
            $headers['orgId'] = (string) $orgId;
        }

        try {
            // Attempt to fetch organizations or current user info
            $res = Http::withHeaders($headers)->timeout(10)->get("{$deskBase}/api/v1/organizations");

            if ($res->successful()) {
                $orgs = $res->json('data') ?? [];
                $firstOrg = is_array($orgs) && count($orgs) > 0 ? $orgs[0] : null;
                $orgName = $firstOrg['companyName'] ?? $firstOrg['orgName'] ?? 'Connected Zoho Organization';
                $resolvedOrgId = $firstOrg['orgId'] ?? $firstOrg['id'] ?? $orgId;

                // Save orgId if it was not set
                if (empty($orgId) && ! empty($resolvedOrgId)) {
                    Setting::set('zoho_desk.org_id', (string) $resolvedOrgId);
                }

                return [
                    'success' => true,
                    'message' => "Successfully connected to Zoho Desk! Organization: {$orgName} (DC: {$dc})",
                    'org_name' => (string) $orgName,
                    'org_id' => (string) $resolvedOrgId,
                ];
            }

            // Fallback check against myinfo or tickets endpoint
            $fallbackRes = Http::withHeaders($headers)->timeout(10)->get("{$deskBase}/api/v1/myinfo");
            if ($fallbackRes->successful()) {
                $name = $fallbackRes->json('name') ?? $fallbackRes->json('emailId') ?? 'Authorized Zoho Agent';

                return [
                    'success' => true,
                    'message' => "Successfully connected to Zoho Desk! Authenticated as: {$name}",
                    'org_name' => (string) $name,
                ];
            }

            $errorMsg = $res->json('message') ?? $fallbackRes->json('message') ?? 'API request returned status '.$res->status();

            return [
                'success' => false,
                'message' => "Zoho Desk connection failed: {$errorMsg}",
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => "Connection test exception: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Post a comment or public reply to a Zoho Desk ticket.
     *
     * @return array{success: bool, comment_id?: string, message: string}
     */
    public function postTicketComment(string $ticketId, string $content, bool $isPublic = true): array
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'No active Zoho Desk token available.',
            ];
        }

        $dc = (string) Setting::get('zoho_desk.dc', 'in');
        $orgId = (string) Setting::get('zoho_desk.org_id');
        $deskBase = self::DATA_CENTERS[$dc]['desk'] ?? 'https://desk.zoho.in';

        $headers = [
            'Authorization' => "Zoho-oauthtoken {$token}",
            'Content-Type' => 'application/json',
        ];
        if (! empty($orgId)) {
            $headers['orgId'] = $orgId;
        }

        try {
            $res = Http::withHeaders($headers)->timeout(10)->post("{$deskBase}/api/v1/tickets/{$ticketId}/comments", [
                'content' => $content,
                'isPublic' => $isPublic,
            ]);

            if ($res->successful()) {
                $data = $res->json();

                return [
                    'success' => true,
                    'comment_id' => (string) ($data['id'] ?? ''),
                    'message' => 'Comment successfully posted to Zoho Desk ticket.',
                ];
            }

            Log::warning("Failed to post comment to Zoho Desk ticket {$ticketId}: ".$res->body());

            return [
                'success' => false,
                'message' => $res->json('message') ?? 'HTTP status '.$res->status(),
            ];
        } catch (\Throwable $e) {
            Log::error("Zoho Desk comment exception: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update ticket status in Zoho Desk (e.g., Close or Resolve).
     *
     * @return array{success: bool, message: string}
     */
    public function updateTicketStatus(string $ticketId, string $status = 'Closed'): array
    {
        $token = $this->getAccessToken();
        if (empty($token)) {
            return [
                'success' => false,
                'message' => 'No active Zoho Desk token available.',
            ];
        }

        $dc = (string) Setting::get('zoho_desk.dc', 'in');
        $orgId = (string) Setting::get('zoho_desk.org_id');
        $deskBase = self::DATA_CENTERS[$dc]['desk'] ?? 'https://desk.zoho.in';

        $headers = [
            'Authorization' => "Zoho-oauthtoken {$token}",
            'Content-Type' => 'application/json',
        ];
        if (! empty($orgId)) {
            $headers['orgId'] = $orgId;
        }

        try {
            $res = Http::withHeaders($headers)->timeout(10)->patch("{$deskBase}/api/v1/tickets/{$ticketId}", [
                'status' => $status,
            ]);

            if ($res->successful()) {
                return [
                    'success' => true,
                    'message' => "Ticket status successfully updated to '{$status}'.",
                ];
            }

            Log::warning("Failed to update status for Zoho Desk ticket {$ticketId}: ".$res->body());

            return [
                'success' => false,
                'message' => $res->json('message') ?? 'HTTP status '.$res->status(),
            ];
        } catch (\Throwable $e) {
            Log::error("Zoho Desk update ticket status exception: {$e->getMessage()}");

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Render the comment message using meeting data and ticket context.
     *
     * @param  array<string, mixed>  $ticketData
     */
    public function renderComment(Meeting $meeting, array $ticketData, ?string $customTemplate = null): string
    {
        $template = $customTemplate ?: (string) Setting::get('zoho_desk.comment_template', $this->getDefaultCommentTemplate());

        $requesterName = $ticketData['ticket_contact_name'] ?? ($meeting->owner ? $meeting->owner->name : 'User');
        $ticketNumber = $ticketData['ticket_number'] ?? '';
        $duration = max(15, (int) $meeting->starts_at->diffInMinutes($meeting->ends_at));

        $hostKeySection = '';
        if (! empty($meeting->host_key) && ($meeting->share_host_key || ! empty($ticketData['share_host_key']))) {
            $hostKeySection = "🛡️ **Host Key PIN:** {$meeting->host_key} (Claim Host: Zoom client > Participants > Claim Host)";
        }

        $appUrl = rtrim((string) config('app.url', url('/')), '/');

        $replacements = [
            '{meeting_title}' => $meeting->title,
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
            '{requester_name}' => (string) $requesterName,
            '{ticket_number}' => (string) $ticketNumber,
            '{app_url}' => $appUrl,
        ];

        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template);

        // Clean up redundant blank lines if host_key_section was empty
        return trim((string) preg_replace("/\n{3,}/", "\n\n", $rendered));
    }

    /**
     * Book a meeting on behalf of the ticket creator and optionally reply & close ticket.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function bookMeetingFromTicket(array $data, ?User $actor = null): array
    {
        $ticketEmail = strtolower(trim((string) ($data['ticket_email'] ?? '')));
        if (! filter_var($ticketEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Valid ticket contact email is required.');
        }

        $contactName = trim((string) ($data['ticket_contact_name'] ?? ''));
        if (empty($contactName)) {
            $contactName = explode('@', $ticketEmail)[0];
        }

        // 1. Find or create User for the ticket requester
        /** @var User $requesterUser */
        $requesterUser = User::firstOrCreate(
            ['email' => $ticketEmail],
            [
                'name' => $contactName,
                'password' => Hash::make(Str::random(32)),
                'timezone' => Setting::get('org.timezone', 'Asia/Kolkata'),
            ]
        );

        if (! $requesterUser->hasAnyRole(Role::all())) {
            $defaultRole = Role::where('name', 'User')->first() ?? Role::first();
            if ($defaultRole) {
                $requesterUser->assignRole($defaultRole);
            }
        }

        // 2. Parse times
        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : Carbon::now()->addMinutes(15);
        $durationMinutes = isset($data['duration_minutes']) ? max(15, (int) $data['duration_minutes']) : (int) Setting::get('zoho_desk.default_duration_minutes', 60);

        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $startsAt->copy()->addMinutes($durationMinutes);

        // 3. Prepare meeting data
        $ticketId = (string) ($data['ticket_id'] ?? '');
        $ticketNumber = (string) ($data['ticket_number'] ?? '');
        $ticketSubject = (string) ($data['ticket_subject'] ?? '');

        $title = ! empty($data['title']) ? trim((string) $data['title']) : ($ticketSubject ? "Zoom: {$ticketSubject}" : "Meeting with {$contactName}");

        $poolId = ! empty($data['pool_id']) ? (int) $data['pool_id'] : (int) Setting::get('zoho_desk.default_pool_id');
        $templateId = ! empty($data['template_id']) ? (int) $data['template_id'] : (int) Setting::get('zoho_desk.default_template_id');

        $shareHostKey = isset($data['share_host_key']) ? (bool) $data['share_host_key'] : true;

        $meetingData = [
            'title' => $title,
            'description' => "Scheduled via Zoho Desk Ticket #{$ticketNumber} for {$contactName} ({$ticketEmail})",
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt->toIso8601String(),
            'participant_count' => isset($data['participant_count']) ? (int) $data['participant_count'] : 10,
            'owner_user_id' => $requesterUser->id,
            'preferred_pool_id' => $poolId ?: null,
            'template_id' => $templateId ?: null,
            'share_host_key' => $shareHostKey,
            'custom_fields' => [
                'zoho_ticket_id' => $ticketId,
                'zoho_ticket_number' => $ticketNumber,
                'booked_by_agent' => $actor ? $actor->name : 'Zoho Desk Agent',
            ],
            'source' => 'zoho_desk',
        ];

        // 4. Create Meeting via MeetingService
        $bookingUser = $actor ?? $requesterUser;
        $meeting = $this->meetingService->createMeeting($bookingUser, $meetingData, [$ticketEmail]);

        $meeting->refresh();

        // 5. Render comment for ticket
        $commentText = $this->renderComment($meeting, [
            'ticket_contact_name' => $contactName,
            'ticket_number' => $ticketNumber,
            'share_host_key' => $shareHostKey,
        ], $data['custom_comment'] ?? null);

        $commentPosted = false;
        $ticketClosed = false;
        $commentError = null;
        $closeError = null;

        $postToTicket = isset($data['post_to_ticket']) ? (bool) $data['post_to_ticket'] : true;
        $isPublic = isset($data['is_public']) ? (bool) $data['is_public'] : (bool) Setting::get('zoho_desk.default_is_public', true);
        $closeTicket = isset($data['close_ticket']) ? (bool) $data['close_ticket'] : (bool) Setting::get('zoho_desk.auto_close_ticket', false);
        $closeStatus = (string) ($data['ticket_status'] ?? Setting::get('zoho_desk.ticket_close_status', 'Closed'));

        // 6. Post comment to Zoho Desk if ticketId present and credentials available
        if ($postToTicket && ! empty($ticketId)) {
            $commentRes = $this->postTicketComment($ticketId, $commentText, $isPublic);
            $commentPosted = $commentRes['success'];
            if (! $commentPosted) {
                $commentError = $commentRes['message'];
            }
        }

        // 7. Update ticket status if requested
        if ($closeTicket && ! empty($ticketId)) {
            $closeRes = $this->updateTicketStatus($ticketId, $closeStatus);
            $ticketClosed = $closeRes['success'];
            if (! $ticketClosed) {
                $closeError = $closeRes['message'];
            }
        }

        $this->auditService->log(
            'zoho_desk.meeting_booked',
            $meeting,
            null,
            [
                'ticket_id' => $ticketId,
                'ticket_number' => $ticketNumber,
                'comment_posted' => $commentPosted,
                'ticket_closed' => $ticketClosed,
            ],
            $actor ?? $requesterUser
        );

        return [
            'success' => true,
            'meeting' => [
                'id' => $meeting->id,
                'public_id' => $meeting->public_id,
                'title' => $meeting->title,
                'status' => $meeting->status,
                'starts_at' => $meeting->starts_at->toIso8601String(),
                'ends_at' => $meeting->ends_at->toIso8601String(),
                'timezone' => $meeting->timezone,
                'zoom_meeting_id' => $meeting->zoom_meeting_id,
                'join_url' => $meeting->join_url,
                'passcode' => $meeting->passcode,
                'host_key' => $meeting->host_key,
                'owner' => [
                    'id' => $requesterUser->id,
                    'name' => $requesterUser->name,
                    'email' => $requesterUser->email,
                ],
            ],
            'comment_text' => $commentText,
            'ticket_comment_posted' => $commentPosted,
            'ticket_comment_error' => $commentError,
            'ticket_closed' => $ticketClosed,
            'ticket_close_error' => $closeError,
            'message' => 'Meeting successfully scheduled on behalf of ticket creator.',
        ];
    }

    /**
     * Generate Zoho Desk extension ZIP package for 1-click download.
     */
    public function generateExtensionPackageZip(): string
    {
        $config = $this->getConfiguration();
        $serverUrl = $config['server_url'];
        $apiToken = $config['api_token'];

        $tempFile = tempnam(sys_get_temp_dir(), 'zpm_zd_ext_').'.zip';
        $zip = new ZipArchive;

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create temporary ZIP file for Zoho Desk extension.');
        }

        // 1. Manifest
        $manifest = [
            'name' => 'Zoom Pool Manager',
            'version' => '1.0.0',
            'author' => 'Zoom Pool Manager',
            'description' => 'Schedule Zoom meetings directly from Zoho Desk tickets on behalf of ticket requesters and post meeting credentials and links into the ticket.',
            'logo' => 'app/img/logo.svg',
            'modules' => [
                'widgets' => [
                    [
                        'location' => 'desk.ticket.detail.rightpanel',
                        'url' => './app/index.html',
                        'name' => 'Zoom Meeting',
                        'logo' => './app/img/logo.svg',
                    ],
                ],
            ],
            'storage' => true,
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Read templates from resources/zoho-desk-extension or generate them
        $extensionDir = resource_path('zoho-desk-extension');

        $indexHtml = file_exists("{$extensionDir}/app/index.html")
            ? (string) file_get_contents("{$extensionDir}/app/index.html")
            : $this->generateDefaultIndexHtml();

        $styleCss = file_exists("{$extensionDir}/app/css/style.css")
            ? (string) file_get_contents("{$extensionDir}/app/css/style.css")
            : $this->generateDefaultStyleCss();

        $extensionJs = file_exists("{$extensionDir}/app/js/extension.js")
            ? (string) file_get_contents("{$extensionDir}/app/js/extension.js")
            : $this->generateDefaultExtensionJs();

        // Inject configured serverUrl and apiToken into extension.js
        $extensionJs = str_replace(
            ['__ZPM_SERVER_URL__', '__ZPM_API_TOKEN__'],
            [$serverUrl, $apiToken],
            $extensionJs
        );

        $logoSvg = file_exists("{$extensionDir}/app/img/logo.svg")
            ? (string) file_get_contents("{$extensionDir}/app/img/logo.svg")
            : $this->generateDefaultLogoSvg();

        $readmeMd = file_exists("{$extensionDir}/README.md")
            ? (string) file_get_contents("{$extensionDir}/README.md")
            : $this->generateReadmeMd($serverUrl);

        $zip->addFromString('app/index.html', $indexHtml);
        $zip->addFromString('app/css/style.css', $styleCss);
        $zip->addFromString('app/js/extension.js', $extensionJs);
        $zip->addFromString('app/img/logo.svg', $logoSvg);
        $zip->addFromString('README.md', $readmeMd);

        $zip->close();

        return $tempFile;
    }

    /**
     * Generate default index.html for extension widget.
     */
    protected function generateDefaultIndexHtml(): string
    {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Zoom Pool Manager</title>
  <link rel="stylesheet" href="./css/style.css">
  <script src="https://js.zohostatic.com/support/developer_sdk/v1/desk_extension.js"></script>
</head>
<body>
  <div id="widget-app" class="widget-container">
    <!-- Header -->
    <header class="widget-header">
      <div class="brand">
        <svg class="brand-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <polygon points="23 7 16 12 23 17 23 7"></polygon>
          <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
        </svg>
        <span class="brand-title">Zoom Pool Manager</span>
      </div>
      <button id="btn-toggle-config" class="btn-icon" title="Server Connection Settings">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
      </button>
    </header>

    <!-- Config Panel (Collapsible) -->
    <div id="config-panel" class="card config-card hidden">
      <h4 class="card-title">ZPM Server Connection</h4>
      <div class="form-group">
        <label for="cfg-server-url">ZPM Server URL</label>
        <input type="url" id="cfg-server-url" placeholder="https://zoom.yourdomain.edu">
      </div>
      <div class="form-group">
        <label for="cfg-api-token">ZPM API Token</label>
        <input type="password" id="cfg-api-token" placeholder="zpm_zd_...">
      </div>
      <div class="btn-row">
        <button type="button" id="btn-save-config" class="btn btn-primary btn-sm">Save & Connect</button>
        <button type="button" id="btn-close-config" class="btn btn-secondary btn-sm">Close</button>
      </div>
    </div>

    <!-- Ticket Context Card -->
    <div class="card ticket-card">
      <div class="ticket-header">
        <span class="badge" id="lbl-ticket-number">Ticket</span>
        <span class="ticket-email" id="lbl-ticket-email">Loading...</span>
      </div>
      <div class="ticket-contact-name" id="lbl-contact-name">Requester</div>
    </div>

    <!-- Booking Form -->
    <form id="booking-form" class="space-y">
      <div class="form-group">
        <label for="inp-topic">Meeting Topic</label>
        <input type="text" id="inp-topic" required placeholder="Zoom Meeting with requester">
      </div>

      <div class="form-row">
        <div class="form-group flex-1">
          <label for="inp-date">Date</label>
          <input type="date" id="inp-date" required>
        </div>
        <div class="form-group flex-1">
          <label for="inp-time">Time</label>
          <input type="time" id="inp-time" required>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group flex-1">
          <label for="inp-duration">Duration</label>
          <select id="inp-duration">
            <option value="30">30 min</option>
            <option value="45">45 min</option>
            <option value="60" selected>1 hour</option>
            <option value="90">1.5 hours</option>
            <option value="120">2 hours</option>
            <option value="180">3 hours</option>
            <option value="240">4 hours</option>
          </select>
        </div>
        <div class="form-group flex-1">
          <label for="inp-pool">Resource Pool</label>
          <select id="inp-pool">
            <option value="">Default Available Pool</option>
          </select>
        </div>
      </div>

      <!-- Action Toggles -->
      <div class="card toggles-card">
        <label class="checkbox-label">
          <input type="checkbox" id="chk-post-ticket" checked>
          <span>Post Meeting Details in Ticket</span>
        </label>

        <div id="reply-type-row" class="sub-options">
          <label class="radio-label">
            <input type="radio" name="reply_type" value="public" checked>
            <span>Public Reply (to user)</span>
          </label>
          <label class="radio-label">
            <input type="radio" name="reply_type" value="private">
            <span>Private Comment (internal)</span>
          </label>
        </div>

        <label class="checkbox-label">
          <input type="checkbox" id="chk-close-ticket" checked>
          <span>Close / Resolve Ticket after booking</span>
        </label>

        <label class="checkbox-label">
          <input type="checkbox" id="chk-share-host-key" checked>
          <span>Include Host Key PIN for Claim Host</span>
        </label>
      </div>

      <button type="submit" id="btn-submit" class="btn btn-primary btn-block">
        <span id="btn-submit-text">Book Meeting & Update Ticket</span>
        <span id="btn-submit-spinner" class="spinner hidden"></span>
      </button>
    </form>

    <!-- Success Result Card -->
    <div id="result-card" class="card result-card hidden">
      <div class="result-header">
        <div class="check-icon">✓</div>
        <div>
          <h4 class="result-title">Meeting Scheduled!</h4>
          <p class="result-sub" id="lbl-result-status"></p>
        </div>
      </div>

      <div class="result-field">
        <label>Join Zoom URL</label>
        <div class="copy-input">
          <input type="text" id="res-join-url" readonly>
          <button type="button" class="btn-copy" onclick="copyField('res-join-url')">Copy</button>
        </div>
      </div>

      <div class="result-row">
        <div class="result-field flex-1">
          <label>Meeting ID</label>
          <div class="copy-input">
            <input type="text" id="res-meeting-id" readonly>
            <button type="button" class="btn-copy" onclick="copyField('res-meeting-id')">Copy</button>
          </div>
        </div>
        <div class="result-field flex-1">
          <label>Passcode</label>
          <div class="copy-input">
            <input type="text" id="res-passcode" readonly>
            <button type="button" class="btn-copy" onclick="copyField('res-passcode')">Copy</button>
          </div>
        </div>
      </div>

      <div class="result-field" id="res-hostkey-wrap">
        <label>Host Key PIN (Claim Host)</label>
        <div class="copy-input">
          <input type="text" id="res-host-key" readonly>
          <button type="button" class="btn-copy" onclick="copyField('res-host-key')">Copy</button>
        </div>
      </div>

      <div class="result-badges">
        <span id="badge-comment" class="status-badge">Comment Posted</span>
        <span id="badge-closed" class="status-badge">Ticket Closed</span>
      </div>

      <div class="btn-row">
        <a id="btn-open-zpm" href="#" target="_blank" class="btn btn-secondary btn-sm flex-1">Open in ZPM</a>
        <button type="button" id="btn-reset" class="btn btn-primary btn-sm flex-1">Book Another</button>
      </div>
    </div>

    <!-- Alert Banner -->
    <div id="alert-banner" class="alert-banner hidden"></div>
  </div>

  <script src="./js/extension.js"></script>
</body>
</html>
HTML;
    }

    /**
     * Generate default style.css for extension widget.
     */
    protected function generateDefaultStyleCss(): string
    {
        return <<<'CSS'
:root {
  --primary: #0284c7;
  --primary-hover: #0369a1;
  --bg: #f8fafc;
  --card-bg: #ffffff;
  --text: #0f172a;
  --text-muted: #64748b;
  --border: #e2e8f0;
  --success: #10b981;
  --danger: #ef4444;
  --radius: 10px;
}

* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
}

body {
  background-color: var(--bg);
  color: var(--text);
  font-size: 13px;
  line-height: 1.4;
  padding: 12px;
}

.widget-container {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-width: 100%;
}

.widget-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 8px;
  border-bottom: 1px solid var(--border);
}

.brand {
  display: flex;
  align-items: center;
  gap: 8px;
  font-weight: 700;
  font-size: 14px;
  color: var(--primary);
}

.brand-icon {
  width: 20px;
  height: 20px;
}

.btn-icon {
  background: transparent;
  border: none;
  cursor: pointer;
  color: var(--text-muted);
  padding: 4px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}
.btn-icon:hover {
  background: var(--border);
  color: var(--text);
}
.btn-icon svg {
  width: 16px;
  height: 16px;
}

.card {
  background: var(--card-bg);
  border: 1px solid var(--border);
  border-radius: var(--radius);
  padding: 12px;
}

.card-title {
  font-size: 12px;
  font-weight: 700;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: var(--text-muted);
}

.ticket-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.badge {
  background: #e0f2fe;
  color: #0369a1;
  font-weight: 700;
  font-size: 11px;
  padding: 2px 8px;
  border-radius: 12px;
}

.ticket-email {
  font-size: 12px;
  color: var(--text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.ticket-contact-name {
  font-weight: 600;
  font-size: 14px;
  margin-top: 4px;
}

.form-group {
  display: flex;
  flex-direction: column;
  gap: 4px;
  margin-bottom: 8px;
}

.form-group label {
  font-size: 11px;
  font-weight: 600;
  color: var(--text-muted);
}

input[type="text"],
input[type="date"],
input[type="time"],
input[type="url"],
input[type="password"],
select {
  width: 100%;
  padding: 8px 10px;
  font-size: 13px;
  border: 1px solid var(--border);
  border-radius: 6px;
  background: #fff;
  color: var(--text);
  outline: none;
  transition: border-color 0.2s;
}

input:focus, select:focus {
  border-color: var(--primary);
}

.form-row {
  display: flex;
  gap: 8px;
}

.flex-1 {
  flex: 1;
}

.checkbox-label, .radio-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  cursor: pointer;
  margin-bottom: 6px;
}

.sub-options {
  margin-left: 20px;
  margin-bottom: 8px;
}

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 9px 14px;
  font-size: 13px;
  font-weight: 600;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  transition: all 0.15s;
  text-decoration: none;
}

.btn-primary {
  background: var(--primary);
  color: white;
}
.btn-primary:hover {
  background: var(--primary-hover);
}

.btn-secondary {
  background: #f1f5f9;
  color: var(--text);
  border: 1px solid var(--border);
}
.btn-secondary:hover {
  background: #e2e8f0;
}

.btn-block {
  width: 100%;
}

.btn-sm {
  padding: 6px 10px;
  font-size: 12px;
}

.btn-row {
  display: flex;
  gap: 8px;
  margin-top: 8px;
}

.copy-input {
  display: flex;
  gap: 4px;
}
.copy-input input {
  font-family: monospace;
  font-size: 12px;
  background: #f8fafc;
}
.btn-copy {
  padding: 4px 8px;
  font-size: 11px;
  border: 1px solid var(--border);
  background: #fff;
  border-radius: 4px;
  cursor: pointer;
}
.btn-copy:hover {
  background: #f1f5f9;
}

.result-card {
  border-left: 4px solid var(--success);
}
.result-header {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 12px;
}
.check-icon {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #d1fae5;
  color: #065f46;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
}
.result-title {
  font-size: 14px;
  font-weight: bold;
  color: #065f46;
}
.result-sub {
  font-size: 11px;
  color: var(--text-muted);
}
.result-field {
  margin-bottom: 8px;
}
.result-field label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--text-muted);
}
.result-row {
  display: flex;
  gap: 8px;
}

.result-badges {
  display: flex;
  gap: 6px;
  margin: 10px 0;
  flex-wrap: wrap;
}
.status-badge {
  font-size: 10px;
  font-weight: bold;
  padding: 2px 6px;
  border-radius: 10px;
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #a7f3d0;
}
.status-badge.failed {
  background: #fef2f2;
  color: #b91c1c;
  border-color: #fecaca;
}

.alert-banner {
  padding: 8px 12px;
  border-radius: 6px;
  font-size: 12px;
  margin-top: 8px;
}
.alert-banner.error {
  background: #fef2f2;
  color: #991b1b;
  border: 1px solid #fecaca;
}
.alert-banner.success {
  background: #ecfdf5;
  color: #065f46;
  border: 1px solid #a7f3d0;
}

.hidden {
  display: none !important;
}

.spinner {
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255,255,255,0.4);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  display: inline-block;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
CSS;
    }

    /**
     * Generate default extension.js for widget.
     */
    protected function generateDefaultExtensionJs(): string
    {
        return <<<'JS'
(function() {
  'use strict';

  // State
  let config = {
    serverUrl: '__ZPM_SERVER_URL__',
    apiToken: '__ZPM_API_TOKEN__'
  };

  let currentTicket = {
    id: '',
    ticketNumber: '',
    subject: '',
    email: '',
    contactName: ''
  };

  let zpmOptions = {
    pools: [],
    templates: []
  };

  // DOM Elements
  const elConfigPanel = document.getElementById('config-panel');
  const btnToggleConfig = document.getElementById('btn-toggle-config');
  const btnCloseConfig = document.getElementById('btn-close-config');
  const btnSaveConfig = document.getElementById('btn-save-config');
  const cfgServerUrl = document.getElementById('cfg-server-url');
  const cfgApiToken = document.getElementById('cfg-api-token');

  const lblTicketNumber = document.getElementById('lbl-ticket-number');
  const lblTicketEmail = document.getElementById('lbl-ticket-email');
  const lblContactName = document.getElementById('lbl-contact-name');

  const formBooking = document.getElementById('booking-form');
  const inpTopic = document.getElementById('inp-topic');
  const inpDate = document.getElementById('inp-date');
  const inpTime = document.getElementById('inp-time');
  const inpDuration = document.getElementById('inp-duration');
  const inpPool = document.getElementById('inp-pool');
  const chkPostTicket = document.getElementById('chk-post-ticket');
  const chkCloseTicket = document.getElementById('chk-close-ticket');
  const chkShareHostKey = document.getElementById('chk-share-host-key');
  const btnSubmit = document.getElementById('btn-submit');
  const btnSubmitText = document.getElementById('btn-submit-text');
  const btnSubmitSpinner = document.getElementById('btn-submit-spinner');

  const resultCard = document.getElementById('result-card');
  const lblResultStatus = document.getElementById('lbl-result-status');
  const resJoinUrl = document.getElementById('res-join-url');
  const resMeetingId = document.getElementById('res-meeting-id');
  const resPasscode = document.getElementById('res-passcode');
  const resHostKey = document.getElementById('res-host-key');
  const badgeComment = document.getElementById('badge-comment');
  const badgeClosed = document.getElementById('badge-closed');
  const btnOpenZpm = document.getElementById('btn-open-zpm');
  const btnReset = document.getElementById('btn-reset');
  const alertBanner = document.getElementById('alert-banner');

  // Copy helper
  window.copyField = function(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.select();
    document.execCommand('copy');
    showAlert('Copied to clipboard!', 'success');
  };

  function showAlert(msg, type = 'error') {
    if (!alertBanner) return;
    alertBanner.textContent = msg;
    alertBanner.className = `alert-banner ${type}`;
    alertBanner.classList.remove('hidden');
    setTimeout(() => {
      alertBanner.classList.add('hidden');
    }, 5000);
  }

  // Load saved config from local/extension storage
  function loadConfig() {
    try {
      const saved = localStorage.getItem('zpm_zd_config');
      if (saved) {
        const parsed = JSON.parse(saved);
        if (parsed.serverUrl) config.serverUrl = parsed.serverUrl;
        if (parsed.apiToken) config.apiToken = parsed.apiToken;
      }
    } catch (e) {}

    if (cfgServerUrl) cfgServerUrl.value = config.serverUrl;
    if (cfgApiToken) cfgApiToken.value = config.apiToken;
  }

  function saveConfig() {
    config.serverUrl = cfgServerUrl.value.trim().replace(/\/$/, '');
    config.apiToken = cfgApiToken.value.trim();

    try {
      localStorage.setItem('zpm_zd_config', JSON.stringify(config));
    } catch (e) {}

    elConfigPanel.classList.add('hidden');
    showAlert('Connection settings saved!', 'success');
    fetchOptions();
  }

  // Set default date & time (next upcoming 30m slot)
  function initDateTimeDefaults() {
    const now = new Date();
    // Round to next 30 min
    const minutes = now.getMinutes();
    const roundedMinutes = minutes < 30 ? 30 : 60;
    now.setMinutes(roundedMinutes, 0, 0);

    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const mins = String(now.getMinutes()).padStart(2, '0');

    if (inpDate) inpDate.value = `${year}-${month}-${day}`;
    if (inpTime) inpTime.value = `${hours}:${mins}`;
  }

  // Fetch ZPM options (pools, templates)
  async function fetchOptions() {
    if (!config.serverUrl) return;

    try {
      const res = await fetch(`${config.serverUrl}/api/v1/integrations/zoho-desk/options`, {
        headers: {
          'X-API-KEY': config.apiToken,
          'Accept': 'application/json'
        }
      });

      if (res.ok) {
        const data = await res.json();
        zpmOptions = data;

        if (inpPool && data.pools) {
          inpPool.innerHTML = '<option value="">Default Available Pool</option>';
          data.pools.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.name} (Cap: ${p.capacity})`;
            if (data.default_pool_id && data.default_pool_id === p.id) {
              opt.selected = true;
            }
            inpPool.appendChild(opt);
          });
        }
      }
    } catch (err) {
      console.warn('Could not fetch ZPM options:', err);
    }
  }

  // Handle Form Submission
  async function handleBookingSubmit(e) {
    e.preventDefault();

    if (!config.serverUrl || !config.apiToken) {
      elConfigPanel.classList.remove('hidden');
      showAlert('Please configure ZPM Server URL and API Token first.');
      return;
    }

    const topic = inpTopic.value.trim();
    const date = inpDate.value;
    const time = inpTime.value;
    const duration = parseInt(inpDuration.value, 10) || 60;
    const poolId = inpPool.value ? parseInt(inpPool.value, 10) : null;
    const postToTicket = chkPostTicket.checked;
    const isPublic = document.querySelector('input[name="reply_type"]:checked')?.value === 'public';
    const closeTicket = chkCloseTicket.checked;
    const shareHostKey = chkShareHostKey.checked;

    const startsAt = new Date(`${date}T${time}:00`).toISOString();

    btnSubmit.disabled = true;
    btnSubmitText.textContent = 'Booking meeting...';
    btnSubmitSpinner.classList.remove('hidden');

    try {
      const payload = {
        ticket_id: currentTicket.id,
        ticket_number: currentTicket.ticketNumber,
        ticket_subject: currentTicket.subject,
        ticket_email: currentTicket.email,
        ticket_contact_name: currentTicket.contactName,
        title: topic,
        starts_at: startsAt,
        duration_minutes: duration,
        pool_id: poolId,
        post_to_ticket: postToTicket,
        is_public: isPublic,
        close_ticket: closeTicket,
        share_host_key: shareHostKey
      };

      const res = await fetch(`${config.serverUrl}/api/v1/integrations/zoho-desk/book-and-reply`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-API-KEY': config.apiToken,
          'Accept': 'application/json'
        },
        body: JSON.stringify(payload)
      });

      const result = await res.json();

      if (!res.ok || !result.success) {
        throw new Error(result.message || 'Scheduling failed. Please check ZPM configuration.');
      }

      // If server could not post directly to Zoho Desk (e.g. server-side OAuth wasn't configured),
      // we can post client-side using Zoho Desk SDK!
      if (postToTicket && !result.ticket_comment_posted && window.ZOHODESK) {
        try {
          await window.ZOHODESK.comment.add({
            id: currentTicket.id,
            isPublic: isPublic,
            content: result.comment_text
          });
          result.ticket_comment_posted = true;
        } catch (sdkErr) {
          console.warn('Client-side SDK comment fallback failed:', sdkErr);
        }
      }

      if (closeTicket && !result.ticket_closed && window.ZOHODESK) {
        try {
          await window.ZOHODESK.ticket.update({
            id: currentTicket.id,
            status: 'Closed'
          });
          result.ticket_closed = true;
        } catch (sdkErr) {
          console.warn('Client-side SDK ticket close fallback failed:', sdkErr);
        }
      }

      // Display Success Result
      displaySuccess(result);
    } catch (err) {
      showAlert(err.message || 'An error occurred during booking.');
    } finally {
      btnSubmit.disabled = false;
      btnSubmitText.textContent = 'Book Meeting & Update Ticket';
      btnSubmitSpinner.classList.add('hidden');
    }
  }

  function displaySuccess(res) {
    const meeting = res.meeting;
    formBooking.classList.add('hidden');
    resultCard.classList.remove('hidden');

    lblResultStatus.textContent = `Scheduled on behalf of ${meeting.owner?.name || currentTicket.contactName}`;
    resJoinUrl.value = meeting.join_url || '';
    resMeetingId.value = meeting.zoom_meeting_id || '';
    resPasscode.value = meeting.passcode || '';
    resHostKey.value = meeting.host_key || '';

    if (btnOpenZpm) {
      btnOpenZpm.href = `${config.serverUrl}/app/meetings`;
    }

    if (badgeComment) {
      if (res.ticket_comment_posted) {
        badgeComment.textContent = '✓ Comment Posted';
        badgeComment.className = 'status-badge';
      } else {
        badgeComment.textContent = 'Comment Not Posted';
        badgeComment.className = 'status-badge failed';
      }
    }

    if (badgeClosed) {
      if (res.ticket_closed) {
        badgeClosed.textContent = '✓ Ticket Closed';
        badgeClosed.className = 'status-badge';
      } else {
        badgeClosed.textContent = 'Ticket Not Closed';
        badgeClosed.className = 'status-badge failed';
      }
    }
  }

  function resetForm() {
    resultCard.classList.add('hidden');
    formBooking.classList.remove('hidden');
    initDateTimeDefaults();
  }

  // Initialize Zoho Desk SDK
  function initDeskSdk() {
    if (typeof ZOHODESK !== 'undefined') {
      ZOHODESK.init().then(function(App) {
        ZOHODESK.get('ticket').then(function(response) {
          const t = response && (response.ticket || response['ticket']);
          if (t) {
            currentTicket.id = t.id || '';
            currentTicket.ticketNumber = t.ticketNumber || t.id || '';
            currentTicket.subject = t.subject || '';
            currentTicket.email = t.email || (t.contact && t.contact.email) || '';
            currentTicket.contactName = (t.contact && t.contact.name) || t.contactName || currentTicket.email.split('@')[0];

            updateTicketUi();
          }
        }).catch(function(err) {
          console.warn('Failed to get ticket from Desk SDK:', err);
        });
      }).catch(function(err) {
        console.warn('Desk SDK init error:', err);
      });
    } else {
      // Demo / simulation mode if running outside Zoho Desk
      currentTicket = {
        id: '1001',
        ticketNumber: 'TKT-10492',
        subject: 'Need Zoom meeting with Dean',
        email: 'faculty@krea.edu.in',
        contactName: 'Prof. Rajesh Sharma'
      };
      updateTicketUi();
    }
  }

  function updateTicketUi() {
    if (lblTicketNumber) lblTicketNumber.textContent = `#${currentTicket.ticketNumber}`;
    if (lblTicketEmail) lblTicketEmail.textContent = currentTicket.email;
    if (lblContactName) lblContactName.textContent = currentTicket.contactName;
    if (inpTopic && (!inpTopic.value || inpTopic.value === 'Zoom Meeting with requester')) {
      inpTopic.value = currentTicket.subject ? `Meeting: ${currentTicket.subject}` : `Zoom Meeting with ${currentTicket.contactName}`;
    }
  }

  // Event Listeners
  if (btnToggleConfig) {
    btnToggleConfig.addEventListener('click', () => elConfigPanel.classList.toggle('hidden'));
  }
  if (btnCloseConfig) {
    btnCloseConfig.addEventListener('click', () => elConfigPanel.classList.add('hidden'));
  }
  if (btnSaveConfig) {
    btnSaveConfig.addEventListener('click', saveConfig);
  }
  if (formBooking) {
    formBooking.addEventListener('submit', handleBookingSubmit);
  }
  if (btnReset) {
    btnReset.addEventListener('click', resetForm);
  }

  // Startup
  loadConfig();
  initDateTimeDefaults();
  initDeskSdk();
  fetchOptions();
})();
JS;
    }

    /**
     * Generate default logo.svg.
     */
    protected function generateDefaultLogoSvg(): string
    {
        return <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="48" height="48">
  <rect width="48" height="48" rx="12" fill="#0284c7"/>
  <path d="M12 16C12 14.8954 12.8954 14 14 14H26C27.1046 14 28 14.8954 28 16V32C28 33.1046 27.1046 34 26 34H14C12.8954 34 12 33.1046 12 32V16Z" fill="white"/>
  <path d="M30 20.5L37 15.5V32.5L30 27.5V20.5Z" fill="white"/>
</svg>
SVG;
    }

    /**
     * Generate README.md with installation instructions for Zoho Desk.
     */
    protected function generateReadmeMd(string $serverUrl): string
    {
        return <<<MD
# Zoom Pool Manager - Zoho Desk Extension

Schedule Zoom meetings on behalf of ticket requesters directly from Zoho Desk and automatically post meeting credentials and links into the ticket.

## Features
- **Book On Behalf Of:** Automatically sets the ticket creator as the meeting owner in Zoom Pool Manager.
- **Auto-Reply & Post to Ticket:** Instantly posts the Zoom Join Link, Meeting ID, Passcode, and Host Key PIN directly into the Zoho Desk ticket conversation.
- **Auto-Close Ticket:** Option to automatically resolve/close the ticket once the meeting is scheduled.
- **Resource Pool Allocation:** Intelligent capacity and policy enforcement from your Zoom Pool Manager cluster.

## Installation Instructions

1. Log in to your **Zoho Desk** portal as an Administrator.
2. Click the **Setup (Gear)** icon in the top-right corner.
3. Under **Developer Space**, select **Extensions** (or **Custom Apps**).
4. Click **Install Extension** or **Upload Custom Extension**.
5. Upload this `.zip` file (`zoom-pool-manager-zoho-desk.zip`).
6. In Extension Configuration, ensure the pre-filled Server URL (`{$serverUrl}`) and API Token match your Zoom Pool Manager instance.
7. Set visibility to **All Agents** or your designated **IT Support** department.
8. Open any ticket in Zoho Desk. Click the **Zoom Meeting** widget on the right panel to begin scheduling!
MD;
    }
}
