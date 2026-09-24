# Zoom Verification Record (Milestone M0)

> **Status: NOT YET VERIFIED.** This file is a template. It is filled in during milestone M0 by running the
> verification scripts in `tools/zoom-spike/` against a **real test Zoom account**.
>
> Rule: application code may only use a Zoom endpoint, field, scope, limit or webhook format
> whose row below is marked `VERIFIED`. Rows marked `UNVERIFIED` or `NOT SUPPORTED` must not be
> used; `NOT SUPPORTED` items must also be listed in `docs/zoom-limitations.md` with the fallback.

## 0. Test environment

| Item | Value |
|---|---|
| Verified by | |
| Date | |
| Zoom plan (Pro / Business / Education / Enterprise) | |
| Account type (single / master with sub-accounts) | |
| Test Server-to-Server OAuth app name | |
| Number of test users (licensed / basic) | |
| Official Zoom docs pages consulted (URLs) | |

Credentials used for the spike live only in `tools/zoom-spike/.env.local` (git-ignored). Never paste them here.

## 1. Status legend

- `VERIFIED` — works exactly as described; fixture saved.
- `VERIFIED WITH CHANGES` — works, but differs from SPEC; the difference is described and SPEC must be updated.
- `NOT SUPPORTED` — Zoom does not expose it; fallback recorded in `docs/zoom-limitations.md`.
- `UNVERIFIED` — not yet tested. **Do not use in code.**

## 2. Verification checklist

For each row, fill in: actual endpoint/field, required scope(s), result, and the fixture file saved in `tests/Fixtures/Zoom/`.

### 2.1 Authentication

| # | Item | SPEC expectation | Actual endpoint / field | Required scope(s) | Status | Fixture | Notes |
|---|---|---|---|---|---|---|---|
| A1 | S2S token request | `POST https://zoom.us/oauth/token`, `grant_type=account_credentials`, Basic auth | | n/a | UNVERIFIED | | |
| A2 | Token lifetime | `expires_in` ≈ 3600 s | | n/a | UNVERIFIED | | |
| A3 | Granted scopes returned in token response | `scope` field present | | n/a | UNVERIFIED | | |
| A4 | Behaviour of expired/invalid token | HTTP 401 | | n/a | UNVERIFIED | | |

### 2.2 Users and capabilities

| # | Item | SPEC expectation | Actual endpoint / field | Required scope(s) | Status | Fixture | Notes |
|---|---|---|---|---|---|---|---|
| U1 | List users with pagination | `GET /users`, `next_page_token` | | | UNVERIFIED | | |
| U2 | License type field | user `type` (1 Basic, 2 Licensed) | | | UNVERIFIED | | |
| U3 | Meeting participant capacity | user settings / feature field | | | UNVERIFIED | | |
| U4 | Large Meeting add-on + capacity | user settings / feature field | | | UNVERIFIED | | |
| U5 | Webinar add-on + capacity | user settings / feature field | | | UNVERIFIED | | |
| U6 | Cloud recording enabled | user settings field | | | UNVERIFIED | | |
| U7 | Transcript available | user settings field | | | UNVERIFIED | | |
| U8 | AI Companion available | user/account settings field | | | UNVERIFIED | | |
| U9 | Max concurrent meetings per host on this plan | 1 (default assumption) | | | UNVERIFIED | | |

### 2.3 Meetings

| # | Item | SPEC expectation | Actual endpoint / field | Required scope(s) | Status | Fixture | Notes |
|---|---|---|---|---|---|---|---|
| M1 | Create scheduled meeting on a pooled user | `POST /users/{userId}/meetings`, type 2 | | | UNVERIFIED | | |
| M2 | Get meeting | `GET /meetings/{meetingId}` | | | UNVERIFIED | | |
| M3 | Update meeting | `PATCH /meetings/{meetingId}` | | | UNVERIFIED | | |
| M4 | Delete meeting | `DELETE /meetings/{meetingId}` | | | UNVERIFIED | | |
| M5 | List a user's upcoming meetings (for idempotency adoption) | `GET /users/{userId}/meetings?type=upcoming` | | | UNVERIFIED | | |
| M6 | Agenda field keeps marker `[ZPM:{id}]` unchanged | round-trips exactly | | | UNVERIFIED | | |
| M7 | Recurring fixed-time meeting | type 8 + `recurrence` object | | | UNVERIFIED | | |
| M8 | Maximum occurrences per recurring meeting | believed 50 | | | UNVERIFIED | | |
| M9 | Update / delete a single occurrence | `occurrence_id` query param | | | UNVERIFIED | | |
| M10 | Same `join_url` for all occurrences of a type 8 series | yes | | | UNVERIFIED | | |
| M11 | Daily per-user limit on create/update | exists — record the number | | | UNVERIFIED | | |

### 2.4 Security profile settings (one row per setting used by security profiles)

| # | ZPM setting | Zoom API field | Enforceable per meeting? | Status | Notes |
|---|---|---|---|---|---|
| S1 | passcode | | | UNVERIFIED | |
| S2 | waiting_room | | | UNVERIFIED | |
| S3 | authenticated users only | | | UNVERIFIED | |
| S4 | domain restriction | | | UNVERIFIED | |
| S5 | join before host | | | UNVERIFIED | |
| S6 | mute on entry | | | UNVERIFIED | |
| S7 | screen sharing (participants) | | | UNVERIFIED | |
| S8 | chat | | | UNVERIFIED | |
| S9 | participant rename | | | UNVERIFIED | |
| S10 | participant unmute | | | UNVERIFIED | |
| S11 | participant video | | | UNVERIFIED | |
| S12 | file transfer | | | UNVERIFIED | |
| S13 | auto cloud recording | | | UNVERIFIED | |
| S14 | local recording | | | UNVERIFIED | |
| S15 | registration + approval type | | | UNVERIFIED | |
| S16 | AI Companion (summary / questions) | | | UNVERIFIED | |

Settings that are not enforceable per meeting become "advisory" in the UI.

### 2.5 Host control

| # | Item | SPEC expectation | Actual endpoint / field | Required scope(s) | Status | Fixture | Notes |
|---|---|---|---|---|---|---|---|
| H1 | Fresh `start_url` via `GET /meetings/{id}` | returned on each call | | | UNVERIFIED | | |
| H2 | `start_url` lifetime | record actual | | | UNVERIFIED | | |
| H3 | `start_url` starts meeting as host without account password | yes | | | UNVERIFIED | | |
| H4 | Read resource host key via API | user field | | | UNVERIFIED | | |
| H5 | Rotate host key via API | `PATCH /users/{id}` | | | UNVERIFIED | | |
| H6 | Claim host with host key works mid-meeting | yes | manual test | n/a | UNVERIFIED | | |
| H7 | Alternative host rules (must be licensed? same account?) | same account, licensed | | | UNVERIFIED | | |
| H8 | Behaviour if host starts a 2nd meeting while one is running | blocked / ends first | manual test | n/a | UNVERIFIED | | |

### 2.6 Recordings

| # | Item | SPEC expectation | Actual endpoint / field | Required scope(s) | Status | Fixture | Notes |
|---|---|---|---|---|---|---|---|
| R1 | List recordings for a meeting | `GET /meetings/{id}/recordings` | | | UNVERIFIED | | |
| R2 | Play / share URL usable by a ZPM redirect | | | | UNVERIFIED | | |
| R3 | Download requires token (webhook `download_token` or access token) | | | | UNVERIFIED | | |
| R4 | Cloud storage usage for account/user | endpoint exists | | | UNVERIFIED | | |
| R5 | Transcript file type present | | | | UNVERIFIED | | |

### 2.7 Webhooks

| # | Item | SPEC expectation | Actual | Status | Fixture | Notes |
|---|---|---|---|---|---|---|
| W1 | URL validation event name | `endpoint.url_validation` | | UNVERIFIED | | |
| W2 | URL validation response | `{plainToken, encryptedToken = hex(HMAC-SHA256(secret, plainToken))}` | | UNVERIFIED | | |
| W3 | Signature header + message format | `x-zm-signature = "v0=" + hex(HMAC-SHA256(secret, "v0:{ts}:{body}"))` | | UNVERIFIED | | |
| W4 | Timestamp header | `x-zm-request-timestamp` | | UNVERIFIED | | |
| W5 | Unique event identifier for dedupe | record field, or compute hash | | UNVERIFIED | | |
| W6 | Event: meeting started | | | UNVERIFIED | | |
| W7 | Event: meeting ended | | | UNVERIFIED | | |
| W8 | Event: meeting updated | | | UNVERIFIED | | |
| W9 | Event: meeting deleted | | | UNVERIFIED | | |
| W10 | Event: recording completed | | | UNVERIFIED | | |
| W11 | Event: transcript completed | | | UNVERIFIED | | |
| W12 | Event: user updated / deactivated / deleted | | | UNVERIFIED | | |
| W13 | Retry behaviour when endpoint fails | | | UNVERIFIED | | |

### 2.8 Rate limits

| # | Item | SPEC expectation | Actual | Status | Notes |
|---|---|---|---|---|---|
| L1 | HTTP 429 returned when limited | yes | | UNVERIFIED | |
| L2 | `Retry-After` or rate-limit headers present | record header names | | UNVERIFIED | |
| L3 | Rate-limit category for each endpoint used (light/medium/heavy) | record | | UNVERIFIED | |
| L4 | Plan-level per-second limits | record | | UNVERIFIED | |

### 2.9 Attendance (V1.1, verify now if possible)

| # | Item | SPEC expectation | Actual endpoint | Required scope(s) | Status | Notes |
|---|---|---|---|---|---|---|
| P1 | Past meeting participants | `GET /past_meetings/{uuid}/participants` or report API | | | UNVERIFIED | |
| P2 | Join/leave times per participant | present | | | UNVERIFIED | |
| P3 | Plan requirement for report API | record | | | UNVERIFIED | |

## 3. Final required scope list

Single source of truth defined in `config/zoom.php` and displayed in the Settings UI and Help Guide:

| Scope (exact name) | Granular Alternative | Needed for | Priority |
|---|---|---|---|
| `meeting:write:admin` | `meeting:write:meeting:admin` | Schedule pooled sessions, update meeting topics/times, apply security profiles, delete/release cancelled bookings | Required (Core) |
| `meeting:read:admin` | `meeting:read:meeting:admin` | Query meeting details, retrieve dynamic JIT host `start_url`, verify session status | Required (Core) |
| `user:read:admin` | `user:read:user:admin` | Inspect pooled host accounts, verify license types (Basic vs Licensed) and seat capacities | Required (Core) |
| `user:write:admin` | `user:update:user:admin` | Automated rotation of the 6-digit host key PIN after each meeting concludes | Required (Core) |
| `recording:read:admin` | `recording:read:recording:admin` | Index completed cloud recordings, secure playback redirects, download AI audio transcripts | Recommended |
| `report:read:admin` | `report:read:list_meeting_participants:admin` | Past meeting participant reports, attendance join/leave times, session duration (Milestone M12/V1.1) | Recommended |
| `dashboard:read:admin` | `dashboard:read:list_meeting_participants:admin` | Live meeting telemetry, latency, and active session diagnostic metrics | Optional |

### Webhook Event Subscriptions (Feature → Event Subscriptions):
- `meeting.started` - Host started pooled meeting
- `meeting.ended` - Meeting concluded; triggers host key rotation & resource release
- `meeting.updated` - Sync modifications made from Zoom client
- `meeting.deleted` - Clean up reservations cancelled on Zoom
- `recording.completed` - Cloud recording processed and ready for viewing
- `recording.transcript_completed` - Audio/video transcripts ready


| SPEC section | SPEC says | Reality | Proposed change | Owner decision |
|---|---|---|---|---|
| | | | | |

## 5. Sign-off

- [ ] Every row is VERIFIED, VERIFIED WITH CHANGES, or NOT SUPPORTED (no UNVERIFIED rows used by V1.0)
- [ ] Fixtures saved in `tests/Fixtures/Zoom/` with secrets and personal data removed
- [ ] `config/zpm-zoom-scopes.php` filled
- [ ] `docs/zoom-limitations.md` updated for every NOT SUPPORTED row
- [ ] SPEC updated for every VERIFIED WITH CHANGES row
- [ ] Owner approved section 4

Owner sign-off: ____________________  Date: __________
