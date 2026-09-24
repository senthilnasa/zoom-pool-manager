# Zoom Pool Manager (ZPM) — Final Master Development Prompt (V1.0 / V1.1)

> Save this file in the repository as `docs/SPEC.md`. Every AI coding session must read it before working.
> Work one milestone at a time (Part J). Never generate the whole application in one response.

---

## PART A — ROLE AND OPERATING RULES

Act as a senior Laravel architect, security engineer, database architect, DevOps engineer and QA engineer.

You are building **Zoom Pool Manager (ZPM)** — tagline *"Automate Your Zoom Resource Pool"* — repository `zoom-pool-manager`.
ZPM is an independent open-source project, **not affiliated with or endorsed by Zoom Video Communications, Inc.** State this in the README and footer.

ZPM lets **one organization** centrally manage a pool of licensed Zoom users and automatically allocate them to meetings. Reference deployment: a university with ~30 licensed Zoom users, ~20 managed by ZPM.

### A1. Non-negotiable rules

1. **Fully functional means no stubs.** Every feature in the current milestone must work end-to-end: migration → model → service → policy → controller → view → job → test → docs. No `TODO`, no placeholder methods, no "implement later" comments, no fake data outside demo mode and tests.
2. **Never fake Zoom behavior.** If Zoom does not expose a capability, then: identify the limitation → document it in `docs/zoom-limitations.md` → implement the documented fallback → show an admin-visible warning in the UI. Never pretend it works.
3. **Verify before you trust.** Every Zoom endpoint, field, scope, limit and webhook format in this document marked **[VERIFY]** must be checked against current official Zoom documentation and a real Zoom test account in Milestone M0. Record results in `docs/zoom-verification.md`. If reality differs from this spec, reality wins — update the spec and tell the owner.
4. **Single organization only.** Do not build multi-tenancy. No `organization_id` / `tenant_id` columns on business tables. Organization details live in settings.
5. **Business logic lives in services**, not controllers, views or models. Controllers validate (Form Requests), authorize (Policies), call a service, return a response.
6. **Everything slow or external runs in a queued job.** No Zoom API calls, bulk syncs or email sends inside a normal web request, except the explicitly allowed synchronous calls in Part F (connection test, start-meeting redirect).
7. **Small files, small commits.** Each milestone is a sequence of small, reviewable changes, each with tests.
8. **Ask the owner** only for the decisions listed in Part B. For anything else, choose the simplest secure option, record it in `docs/decisions/` (ADR format), and continue.
9. At the end of every milestone, output: what was built, how to test it manually, test results, known limitations, and files changed.

---

## PART B — OWNER DECISIONS (defaults used unless the owner changes them)

| Decision | Default |
|---|---|
| License | **Owner must choose before first public release** (AGPL-3.0, Apache-2.0 or MIT). Use `LICENSE-PENDING.md` until then. |
| Organization timezone | `Asia/Kolkata` |
| Default meeting buffer | 10 minutes (allowed range 10–60) |
| Minimum booking notice | 2 hours |
| Maximum advance booking | 90 days |
| Default allocation strategy | Least hours (today) |
| Recurring series mode | Single resource, single Zoom recurring meeting (see Part G) |
| Join-link policy on reallocation | `BLOCK_AFTER_NOTIFICATION` |
| Max concurrent meetings per resource | 1 |
| Default UI language | English |

---

## PART C — LOCKED TECHNOLOGY DECISIONS

- **PHP 8.3+**, **Laravel 12.x** (or the current stable major at project start — never a pre-release).
- **MySQL 8.0+ or MariaDB 10.6+** (10.6 is required for `SKIP LOCKED`, used by the database queue). CI must run migrations and tests against both.
- Blade + Alpine.js + Tailwind CSS. Assets are built at release time; **production never requires Node.js**.
- Laravel Scheduler, Laravel Queue with the **database** driver. Redis optional, never required.
- Pest (preferred) or PHPUnit. Docker Compose for development and container deployment.
- Forbidden as requirements: WordPress, React/Vue SPA as the main UI, Flutter, RabbitMQ, Kubernetes, Supervisor.

### C1. Approved packages (verify each is maintained and supports the chosen Laravel version before adding)

| Need | Package |
|---|---|
| Google / Microsoft login | `laravel/socialite` + `socialiteproviders/microsoft-azure` |
| SAML 2.0 | `onelogin/php-saml` (via a maintained Laravel wrapper, or a thin wrapper of our own) |
| TOTP MFA | `pragmarx/google2fa` + `bacon/bacon-qr-code` |
| Roles & permissions | `spatie/laravel-permission` |
| Recurrence rules (RRULE) | `rlanvin/php-rrule` or `simshaun/recurr` |
| ICS generation | `spatie/icalendar-generator` |
| Database backups | `spatie/laravel-backup` (DB only; see Part I) |
| OpenAPI docs | `dedoc/scramble` |
| HTML sanitizing (email templates) | `mews/purifier` or `symfony/html-sanitizer` |

Do **not** implement OAuth, SAML or TOTP cryptography by hand.

### C2. Shared-hosting realities (design for these from day one)

- Many shared hosts have **no SSH and no Composer**. The GitHub release pipeline must produce a ready-to-upload ZIP containing `vendor/` and built assets.
- Only `public/` may be in the web root. `.env` should live outside it: configure `$app->useEnvironmentPath()` in `bootstrap/app.php` to read a path from a small `zpm-paths.php` file. If the host forces everything into the web root, ship `.htaccess` deny rules and have the installer **refuse to finish** until a self-test confirms `.env`, `storage/` and `vendor/` are not reachable over HTTP.
- Cron may only run every 5–15 minutes. The scheduler runs the queue as:
  `queue:work --stop-when-empty --max-time=50` with `withoutOverlapping()`.
  Document the resulting latency.
- If no CLI cron exists: protected pseudo-cron endpoint `POST /cron/{token}` (secret token, optional IP allow-list, rate-limited, locked against overlap), meant to be triggered by an external web-cron service.
- Long-running processes, sockets and daemons are not allowed.

---

## PART D — ARCHITECTURE

### D1. Modules (folder: `app/Domain/<Module>` with Services, Actions, Models, Policies, Jobs, Events, DTOs)

Auth · Users · Roles · Departments · ZoomConnections · ZoomResources · ResourcePools · Scheduling (policies, buffer, reservations, conflicts) · Allocation · Meetings · MeetingSeries · Templates · SecurityProfiles · HostControl · Workflow · Approvals · Quotas · Waitlist · Invitees · Registration · Calendar · Recordings · Storage · Attendance · Notifications · Mail · WebhooksInbound · WebhooksOutbound · Jobs · Reconciliation · Reports · Audit · Api · Health · Settings · Branding · Backup · Maintenance · Privacy · Demo

### D2. Provider interfaces (keep them thin — no speculative methods)

```
IdentityProvider   → GoogleIdentityProvider, MicrosoftIdentityProvider, SamlIdentityProvider
MeetingProvider    → ZoomMeetingProvider            (only implementation in V1)
MailProvider       → SmtpMailProvider, GmailApiMailProvider, MicrosoftGraphMailProvider
StorageProvider    → ZoomCloudStorage (V1.0); Local, S3, R2, GoogleDrive, OneDrive (V1.1)
```

Workflow, allocation and scheduling code must depend only on these interfaces, never on Zoom classes directly.

### D3. Identity model — never confuse these

| Concept | Stored as |
|---|---|
| Requester (who submitted) | `meetings.requester_user_id` |
| Meeting owner (who it is for) | `meetings.owner_user_id` |
| Allocated Zoom resource | `meetings.zoom_resource_id` |
| Zoom host user (technical owner in Zoom) | `zoom_resources.zoom_user_id` |
| Recording logical owner | `recordings.logical_owner_user_id` |
| Approver | `meeting_approvals.approver_user_id` |

A Zoom licensed user is a **resource**, never an application user. A person may be an app user without being a resource.

### D4. Configuration precedence (higher always wins, lower can only restrict further)

```
Organization policy → Security profile → Department policy → Role policy → Template → User request
```

Implement as one `EffectivePolicyResolver` service with unit tests for every level.

---

## PART E — DATABASE

Use ULIDs as public identifiers (`public_id`), bigint auto-increment primary keys internally. Timestamps in UTC. Foreign keys and indexes on every relationship and every column used in filters. Soft deletes on meetings, series, templates, profiles, pools, resources and users. Every schema change is a Laravel migration; users never edit the schema by hand.

### E1. Tables

**Identity & access:** `users`, `departments`, `identity_providers`, `user_identities` (provider, external_id, email; unique per provider), `sessions`, spatie permission tables (`roles`, `permissions`, `model_has_roles`, …), `mfa_secrets` (encrypted), `mfa_recovery_codes` (hashed), `login_attempts`, `api_keys` (hashed key, prefix, scopes JSON, expires_at, last_used_at, revoked_at).
`users` has **no** `role` column — roles come only from spatie tables.

**Zoom:** 
- `zoom_connections` — name, account_id (enc), client_id (enc), client_secret (enc), webhook_secret_token (enc), status, granted_scopes, last_sync_at, last_success_at, last_error, last_error_at, enabled.
- `zoom_users` — connection_id, zoom_user_id, email, names, user_type, status, timezone, host_key (enc, nullable), synced_at, raw JSON.
- `zoom_resources` — zoom_user_id FK, managed, status (active/inactive/maintenance), priority, is_backup, participant_capacity, large_meeting_capacity, webinar_capacity, cloud_recording, transcript, ai_companion, max_concurrent (default 1), capabilities_checked_at.
- `resource_pools`, `resource_pool_members` (pool_id, resource_id, priority), `pool_strategy` column on pools.
- `resource_reservations` — **the occupancy source of truth**: resource_id, meeting_id (nullable), source (zpm/external/maintenance), occupied_from, occupied_until (**buffer included**), status (held/confirmed/released), hold_expires_at. Index (resource_id, occupied_from, occupied_until, status).

**Meetings:**
- `meeting_series` — owner/requester, rrule, timezone, start_date, until_date, count, series_mode, status, zoom_meeting_id (when single Zoom recurring meeting), zoom_resource_id, term_id (nullable, V2), source.
- `meetings` — **one row per occurrence** (a one-off meeting is a meeting with `series_id = null`): public_id, series_id, occurrence_index, zoom_occurrence_id, title, description, meeting_type, starts_at, ends_at, timezone, participant_count, requester_user_id, owner_user_id, department_id, template_id, security_profile_id, ai_companion_policy, recording_mode, external_participants, registration_enabled, zoom_resource_id, zoom_meeting_id, zoom_uuid, join_url, passcode (enc), link_distributed_at, status, idempotency_key (unique), source (web/api/bulk_import/instant), is_detached_from_series, cancelled_reason.
  *(There is no separate `meeting_occurrences` table.)*
- `meeting_templates`, `security_profiles` (settings JSON validated against a schema), `booking_policies`, `blackout_periods` (holiday/exam/maintenance, optional department), `terms` (V2, create empty table now).
- `meeting_invitees`, `meeting_registrations_config`, `meeting_participants` (V1.1 attendance).
- `meeting_status_history` (from, to, actor, reason, at).

**Workflow:** `workflow_rules` (priority, conditions JSON, actions JSON, enabled), `workflow_executions`, `meeting_approvals` (step, approver_user_id, delegated_from, decision, decided_at, due_at, reminder_sent_at, escalated_at), `approval_delegations`, `quotas`, `quota_usages`, `waitlist_entries`, `overrides` (actor, target, field, old, new, reason).

**Recordings:** `recordings` (meeting_id, zoom_recording_id, zoom_owner_zoom_user_id, logical_owner_user_id, storage_provider, storage_object_id, status, size, expires_at), `recording_files`, `recording_access_logs`.

**Notifications & mail:** `notifications` (Laravel database notifications), `notification_preferences`, `email_templates` (key, locale, subject, html, text, version), `email_deliveries` (recipient, template, provider, status, attempts, last_attempt_at, sent_at, error, dedupe_key unique, meeting_id, user_id), `mail_provider_settings` (enc).

**Integration & ops:** `jobs`, `job_batches`, `failed_jobs`, `webhook_events` (connection_id, zoom_event_id / computed dedupe hash unique, event_type, payload, signature_valid, status, attempts, processed_at, error), `outgoing_webhooks`, `outgoing_webhook_deliveries`, `drift_conflicts` (type, meeting_id, resource_id, details, status: open/resolved/ignored/marked_external), `audit_logs`, `system_settings` (key, value JSON, encrypted flag), `health_checks`, `alerts` (key, severity, first_seen, last_seen, count, notified_at, resolved_at), `backup_records`, `data_export_requests`, `app_version`.

### E2. Audit log immutability
The application has **no update or delete path** for `audit_logs` (model guards throw). Each row stores `hash = sha256(previous_hash + row_json)` so tampering is detectable. Only the retention job may purge, and purging is itself audited.

---

## PART F — ZOOM INTEGRATION (all details [VERIFY] in M0)

### F1. Central Zoom client
One `ZoomClient` per connection; **every** Zoom call goes through it.
- Token: `POST https://zoom.us/oauth/token` with `grant_type=account_credentials&account_id=…`, HTTP Basic `client_id:client_secret`. Cache the token per connection until `expires_in − 300s`. Refresh under `Cache::lock("zoom-token:{id}")` so concurrent jobs never double-refresh. Store the returned `scope` string in `granted_scopes`. **[VERIFY]**
- Base URL `https://api.zoom.us/v2`. **[VERIFY]**
- Retries: on HTTP 429 read `Retry-After` (or rate-limit headers), back off exponentially with jitter, max attempts configurable (default 5), never infinite. On 401 refresh the token once and retry once. On 5xx/timeouts retry with backoff. After final failure: throw a typed exception → job fails → `alerts` entry.
- Zoom has per-second and **daily per-user limits on meeting create/update** **[VERIFY]**. Track daily call counts per resource; the allocator skips resources near the limit.
- Log every call (method, path, status, duration, connection) — never tokens, secrets or passcodes.

### F2. Scope validation
Keep required scopes in `config/zpm-zoom-scopes.php`, filled from M0 verification — **never invented**. The connection test compares required vs `granted_scopes` and also performs a harmless read call per capability, then shows:
```
OAuth credentials ✓   Users API ✓   Meetings API ✓   Recordings API ✓   Webhook secret set ✓   Missing scope ✗ <name>
```

### F3. User sync & capabilities
Paginated user list (`next_page_token`), then per-user settings/features to read license type, meeting capacity, large-meeting, webinar, cloud recording, transcript and AI Companion availability **[VERIFY field names]**. Upsert into `zoom_users`; only rows marked `managed` become allocatable `zoom_resources`. Users that disappear or are downgraded raise a `drift_conflicts` entry and trigger re-validation of their future meetings.

### F4. Meeting operations
- Create: `POST /users/{zoomUserId}/meetings` — type 2 (scheduled) or 8 (recurring, fixed time). Settings come from the resolved security profile.
- Read/update/delete: `/meetings/{meetingId}`, with `occurrence_id` for single occurrences. **[VERIFY]**
- **Idempotency:** Zoom's create endpoint has no idempotency key. Put a marker `[ZPM:{meeting_public_id}]` at the end of the agenda. Before retrying a create after a timeout or unknown result, list the resource's upcoming meetings and adopt a match on marker + start time instead of creating a duplicate.
- Recurring fixed-time meetings have an occurrence limit (believed to be 50) **[VERIFY]**. Longer series are split into consecutive Zoom series, and the requester is told the link changes at the split.

### F5. Host control (M6 — the most important Zoom feature)
Goal: the owner runs a meeting hosted by pooled user `zoom07` without ever knowing its password.

1. **Start button (primary method).** "Start meeting" is shown only in ZPM to the owner, delegated co-owners and IT, from 15 minutes (configurable) before the start until the end. Clicking it re-checks authorization, calls `GET /meetings/{id}` **at click time** to get a fresh `start_url` (they expire **[VERIFY lifetime]**), audits the action, and 302-redirects. `start_url` is **never** stored in plain text, emailed, logged or sent through the API.
2. **Host key (recovery).** For late joining, or when the owner joined as a participant: ZPM shows the resource's host key to the authorized owner only during the meeting window, audits every reveal, and **rotates the host key after the meeting ends** (`PATCH /users/{id}` **[VERIFY]**). If rotation is not possible via API, document it and warn the admin.
3. **Alternative host (optional).** Only when the owner has a licensed Zoom user in the **same Zoom account** as the resource **[VERIFY Zoom rules]**. Otherwise hide the option.
4. **Co-hosts** are assigned inside the meeting by the host. ZPM documents this; it does not pretend to automate it.
5. **Join before host** is off by default. Profiles may allow it; "Exam" and "Confidential" never do.
6. **Emergency IT control:** IT can start any meeting or reveal a host key with a mandatory reason → override record + audit + notification to the owner.
7. **One meeting at a time per resource** (`max_concurrent = 1`) unless the admin raises it after verifying the account's plan.

### F6. Inbound webhooks
- Endpoint per connection: `POST /webhooks/zoom/{connection_public_id}` (CSRF-exempt, rate-limited, payload size limit).
- URL validation: for `endpoint.url_validation`, return `{plainToken, encryptedToken = hex(HMAC-SHA256(secret_token, plainToken))}`. **[VERIFY]**
- Signature: require `x-zm-signature == "v0=" + hex(HMAC-SHA256(secret_token, "v0:{x-zm-request-timestamp}:{raw_body}"))` with a constant-time comparison. Reject timestamps older than 5 minutes. **[VERIFY]**
- Flow: validate → insert `webhook_events` (unique dedupe key; duplicates return 200 and are ignored) → dispatch job → return 200 quickly.
- Events handled (names **[VERIFY]**): meeting started / ended / updated / deleted, recording completed, transcript completed, user updated / deactivated / deleted.
- Admin UI: event log, failed events, replay.

### F7. Reconciliation (drift)
A scheduled job (default hourly, plus nightly full pass) compares ZPM meetings against Zoom for each managed resource over the next 30 days. It detects changed time, host, settings or passcode, deleted meetings, meetings created outside ZPM (these become `source = external` reservations so the allocator respects them), and license or capacity changes. Each finding creates a `drift_conflicts` row plus an admin notification, with actions **Resolve (re-apply ZPM), Accept Zoom value, Ignore, Mark External**. ZPM never silently overwrites Zoom.

### F8. AI Companion
Policy values: `DISABLED | ALLOWED | REQUIRED`, part of the security profile. Enforcement is applied through meeting settings only where the API supports it **[VERIFY]**. Where it cannot be enforced, the profile shows "Advisory only — enforce in Zoom admin settings", and "Exam", "Interview" and "Confidential" show an admin warning.

---

## PART G — SCHEDULING & ALLOCATION (M4, M7)

### G1. Buffer
`meeting_buffer_minutes` defaults to 10, allowed range 10–60. A reservation occupies `[starts_at, ends_at + buffer]`. Users cannot go below the organization minimum. Admins can override per meeting through the override flow (reason required).

### G2. Allocation algorithm (must be concurrency-safe)

1. **Policy validation:** resolve the effective policy (D4), booking window, working hours, blackout periods, max duration, quota. Fail with a clear, specific reason.
2. **Capability filter:** managed, active, enabled connection, in an allowed pool; capacity ≥ participant count; large-meeting, webinar, recording, transcript and AI Companion requirements; not near its daily API limit.
3. **Availability and hold (one DB transaction):** `SELECT … FOR UPDATE` the candidate `zoom_resources` rows **ordered by id** (prevents deadlocks). Exclude resources that have a held/confirmed reservation with `existing.occupied_from < new.occupied_until AND existing.occupied_until > new.occupied_from`. Apply the pool strategy (round robin, least used, least meetings, least hours, random, preferred, manual). Insert a `held` reservation with `hold_expires_at = now + 10 min`. Commit.
4. **Provision (queued job, outside the transaction):** create the Zoom meeting with the idempotency rules in F4. On success the reservation becomes `confirmed` and the meeting `scheduled`. On failure the reservation is released and the meeting becomes `failed`, with a retry action.
5. A scheduler job releases expired holds.

Required tests: two parallel requests for the same slot never get the same resource; buffer edges (a meeting ending exactly at buffer end is allowed); DST transitions; capacity mismatch; an external reservation blocks allocation.

### G3. Conflict detection
It runs at request time (preview), before approval, and again at allocation. Types: overlap, buffer, capacity, policy, blackout, recurring occurrence, external meeting. When no resource fits, the requester sees: available alternative times, the option to join the waitlist, or "Request IT override".

### G4. Recurring series (M7)
Series modes, chosen by the requester's template and limited by policy:
- `SINGLE_RESOURCE` (**default**): find one resource free for **every** occurrence (with buffer). Create one Zoom recurring meeting, so there is **one join link**. This is what classes need.
- `SPLIT_WHEN_NEEDED`: if no single resource fits, split into the fewest consecutive segments, each on one resource. Before submitting, the requester sees exactly which dates get which link and must confirm.
- `PER_OCCURRENCE`: every occurrence is its own Zoom meeting and link. Only for irregular series. Warn the requester.
Blackout and holiday dates are skipped automatically, and the requester sees the skipped dates.

Edits support one occurrence, this and future, or the entire series. Editing or reallocating one occurrence of a single Zoom series **detaches** it (delete that occurrence in Zoom, create a standalone meeting). This changes its link, so the join-link policy applies. "This and future" ends the old series and starts a new one.

### G5. Join-link policy on reallocation
`PRESERVE_LINK` (only possible when the resource does not change), `NEW_LINK` (automatic `meeting_link_changed` email plus ICS update to every invitee and the owner), `BLOCK_AFTER_NOTIFICATION` (default: once `link_distributed_at` is set, reallocation needs an IT override with a reason). Every change is audited. Links never change silently.

### G6. Quotas, waitlist, instant meetings
- Quotas per user or department, weekly or monthly, counted as meetings or hours. Admin override with reason.
- Waitlist (V1.0 basic): FIFO. When a reservation is released or cancelled, the scheduler retries allocation for matching entries. Configurable auto-allocate vs notify-only. Entries expire.
- Instant meetings: start within 15 minutes. Policy decides auto-approve, IT approval or role restriction. The emergency pool is used first.

---

## PART H — FUNCTIONAL SPECIFICATION BY MODULE

### H1. Installer (M1)
Web installer steps: welcome → requirements (PHP version, extensions, writable dirs, HTTPS, web-root exposure self-test) → database config and connection test → migrations → first Super Admin (local, **MFA enrollment required**) → organization name and timezone → email → Zoom connection (optional, skippable) → finish. The installer is then locked (flag file plus DB flag; re-running requires CLI `php artisan zpm:installer:unlock`). Upgrades run `zpm:upgrade`: pre-upgrade DB backup → maintenance mode → migrations → cache rebuild → version record. It is also available as an admin button that shows the backup result first.

### H2. Authentication (M2)
- Google OIDC, Microsoft Entra ID, generic SAML 2.0 (SP metadata download, IdP metadata upload/URL, certificate rotation with two active certs, signed assertions required). Local login for break-glass accounts and environments without SSO.
- Allowed email domains; SSO-only mode (local login hidden, reachable at `/login/local` for break-glass).
- JIT provisioning on first SSO login. Group → role and group/attribute → department mapping, re-evaluated at each login. Users removed from the IdP are deactivated on their next failed login; periodic directory sync is V2.
- Sessions: database driver, regenerate on login, idle and absolute timeout, "log out all sessions" for users, and admin force-logout of any user.
- Rate limiting and lockout (per account and per IP), failed-login tracking, login history.
- **Break-glass:** local Super Admin accounts **must** have TOTP. It cannot be disabled in the UI, and password reset is only possible via CLI `php artisan zpm:admin:reset {email}`. Normal local users may use emailed password reset if the admin enables it.
- TOTP: RFC 6238, QR enrollment, 10 single-use recovery codes (hashed), admin MFA reset (audited, reason required), Argon2id password hashing.

### H3. Roles & permissions (M2)
Roles: Super Administrator, IT Administrator, Meeting Administrator, Approver, Department Administrator, Faculty, Staff, Viewer/Auditor, API Client.
Permissions: `meeting.{create,view,view_any,edit,cancel,approve,allocate,override,reschedule,book_on_behalf,start_as_host}`, `recording.{view,view_any,manage,download,share}`, `zoom.{view,manage}`, `resource.{view,manage}`, `pool.manage`, `template.manage`, `security_profile.manage`, `workflow.{view,manage}`, `quota.manage`, `user.{view,manage}`, `settings.{view,manage}`, `audit.view`, `backup.manage`, `api.manage`, `health.view`, `emergency.use`, `privacy.manage`.
Department Administrators are scoped to their own department in every policy. Produce the full permission matrix in `docs/permissions.md`.

### H4. Zoom connections, resources, pools (M3)
Connection CRUD, enable/disable, test (F2), credential rotation (the new secret is tested before the old one is replaced), "remove safely" (blocked while future meetings exist unless they are migrated or cancelled). Resource list and detail pages with capabilities, pool membership, status, today's timeline (meetings plus buffer blocks), utilization, last sync. Pools with strategy, priority members, and backup and emergency flags.

### H5. Meeting requests, templates, security profiles (M5)
- Request form fields: title, description, type, date/time, duration, timezone, participant count, internal/external, recording, transcript, security profile, AI Companion policy, template, preferred pool, department, invitees, registration, recurrence, notes, "book on behalf of" (permission-gated).
- A live availability preview (conflict check) before submit.
- Default templates: Faculty Class, Faculty Meeting, Student Meeting, Interview, Exam, Training, External Event, Confidential, Webinar. Each defines profile, recording, AI Companion, participant limit, duration limits, approval requirement, pool, registration, external participants and series mode. Admins can create more.
- Default security profiles: Standard, Internal, Confidential, Exam, Interview, External Event, Public Webinar, plus custom. Settings: passcode, waiting room, authenticated users only, domain restriction, join before host, mute on entry, screen sharing, chat, participant rename, unmute, video, file transfer, cloud/local recording, registration, AI Companion. **Each setting is mapped to a verified Zoom API field.** Unsupported settings are shown as "advisory".
- Organization-enforced settings are locked in the UI and ignored in API input.

### H6. Workflow & approvals (M8)
- Rules are evaluated in priority order. Conditions: requester, role, department, duration, participant count, meeting type, recording, external participants, security profile, working hours, pool, template, instant. Actions: auto-approve, require approval (manager / department admin / IT / named users / role; ANY or ALL; multi-step chain), reject with message, assign pool, assign profile, enable recording, notify.
- Visual rule builder (Alpine form, no drag-and-drop required) plus a "test this rule against a sample request" button.
- **Nobody approves their own request.** Approvers see conflicts before deciding.
- Reminders, escalation after timeout, delegation (date range), expiry (auto-reject or escalate, configurable), full execution log.
- Sensitive changes (time, participant count above threshold, security profile, recording, external participants) trigger re-approval.

### H7. Meeting lifecycle (M5)
Statuses: `draft → pending_approval → approved → allocating → scheduled → started → ended → recording_processing → completed`, plus `cancelled`, `rejected`, `failed`, `waitlisted`. Transitions are enforced by one state-machine service. Every transition is written to `meeting_status_history`. Actions: edit, reschedule, cancel, reallocate, re-approve, clone, retry failed provisioning.

### H8. Pages
- **Dashboard:** today's meetings, pending approvals (for approvers), available and in-use resources, upcoming meetings, open conflicts, recording status, failed jobs and health (for admins).
- **My Meetings:** upcoming, past, pending, cancelled, recurring. Actions: start (F5), edit, reschedule, cancel, copy link, add to calendar (.ics), view recording.
- **Meeting details:** everything in D3 plus join link, host controls, profile, recording, approvals, invitees, status history, audit trail.
- **Calendar view:** day, week and month. Admins see a per-resource timeline. Users see only authorized meetings.
- **Resource view:** as in H4.
- **Search & bulk:** filter meetings, users, resources, recordings, approvals, jobs and audit logs. Bulk actions: enable/disable resources, assign pool, retry jobs, cancel meetings (with confirmation and reason), sync users.

### H9. Invitees, ICS, registration (M9)
Internal invitees (user picker) and external invitees (email). Invitations, updates and cancellations are sent automatically with an ICS attachment (stable `UID` per meeting, `SEQUENCE` incremented on change, `METHOD:CANCEL` on cancel). Registration settings are passed to Zoom where supported. Registrant sync is V1.1.

### H10. Email & notifications (M9)
- MailProvider: SMTP (host, port, encryption, auth, timeout, from, reply-to), Gmail API and Microsoft Graph `sendMail` (OAuth app credentials, encrypted). Buttons: test connection, test auth, send test email.
- DB-backed templates, HTML and text versions, per locale, versioned. Variables use a whitelist (`{{meeting.title}}` etc.), rendered through a safe renderer (no Blade/PHP execution) and escaped. Admin HTML is sanitized. Preview with sample data.
- Template keys: request_submitted, auto_approved, approval_required, approved, rejected, cancelled, rescheduled, updated, start_reminder, link_changed, host_key_revealed, recording_available, recording_processing, recording_failed, recording_expiring, waitlist_available, quota_exceeded, resource_unavailable, zoom_connection_failed, cron_failure, webhook_failure, job_failure, drift_detected, password_reset, admin_invitation, mfa_enabled, mfa_reset.
- All mail is queued. Dedupe key = event id + recipient + template (unique), so retries never double-send. Exponential backoff, failed status, manual retry. `email_deliveries` log never stores credentials or full auth headers.
- From address is limited to configured sender identities. Document SPF, DKIM and DMARC.
- In-app notifications (bell, unread count, mark read). User preferences per event type. Security and administrative notifications cannot be disabled.
- Emails never contain `start_url` or host keys. Passcodes appear only if the profile allows it.

### H11. Recordings foundation (M10)
On a recording-completed webhook or during recording sync: store metadata and files list, map to meeting → `logical_owner_user_id = meetings.owner_user_id`. My Recordings lists only recordings the user logically owns or is explicitly granted. Using the same pooled resource grants nothing. Viewing uses Zoom share/play URLs through an authorized ZPM redirect, and every access is audited. Cloud storage usage monitoring with a warning threshold **[VERIFY endpoint]**. Recording consent: an organization-level notice text shown in the request form and invitations for recorded meetings, and a privacy-notice link.

### H12. Operations (M11)
- `/admin/health`: DB, each Zoom connection, OAuth, cron (last run), queue (oldest pending job age), webhooks (last received per connection), email (last success/failure), storage, disk space. Status: healthy / warning / failed.
- Alerts: cron inactive > 2 × interval, webhook silence (configurable), repeated Zoom errors, OAuth failure, failed-job spikes, email failures, storage issues. Thresholds, dedupe by alert key, at most one email per alert per hour, recovery email when resolved.
- Jobs monitor: pending, running, failed, retrying. Inspect error, retry, cancel where safe.
- Diagnostics buttons: test DB, Zoom, OAuth, email, webhook (self-signed test event), queue, storage, run sync, run health check.
- Maintenance mode: message, start and expected end. Admins bypass it. Webhook intake, queue and scheduler **keep running** to avoid drift.
- Emergency panel (`emergency.use`): force allocation, change resource, bypass quota / approval / buffer, cancel, recreate meeting, recover host access. A reason is mandatory, an `overrides` row is written, it is audited, and the owner is notified.
- Backups: scheduled and manual DB backup, stored outside the web root, optional encryption (zip password / key). Backup history, retention, verification (the archive opens and the dump is non-empty). Secrets in settings are stored encrypted, so they stay encrypted in backups. Document that restoring requires the same `APP_KEY`. Restore is by documented CLI procedure only.
- Data retention: configurable per table (audit logs, webhook events, jobs, notifications, email logs, meeting history, recording metadata). Nothing is purged unless a policy is set. Purges are audited.
- Privacy: privacy-notice URL, per-user data export (JSON/ZIP), anonymization workflow (replaces personal fields, keeps audit integrity), deletion requests log. Documentation says ZPM provides controls to support DPDP/GDPR programmes, **not** that it makes an organization compliant.

### H13. API & outgoing webhooks (M12)
- REST under `/api/v1`, authenticated by scoped API keys (Bearer). Keys are shown once and stored hashed, with expiry, revocation, per-key rate limits and an audit of every write.
- Endpoints: meetings (list, create, get, update, cancel, occurrences), availability check, resources (read), templates and profiles (read), recordings (read, authorized), users (read), reports (summary). `Idempotency-Key` header is supported on POST. The API follows the same policies, workflow and quotas as the UI.
- OpenAPI via Scramble at `/docs/api` (admin-only by default).
- Outgoing webhooks: events `meeting.created|approved|rejected|cancelled|rescheduled|started|ended`, `recording.available|failed`, `resource.allocated`, `workflow.completed`. Headers: `X-ZPM-Signature` (HMAC-SHA256 of `timestamp.body`), `X-ZPM-Timestamp`, `X-ZPM-Event-Id`. Retry with backoff, delivery log, replay, disable endpoint, secret rotation with a grace period.

### H14. Reports (V1.0)
Meetings count (day/week/month), meeting hours, per-resource utilization (meetings, hours, % of working hours), peak hours heatmap, approvals (auto vs manual, average approval time, rejection rate), failed allocations and reasons, waitlist count, Zoom API error rate. CSV export.

### H15. UI, accessibility, i18n
Responsive (mobile, tablet, desktop), semantic HTML, labelled form controls, keyboard navigation, visible focus, WCAG AA contrast, `aria-live` for status messages, loading / empty / error states, dark mode. All strings go through `__()` with translation files. English ships in V1. Tamil and Hindi are added by translation files only. Branding (name, logo, favicon, primary color, support email, website, email footer, privacy URL) applies to the app, login page, emails and maintenance page.

### H16. Demo mode
`ZPM_DEMO=true`: seeded fake users, 20 fake resources across 2 fake connections, templates, workflows, a month of meetings and reports. A `FakeMeetingProvider` replaces Zoom, mail goes to the log only, a permanent "DEMO MODE" banner is shown, and the dataset resets nightly. Real Zoom and mail providers cannot be enabled while demo mode is on.

---

## PART I — SECURITY, OBSERVABILITY, QUALITY

- **Security:** CSRF (except the signed webhook and cron endpoints), output escaping, Form Request validation everywhere, Policies on every route, Eloquent/bound parameters only, secure + HttpOnly + SameSite=Lax cookies, HTTPS enforced, HSTS (configurable), CSP (no inline scripts; Alpine CSP build), X-Frame-Options/`frame-ancestors`, Referrer-Policy, Permissions-Policy, session rotation, signed URLs for sensitive links, encrypted casts for every secret, `APP_PREVIOUS_KEYS` documented for key rotation, uploads (logo/favicon/SAML metadata only) validated by MIME + extension + size, random names, stored outside the web root, SVG sanitized or refused.
- **Errors:** users see friendly messages with a reference id. Stack traces, SQL, paths and secrets appear only in logs, never in responses, when `APP_DEBUG=false`. The installer refuses to finish with `APP_DEBUG=true` in production.
- **Logging:** structured JSON logs with channels AUTH, ZOOM, MEETING, ALLOCATION, WORKFLOW, EMAIL, WEBHOOK, RECORDING, JOB, SECURITY, API, SYSTEM. Default level INFO. Tokens, secrets, passcodes, host keys and start URLs are redacted by a log processor (unit-tested).
- **Performance:** paginate everything, eager-load relations, index filter columns. Morning spikes, bulk syncs and webhook bursts go through jobs. Target: dashboard < 500 ms with 10k meetings on modest shared hosting.
- **Code quality:** PSR-12 (Laravel Pint), PHPStan/Larastan level 6+, strict types, typed properties and return types, DTOs for service inputs. No over-engineering: no repositories unless a query is reused in 3+ places.

### I1. Testing
- **Unit:** EffectivePolicyResolver, buffer maths, overlap detection, every allocation strategy, quota counting, recurrence expansion incl. DST and blackout skipping, series splitting, workflow rule evaluation, token caching and refresh locking, retry/backoff, webhook signature verification, ICS output, template variable escaping, log redaction.
- **Feature:** installer, local login + MFA, SSO callbacks (mocked IdPs), SAML response handling (fixture assertions), RBAC on every route, request → auto-approve → allocate → provision, manual approval chain ANY/ALL, self-approval blocked, cancel/reschedule/reallocate with link policy, recurring edit one / future / all, start-meeting redirect authorization, host-key reveal and audit, emergency override, email queue + dedupe, in-app notifications, inbound webhook (valid, invalid signature, stale timestamp, duplicate, URL validation), drift detection, API keys + scopes + idempotency, outgoing webhook signing.
- **Integration (Zoom mocked with `Http::fake` and recorded fixtures from M0):** 429 with Retry-After, timeouts, 401 → refresh, expired token, malformed JSON, create-timeout-then-adopt (idempotency), delayed and out-of-order webhooks.
- **Concurrency:** a test that runs two allocation attempts in separate DB connections for the same slot and asserts one resource is used once.
- **CI (GitHub Actions):** Pint, Larastan, tests on PHP 8.3 and 8.4 × MySQL 8 and MariaDB 10.6, `composer audit`, and a release job that builds the shared-hosting ZIP.
- Automated tests never use real Zoom credentials. Real-account checks live only in the M0 spike scripts, which read credentials from a local, git-ignored file.

---

## PART J — BUILD PLAN (milestones)

**Definition of done for every milestone:** migrations run clean on MySQL 8 and MariaDB 10.6; all tests pass; Pint and Larastan are clean; no stubs or TODOs in the delivered scope; demo seeder updated; docs updated; manual test checklist written in `docs/testing/Mx.md`; summary delivered (A1 rule 9).

| # | Milestone | Delivers |
|---|---|---|
| **M0** | **Zoom verification spike** (no app code) | Scripts against a real test Zoom account: S2S token + scopes, user list + capabilities, create/update/delete on a pooled user, type 8 recurring + occurrence ops + max occurrences, fresh `start_url` + expiry, host key read/rotate, meeting settings for AI Companion, recording endpoints, webhook URL validation + signature, rate-limit headers, daily limits. Output `docs/zoom-verification.md`, fixtures for tests, `config/zpm-zoom-scopes.php`, and a list of spec changes for the owner. |
| M0.5 | Design documents | Architecture doc, module dependency map, ERD, table definitions, permission matrix, provider interfaces, queue/notification/security/deployment architecture, V1.0/V1.1 matrix, API outline, test strategy. Owner reviews before M1. |
| M1 | Foundation | Laravel skeleton, env-outside-webroot, installer, settings, branding, layouts, i18n, security headers, error pages, logging + redaction, health endpoint stub → real in M11, Docker, CI, release ZIP. |
| M2 | Auth & RBAC | Local + MFA + break-glass CLI, Google, Microsoft, SAML, provisioning, mappings, sessions, roles/permissions, audit log core. |
| M3 | Zoom connections & resources | ZoomClient, tokens, rate limits, scope test, user sync, resources, pools, resource pages. |
| M4 | Scheduling core | Booking policies, blackouts, buffer, reservations, conflict detection, allocator + all strategies, concurrency tests. |
| M5 | Meetings | Requests, templates, security profiles, AI Companion policy, book on behalf, provisioning job + idempotency, lifecycle state machine, My Meetings, details page, calendar view. |
| M6 | Host control | Start button, host key reveal + rotation, alternative host (if verified), emergency host recovery. |
| M7 | Recurring series | Three series modes, splitting, detach, edit scopes, blackout skipping. |
| M8 | Workflow | Rules, builder, approvals, chains, delegation, reminders, escalation, re-approval, quotas, instant meetings, basic waitlist, overrides. |
| M9 | Communication | Mail providers, templates, queue, dedupe, logs, preferences, in-app notifications, invitees, ICS, registration config. |
| M10 | Webhooks, drift & recordings | Inbound webhooks, reconciliation, drift UI, recording foundation, My Recordings, consent notice, storage quota warning. |
| M11 | Operations | Health dashboard, alerts, job monitor, diagnostics, maintenance mode, emergency panel, backups, retention, privacy tools, reports. |
| M12 | API | API keys, REST endpoints, OpenAPI, outgoing webhooks. |
| M13 | Release hardening | Security review checklist, accessibility pass, performance pass, demo mode, full documentation set, v1.0.0 release. |

---

## PART K — V1.1 SCOPE (build after v1.0.0; schema already supports it)

Recording download, sharing, permissions, transcripts, search · storage providers (Local, S3, R2, Google Drive, OneDrive) with archive-before-expiry, retention and expiry warnings · attendance (participants, join/leave, duration, reports, CSV, API) · Google Calendar and Microsoft Graph calendar sync (never required for booking) · advanced reports (attendance, department, user, recording, quota, trends, Excel export) · advanced waitlist (priority, auto-allocation rules) · daily admin digest · registrant sync · notification channel architecture for Slack/Teams/WhatsApp.

## PART L — V2 BACKLOG (do not build; keep schema-ready)

Bulk timetable (CSV) import with batch allocation (`meetings.source = bulk_import`) · academic calendar / terms ("until end of semester", `terms` table) · user offboarding and ownership transfer UI · directory sync or SCIM deactivation · compromised-link response (regenerate passcode/link, notify invitees) · webinar panelists, practice sessions and Q&A · scheduled emailed reports · Slack/Teams/WhatsApp providers · OneERP/LMS/HR integrations · additional meeting providers (Teams, Google Meet).

---

## PART M — DOCUMENTATION DELIVERABLES

`README.md`, `INSTALL.md` (shared hosting: cPanel, DirectAdmin, Plesk; VPS: Nginx + PHP-FPM; Docker), `UPGRADE.md`, `ARCHITECTURE.md`, `API.md`, `SECURITY.md`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md`, `CHANGELOG.md`, `LICENSE` (after the owner's decision), plus guides: Zoom app setup (exact verified scopes, webhook events, secret token), Google SSO, Microsoft SSO, SAML, MFA and break-glass, email (SMTP/Gmail/Graph + SPF/DKIM/DMARC), cron and pseudo-cron, backups and restore, key rotation, troubleshooting, FAQ, `docs/zoom-limitations.md`.

---

## PART N — V1.0 ACCEPTANCE CHECKLIST

V1.0 ships only when each item below is demonstrated on **both** a shared-hosting install (from the release ZIP) and a Docker install:

Install & upgrade · env outside web root / exposure self-test · Google, Microsoft and SAML login · local break-glass with mandatory TOTP · RBAC + department scoping · multiple Zoom connections with scope test · token caching + rate-limit handling · user sync + capabilities · pools + all strategies · concurrency-safe allocation · buffer 10–60 · booking policies + blackouts · conflict preview · templates + security profiles + AI Companion policy (with advisory flags) · book on behalf · lifecycle state machine · recurring series (all three modes, detach, edit scopes) · host control (start button, host-key reveal + rotation, emergency recovery) · join-link policy · auto/manual/chain approvals, no self-approval, reminders, escalation, delegation · quotas · basic waitlist · instant meetings · ICS + invitees · registration config · SMTP/Gmail/Graph mail with templates, queue, dedupe, logs · notification preferences + in-app notifications · inbound webhooks (validation, signature, idempotency, replay) · reconciliation + drift UI · recording ownership + My Recordings foundation · health dashboard + alerts · job monitor + diagnostics · maintenance mode · emergency panel · immutable audit log · API keys + REST + OpenAPI + outgoing webhooks · backups · retention + privacy tools · reports + CSV · demo mode · accessibility pass · i18n-ready · security hardening · full test suite green in CI · complete documentation.
