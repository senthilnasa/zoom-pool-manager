<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Zoom Developer Portal & Marketplace URLs
    |--------------------------------------------------------------------------
    */
    'marketplace_url' => 'https://marketplace.zoom.us/develop/',
    'scopes_docs_url' => 'https://developers.zoom.us/docs/integrations/oauth-scopes-overview/',
    'app_type' => 'Server-to-Server OAuth',
    'webhook_secret_token' => env('ZOOM_WEBHOOK_SECRET_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Required Scopes for Zoom Pool Manager (Core Functionality)
    |--------------------------------------------------------------------------
    | These scopes are mandatory for basic pooling, meeting reservation,
    | automated host link generation, and host key rotation.
    */
    'required_scopes' => [
        [
            'scope' => 'meeting:write:admin',
            'granular' => 'meeting:write:meeting:admin',
            'category' => 'Meetings',
            'label' => 'Create & Manage Meetings',
            'description' => 'Allows ZPM to schedule pooled sessions, update meeting topics/times, apply security profiles, and delete/release cancelled bookings.',
            'endpoints' => ['POST /users/{userId}/meetings', 'PATCH /meetings/{meetingId}', 'DELETE /meetings/{meetingId}'],
            'zoom_category' => 'Meeting',
            'zoom_option_label' => 'View and manage all user meetings',
            'how_to_find' => 'In Zoom Marketplace "+ Add Scopes" modal: Click "Meeting" in the left sidebar, then check "View and manage all user meetings" (or "Create a meeting for a user" in granular view).',
            'required' => true,
        ],
        [
            'scope' => 'meeting:read:admin',
            'granular' => 'meeting:read:meeting:admin',
            'category' => 'Meetings',
            'label' => 'Read Meeting Details & Start URLs',
            'description' => 'Allows ZPM to query meeting details, retrieve dynamic JIT host start URLs, and verify session status.',
            'endpoints' => ['GET /meetings/{meetingId}', 'GET /users/{userId}/meetings'],
            'zoom_category' => 'Meeting',
            'zoom_option_label' => 'View all user meetings',
            'how_to_find' => 'In Zoom Marketplace "+ Add Scopes" modal: Click "Meeting" in the left sidebar, then check "View all user meetings" (or "View a meeting").',
            'required' => true,
        ],
        [
            'scope' => 'user:read:admin',
            'granular' => 'user:read:user:admin',
            'category' => 'Users',
            'label' => 'Inspect Pooled Host Accounts',
            'description' => 'Discovers host accounts in your Zoom organization, queries license types (Basic vs Licensed), and verifies meeting seat capacity (e.g. 100, 300, 500, or 1000 seats).',
            'endpoints' => ['GET /users', 'GET /users/{userId}'],
            'zoom_category' => 'User',
            'zoom_option_label' => 'View all user information',
            'how_to_find' => 'In Zoom Marketplace "+ Add Scopes" modal: Click "User" in the left sidebar, then check "View all user information" (or "View users").',
            'required' => true,
        ],
        [
            'scope' => 'user:write:admin',
            'granular' => 'user:update:user:admin',
            'category' => 'Users',
            'label' => 'Rotate Host Keys',
            'description' => 'Enables automated rotation of the 6-digit host key on pooled accounts after each meeting ends, preventing unauthorized host takeover.',
            'endpoints' => ['PATCH /users/{userId}'],
            'zoom_category' => 'User',
            'zoom_option_label' => 'View and manage all user information',
            'how_to_find' => 'In Zoom Marketplace "+ Add Scopes" modal: Click "User" in the left sidebar, then check "View and manage all user information" (or "Update a user").',
            'required' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recommended Scopes (Recordings & Attendance Reports)
    |--------------------------------------------------------------------------
    | These scopes power Cloud Recording indexing and past meeting attendee reports.
    */
    'recommended_scopes' => [
        [
            'scope' => 'recording:read:admin',
            'granular' => 'recording:read:recording:admin',
            'category' => 'Cloud Recordings',
            'label' => 'Cloud Recordings & Transcripts',
            'description' => 'Allows ZPM to index completed cloud recordings, generate secure playback redirects, and download AI audio transcripts.',
            'endpoints' => ['GET /meetings/{meetingId}/recordings', 'GET /users/{userId}/recordings'],
            'zoom_category' => 'Recording',
            'zoom_option_label' => 'View all user recordings',
            'how_to_find' => 'In Zoom Marketplace "+ Add Scopes" modal: Click "Recording" (or "Cloud Recording") on the left sidebar, then check "View all user recordings" (or granular "View a meeting\'s recordings" / "View recording").',
            'required' => false,
        ],
        [
            'scope' => 'report:read:admin',
            'granular' => 'report:read:list_meeting_participants:admin',
            'category' => 'Reports & Attendance',
            'label' => 'Meeting Attendance & Participant Reports',
            'description' => 'Allows ZPM to pull participant attendance records, join times, leave times, and total session duration for post-meeting auditing.',
            'endpoints' => ['GET /report/meetings/{meetingId}/participants', 'GET /past_meetings/{meetingId}/participants'],
            'zoom_category' => 'Report',
            'zoom_option_label' => 'View all user and meeting reports',
            'how_to_find' => 'In Zoom Marketplace "+ Add Scopes" modal: Click "Report" on the left sidebar, then check "View all user and meeting reports" (or "View meeting report").',
            'required' => false,
        ],
        [
            'scope' => 'dashboard:read:admin',
            'granular' => 'dashboard:read:list_meeting_participants:admin',
            'category' => 'Telemetry',
            'label' => 'Live Telemetry & Diagnostics',
            'description' => 'Provides live meeting metrics, latency, and real-time active session diagnostics in your institutional Zoom account.',
            'endpoints' => ['GET /metrics/meetings', 'GET /metrics/meetings/{meetingId}/participants'],
            'zoom_category' => 'Dashboard',
            'zoom_option_label' => 'View all user dashboard data',
            'how_to_find' => 'In Zoom Marketplace "+ Add Scopes" modal: Click "Dashboard" on the left sidebar, then check "View all user dashboard data".',
            'required' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Event Subscriptions
    |--------------------------------------------------------------------------
    | Events that should be enabled in the Zoom Marketplace app under
    | Feature -> Event Subscriptions -> Add Event Types.
    */
    'webhook_events' => [
        [
            'event' => 'meeting.started',
            'category' => 'Meeting',
            'description' => 'Notifies ZPM the moment a host starts a pooled session; updates live status and begins the active buffer window.',
        ],
        [
            'event' => 'meeting.ended',
            'category' => 'Meeting',
            'description' => 'Notifies ZPM that the meeting concluded; immediately frees the host resource and triggers automatic host key rotation.',
        ],
        [
            'event' => 'meeting.updated',
            'category' => 'Meeting',
            'description' => 'Detects when meeting settings, topics, or times are modified in the native Zoom client.',
        ],
        [
            'event' => 'meeting.deleted',
            'category' => 'Meeting',
            'description' => 'Syncs cancellation if a meeting is directly removed from the Zoom portal.',
        ],
        [
            'event' => 'recording.completed',
            'category' => 'Recording',
            'description' => 'Notifies ZPM that cloud recording files are processed and ready for distribution.',
        ],
        [
            'event' => 'recording.transcript_completed',
            'category' => 'Recording',
            'description' => 'Notifies ZPM that audio/video transcript files are ready to sync.',
        ],
    ],
];
