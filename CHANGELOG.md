# Changelog

All notable changes to **Zoom Pool Manager (ZPM)** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.20] - 2026-10-05

### Added & Fixed
- **Google Sign-In & SSO Authorized Redirect URI Documentation & UI:**
  - Added comprehensive step-by-step setup guides for Google Workspace (OAuth 2.0 / OIDC) and Microsoft Entra ID in `docs/admin/sso.md`, detailing exact Authorized Redirect URIs, JavaScript Origins, and required OAuth scopes.
  - Added cross-reference note in `docs/zoom/oauth-setup.md` clarifying the distinction between Zoom API Server-to-Server OAuth and User Sign-In (Google/Microsoft SSO) with immediate links to the redirect URI.
  - Added Google Error 400 (`redirect_uri_mismatch`) diagnosis to `docs/troubleshooting/common-issues.md`.
  - Added 1-click copyable **Authorized Redirect URI** helper boxes directly inside the Identity Provider configuration modal in `SsoSettingsPage.vue` (`https://<domain>/auth/google/callback` and `https://<domain>/auth/microsoft/callback`).
  - Updated `SsoController` and OAuth drivers (`GoogleIdentityProvider`, `MicrosoftIdentityProvider`) to dynamically support both clean friendly provider routes (`/auth/google/callback`) and instance-specific ULID routes (`/auth/{public_id}/callback`).

---

## [1.0.19] - 2026-10-05

### Fixed
- **Comprehensive Modal Scrolling & Overlay Polish:**
  - Added universal `v-scroll-lock` and `overflow-y-auto` to all remaining modal containers across every module (Quotas, Pools, Recordings, Workflows, Blackout Periods, Booking Policies, General Settings, Scheduled Jobs, User Profiles, API Keys, Webhooks, Audit Logs, Delegations, Departments, Drift Conflicts, Approvals, Email Templates, Mail Settings, Emergency Actions, and Privacy Compliance).
  - Enhanced `useBodyScrollLock` composable to lock both `document.body` and `document.documentElement` simultaneously while tracking reactive binding lifecycle (`updated` hook).
  - Removed accidental `overflow-y-auto` from the root container of `AppLayout.vue` to ensure page scroll is managed cleanly at the window level, preventing rogue scrollbars and blue tracking slivers beside modals.
  - Added responsive vertical margin (`my-8`) to modal cards to ensure comfortable scrolling on laptop and tablet viewports without clipping.

---

## [1.0.18] - 2026-10-05

### Fixed
- **Modal Popups & Mobile Navigation Drawer Layout Break Fix:**
  - Resolved UI layout break, page jump, and visual clipping when opening any modal popup (e.g. Identity Providers, Directory Sync, Users, Workflows, Pools, etc.) or the Mobile Navigation drawer across the application.
  - **Root Causes:**
    1. Modal overlays were rendered inside page route containers (`<main>`) rather than teleported to document `<body>`. Because `<Header>` has `sticky top-0 z-20`, modals nested inside `<main>` could not cover `<Header>`, causing the top header to poke through above the backdrop as an un-dimmed white strip.
    2. Modals and the mobile drawer did not lock body scroll, allowing background page scrolling while open.
    3. Missing scrollbar width compensation on modal open caused Windows browser scrollbars to collapse, triggering a 15–17px layout jump across the entire page.
    4. At 1024px screen widths (tablets or resized windows), the Header expanded its search input to full width (`md:flex`), crowding out the title and ThemeSelector in the remaining 768px workspace and causing horizontal overflow.
  - **Fixes Applied:**
    - Created universal `useBodyScrollLock` composable and registered `v-scroll-lock` Vue directive globally.
    - Systematically wrapped all modal overlays across all 26 Vue page components in `<Teleport to="body">` with `v-scroll-lock`.
    - Teleported Mobile Sidebar Drawer in `AppLayout.vue` to `body` with `v-scroll-lock` and solid background (`bg-white dark:bg-slate-900 shadow-2xl`).
    - Adjusted `Header.vue` responsive search bar to `xl:flex` (reserving icon trigger button for `< xl`), preventing header overflow on tablet/1024px widths.
    - Added `overflow-x: hidden; max-width: 100vw;` to `html, body` in `app.css` to prevent horizontal page shifting.

---

## [1.0.17] - 2026-10-05

### Fixed
- **Version Detection & Status Discrepancy Fix:**
  - Resolved an issue where the System Updates page persistently reported `v1.0.12` and "New update available" even after successfully updating and deploying newer builds.
  - **Root Cause:** `AppUpdateService::getCurrentVersion()` previously checked `config('zpm.version')` before `version.json`. Because `config/zpm.php` had a hardcoded default `'1.0.12'`, cached configuration always returned `1.0.12`, preventing `version.json` from ever being evaluated.
  - Re-ordered `getCurrentVersion()` to prioritize `version.json` as the single authoritative source of truth.
  - Updated `config/zpm.php` to dynamically read from `version.json` when building the configuration cache.
  - Updated `SpaController`, `SpaAuthController`, `SpaDashboardController`, and `base.blade.php` to resolve the live application version directly from `AppUpdateService::getCurrentVersion()`.
  - Fixed `setStep()` condition so `is_active` remains true across all steps including verification.

---

## [1.0.16] - 2026-10-05

### Fixed
- **System Updates Engine — Performance & Stalling Protection:**
  - Replaced the slow, per-file PHP stream extraction loop (which looped 9,600+ files with `stream_get_contents` and hit PHP's 30s timeout) with native `ZipArchive::extractTo()`, reducing archive extraction time from 60+ seconds to ~5 seconds.
  - Set execution safety limits (`set_time_limit(600)`, `ignore_user_abort(true)`, `ini_set('memory_limit', '512M')`) at the start of `applyUpdate()` to prevent server timeouts during large dependency extraction.
  - Added dedicated `/spa/settings/updates/reset` endpoint and `AppUpdateService::resetProgress()` method to instantly clear update locks, reset progress cache, and bring the application out of maintenance mode.
- **System Updates UI — Stalling Protection & Recovery Controls:**
  - Added an active update lock warning banner on the System Updates overview page with an **"Unlock & Reset State"** button so administrators are never locked out by stale lock files.
  - Added a **"Stuck? Force Reset Lock"** escape hatch inside the update progress dialog so updates can be safely cancelled, reset, and retried at any time without waiting 30 minutes for lock expiration.
  - Added auto-reconnection in `loadCurrentStatus()` so if a user refreshes their browser during an active update, the UI automatically re-attaches to the live progress feed.

---

## [1.0.15] - 2026-10-05

### Fixed
- **System Updates & Releases — Rich Markdown Changelog Rendering:**
  - Resolved changelog notes displaying as raw monospace unformatted text (`###`, `**bold**`, backtick code blocks) on the System Updates overview page.
  - Rendered release notes with GitHub Flavored Markdown parser (`marked.parse`) and styled headings (`h1`–`h4`), clean bullet lists with outside indentation, inline code badges, code blocks, and horizontal dividers in `resources/css/app.css`.
- **CI / Build Pipeline & Code Quality:**
  - Fixed Laravel Pint code style compliance issues in `DirectorySyncService.php` (aligned array arrows and control structure blank-line spacing) that caused GitHub Actions CI and release packaging pipelines to fail.

---

## [1.0.14] - 2026-10-05

### Fixed
- **Global Search Modal — UI Layout Break / Page Jump on Open:**
  - Resolved page content shifting left (≈15–17 px on Windows) when the search modal opened, which made the table, header, and sidebar appear misaligned.
  - **Root cause:** Setting `document.body.style.overflow = 'hidden'` to lock scroll removes the browser scrollbar, collapsing the viewport width and causing the page to re-layout.
  - `GlobalSearchModal.vue`: Before locking body scroll, the scrollbar width is now measured with `window.innerWidth - document.documentElement.clientWidth` and applied as `document.body.style.paddingRight` to compensate exactly for the lost scrollbar space.
  - `GlobalSearchModal.vue`: Both `overflow` and `paddingRight` are cleaned up on modal close and on component `unmount` to prevent any residual style leakage.

---

## [1.0.13] - 2026-10-05

### Fixed
- **Google Directory Sync — Duplicate Entry Crash (`SQLSTATE[23000]` / error 1062):**
  - Resolved `Synchronization failed: Integrity constraint violation: 1062 Duplicate entry '...' for key 'users_email_unique'` errors that occurred during Google Workspace directory sync.
  - **Root cause 1 — Duplicate rows in directory feed:** Google Directory API can return the same account more than once (e.g. shared mailboxes, aliased accounts). The sync loop had no guard against processing the same email twice in a single run, causing a second `INSERT` on an already-created row.
  - **Root cause 2 — Race condition:** The classic check-then-act pattern (`SELECT` → `INSERT`) is not atomic; a concurrent sync job could insert the row between the two operations.
  - `DirectorySyncService.php`: Added `$processedEmails` hash map — any email already processed in the current run is skipped immediately, preventing double-inserts from duplicate directory entries.
  - `DirectorySyncService.php`: Wrapped `User::create()` in a `try/catch (QueryException)` that specifically handles MySQL/MariaDB error code `1062`. On a duplicate-key catch the service re-fetches the existing user and continues with the normal field-update logic instead of throwing.
  - Added `use Illuminate\Database\QueryException` import.

---

## [1.0.10] - 2026-09-29

### Fixed
- **Workflow Rules Builder (`/app/workflows`):**
  - Registered dedicated authenticated SPA endpoints (`/spa/workflows`, `/spa/workflows/{publicId}`, `/spa/workflows/{publicId}/toggle`, `/spa/workflows/simulate`) to resolve routing conflicts and ensure consistent JSON payloads.
  - Resolved 422 simulation validation failures in `WorkflowRuleController::simulate` by introducing flexible fallback defaults for `starts_at` and `ends_at` derived from `duration_minutes`.
  - Modernized `WorkflowsPage.vue` with condition badges (Meeting Type, Duration Min/Max, Department, Roles, Participants), action pills (Auto-Approve, Require Approval, Reject, Resource Pool, Recording Mode), active/disabled toggle switch, full rule editing modal, interactive execution log accordion in simulator, and status toasts.
- **Quota Management Zero-Crash & UI Protection (`/app/quotas`):**
  - Resolved fatal JavaScript `TypeError: Cannot read properties of undefined (reading 'id')` when rendering quota lists.
  - Implemented defensive safe property extractors (`getQuotaModel`, `getQuotaStats`, `getScopeType`, `getTargetName`, `getMeetingsPct`, `getHoursPct`, `getId`) in `QuotasPage.vue` guaranteeing rock-solid rendering regardless of payload nesting.
  - Guarded all percentage progress bar calculations against division by zero, preventing `NaN` style bindings.
  - Added dedicated `/spa/quotas` endpoint and `/spa/quotas/{publicId}/toggle` endpoint allowing administrators to activate/deactivate quota policies directly from the table.
  - Modernized UI with summary cards (Current Period, Total Quotas, Active Policies, Department Limits), scope badges, and responsive action controls.
- **Approval Delegations Management (`/app/delegations`):**
  - Standardized routing to `/spa/delegations` with CSRF protection and clean JSON responses.
  - Fixed active/expired validity detection to evaluate both `is_active` and time windows (`ends_at >= now()`), correctly tagging expired authority transfers.
  - Added visual summary cards, colleague selector with remote search, and 1-click navigation to review pending delegated requests in `/app/approvals`.
- **Documentation Direct Launch & Navigation (`Sidebar.vue` & `DocumentationPage.vue`):**
  - Removed embedded iframe documentation and renamed external GitBook link to **Documentation** in sidebar navigation.
  - Configured documentation to launch directly in a new tab to avoid iframe security blocks (`X-Frame-Options` / CSP) and provide full-screen reading experience.
  - Redesigned `DocumentationPage.vue` with direct launch hero banner and 8 topic quick launch cards.
- **Code Style & CI Build Compliance:**
  - Standardized Laravel Pint code style across controllers and tests, resolving CI build failure on GitHub Actions.
- **Test Coverage:**
  - Added `SpaGovernanceAndLimitsTest` covering full CRUD, toggling, and simulation workflows across all three modules with 100% test pass rate (264/264 tests passing).

---

## [1.0.9] - 2026-09-29

### Fixed
- **Zoom Host Key PIN Shared in Booking Emails & Invitations:**
  - Resolved issue where Host Key PIN was masked with a placeholder even when "Share Host Key" was enabled.
  - `TemplateRenderer.php`: Updated `$vars['meeting.host_key']` to expose the actual 6-digit Host Key PIN when `share_host_key` is true or when the recipient is the meeting requester/organizer.
  - `MailDeliveryService.php`: Added the Host Key PIN box in default HTML and text booking confirmation emails with claim instructions (`In Zoom client > Participants > Claim Host`).
  - Fixed duplicate footer string in default email templates (`This email was sent automatically by {{org.name}}.`).
- **Auto-Start (Join Before Host) & Waiting Room Compatibility:**
  - Resolved Zoom API setting conflict where attendees were trapped in "Waiting for host to start the meeting" despite Auto-Start being enabled, caused by Zoom's restriction that Waiting Room overrides Join Before Host.
  - `MeetingLifecycleService.php`: Ensured Zoom API payload dynamically passes `waiting_room: false` whenever `join_before_host` is enabled in both creation and patch requests.
  - `MeetingCreatePage.vue`, `CalendarPage.vue`, and `MeetingsListPage.vue`: Implemented reactive mutual exclusion and informative status badges between Waiting Room and Join Before Host.
- **User Profile Directory Routing & Type Coercion Fix:**
  - `SpaAdminController.php`: Resolved MySQL/MariaDB type coercion bug in `userProfile` lookup where string ULID public IDs starting with `01...` coerced to integer `1`, causing every user click in the directory (e.g. Balaji Damodaran) to load the Super Administrator profile (ID 1).
  - `UserProfilePage.vue`: Added dynamic route watcher on `route.params.id` to refetch user data on navigation between profile pages.
  - Visual Polish: Modernized Profile View UI with responsive action buttons, stat card styling, elevated badges, and clear empty states.
- **Workflow Approvals Page Actions & Interactive Decision Modal (`/app/approvals`):**
  - `ApprovalsPage.vue`: Fixed approval status evaluation (`a.decision` vs `a.status`) which prevented Approve/Reject action buttons from rendering on pending requests.
  - Replaced browser `prompt()` with an interactive native confirmation modal supporting optional decision notes.
  - Added filter tabs ("Pending Review", "All Requests", "Approved", "Rejected") with live pending counters and search filtering.
  - `SpaDataController.php` & `routes/web.php`: Added `/spa/approvals/{publicId}/decide` endpoint and expanded admin role authorization to support all administrator role variations (`Super Administrator`, `Administrator`, `super_admin`, `it_admin`, and `meeting.approve` permission).

---

## [1.0.8] - 2026-09-29

### Added
- **Embedded GitBook Documentation in Application (`/app/documentation`):**
  - Integrated interactive documentation viewer directly inside the application shell pointing to `https://senthilnasa.gitbook.io/zoom-pool-manager-zpm`.
  - Added 1-click chapter shortcut pills (Quick Start, Installation, Zoom OAuth, Webhooks, Resource Pools, REST API, Troubleshooting FAQ).
  - Added primary navigation item with `GitBook` badge in `Sidebar.vue` and global redirects from `/docs` and `/documentation`.
- **Select2 Style Searchable Dropdown for Custom Fields:**
  - Upgraded meeting booking custom field dropdowns in `MeetingCreatePage.vue` to `<SearchableSelect>` featuring floating body teleport, live text search/filtering, and keyboard navigation.
  - Updated custom field settings modal and table in `GeneralSettingsPage.vue` with `Dropdown (Select2 Searchable)` and `Dropdown (Select2)` badge.
- **GitBook Git Sync Schema Compliance:**
  - Standardized `gitbook-docs.yaml` to conform with GitBook's official site schema with non-empty slug paths and `default: true` flag.

### Fixed
- **Meeting Requester Email & Calendar Synchronization:**
  - Resolved booking-on-behalf omission where only host accounts and external invitees received confirmation emails. The requesting user (`requester_user_id`) now reliably receives confirmation emails, `.ics` calendar files, cancellation notices, and 15-minute start reminders.
  - Updated `IcsCalendarService.php` to add the requesting user as an accepted attendee to the `.ics` calendar invite so Google Calendar and Outlook automatically schedule the session on the requester's calendar.
- **Institutional Branding & Email Header Titles:**
  - Enhanced `TemplateRenderer.php`, `IcsCalendarService.php`, `MailDeliveryService.php`, `SpaGeneralSettingsController.php`, and `SpaController.php` so `org.name` reliably falls back across all setting aliases (`org.name`, `organization_name`, `org_name`, `config('app.organization_name')`).
  - Added institutional prefix to email subjects (`[{{org.name}}] Confirmed: {{meeting.title}}`) and styled header in default HTML email notifications.
  - Replaced hardcoded "Krea" placeholders in `GeneralSettingsPage.vue` and test suites with generic organizational defaults (`Your Organization Name`, `example.edu`).

---

## [1.0.7] - 2026-09-28

### Added
- **Interactive Zoom Webhook Events Debugger (`/app/api/inbound-webhooks`):**
  - **Live Debug Intake Stream**: Added toggleable real-time polling (every 3 seconds) with pulsing live indicator to watch Zoom meeting events stream in as they happen.
  - **Interactive Webhook Simulator**: Modal enabling administrators to dispatch simulated events (`meeting.started`, `meeting.ended`, `meeting.participant_joined`, or custom JSON payloads) to test intake handlers without initiating live Zoom sessions.
  - **1-Click CRC Handshake Test**: Built-in verification tool to simulate Zoom URL validation CRC challenges and inspect returned SHA-256 HMAC tokens.
  - **Intake Diagnostics & Stat Cards**: Live counts for total received events, processed, pending, failed, valid HMAC, and invalid signatures.
  - **Deep Event Inspector**: Modal displaying event headers, client IP, retry attempts count, error diagnostics with stack trace, and syntax-highlighted JSON with 1-click clipboard copy.
  - **Clear Simulated Events**: One-click cleanup to purge simulated debug records without affecting production event logs.
- **Convenient Routing Aliases:**
  - Registered `/app/webhooks`, `/webhooks/logs`, and `/debug/webhooks` redirects directly to the Webhook Debugger.
- **Automated Maintenance Mode Exemption Test:**
  - Added feature test asserting `/spa/settings/updates/progress` returns `200 OK` JSON during active maintenance mode (`php artisan down`).

### Fixed
- **Zero-Downtime Auto-Update Polling (Eliminating 503 Service Unavailable):**
  - Configured `$middleware->preventRequestsDuringMaintenance(except: [...])` in `bootstrap/app.php` to exempt `spa/settings/updates*`, `system/updates*`, `api/v1/health*`, and `/up`.
  - Added fail-safe fallback to disk (`storage/app/updates/progress.json`) in `AppUpdateService.php` so progress state survives `optimize:clear` cache flushes.
  - Added exception resilience in `SystemUpdateController::progress()` during temporary database schema migration locks.
- **Removed Unwanted Automatic Page Reload:**
  - Removed forced 5-second countdown timer and hard `window.location.reload()` in `UpdatesPage.vue`.
  - Added dedicated completion buttons: **"Reload Application Now"** and **"Close & Review Logs"** to keep administrators in complete control.

---

## [1.0.6] - 2026-09-28

### Added
- **Self-Healing Meeting Templates & Security Profiles:**
  - Implemented automatic database seeding and fallback creation for default templates and institutional security profiles if tables are empty.
- **Sticky Left Sidebar Navigation:**
  - Pinned sidebar with independent scrolling for high-density monitors and long page views.
- **Legacy Route Redirections:**
  - Added automatic redirects from `/templates`, `/admin/templates`, `/workflows`, and `/security-profiles` to modern `/app/*` SPA routes.

### Fixed
- **Workflows & Templates UI Modal Clipping:**
  - Teleported all dialog modals to document `body` with high z-index (`z-[100]`), eliminating z-index conflicts and clipping inside nested parent containers.
- **NOC Wallboard Test Boundary Edge Case:**
  - Frozen test time in NOC Wallboard test suite to avoid midnight time-boundary test flakes.

---

## [1.0.5] - 2026-09-28

### Added
- **Modern Zoom Cloud Recording Scopes Support:**
  - Added support and alias matching for granular Zoom recording scopes (`cloud_recording:read:recording:admin`, `cloud_recording:read:list_user_recordings:admin`, `cloud_recording:read:list_recording_files:admin`, `cloud_recording:read:meeting_transcript:admin`).
- **Scopes Diagnostic Intelligence:**
  - Updated the Zoom connection scopes audit view to recognize alternative scope variations and display accurate green verification badges.

---

## [1.0.4] - 2026-09-28

### Changed
- **Typography & Cross-Browser Consistency:**
  - Standardized character formatting by replacing all em-dashes (`—`) with clean standard hyphens (`-`) across page titles, breadcrumbs, navigation items, and view templates.

---

## [1.0.3] - 2026-09-28

### Fixed
- **Installer Database Connection Synchronization:**
  - Synchronized runtime database connection credentials on-the-fly (`config(['database.connections.mariadb => ...'])`) during Web Setup Wizard execution.
  - Explicitly purged stale PDO connections prior to running schema migrations to ensure verified credentials take effect immediately without requiring web server restarts.

---

## [1.0.2] - 2026-09-28

### Fixed
- **Pre-Setup Database Query Prevention:**
  - Forced file session driver (`SESSION_DRIVER=file`) in `bootstrap/app.php` whenever `storage/installed.lock` is absent.
  - Eliminated SQL connection errors on uninstalled instances before database credentials have been configured in the setup wizard.

---

## [1.0.1] - 2026-09-28

### Added
- **Zero-CLI Shared Hosting Auto-Initialization:**
  - Added automatic `.env` creation from `.env.example` and base64 `APP_KEY` generation on application boot for cPanel and Hostinger environments without SSH/terminal access.
- **Auto-Update Archive Protection:**
  - Explicitly protected `storage/installed.lock`, `.env`, and user uploads from being overwritten during 1-click update archive extraction.

### Fixed
- **SystemUpdateTest Dynamic Assertions:**
  - Updated test assertions to dynamically compare against configured runtime versions.

---

## [1.0.0] - 2026-09-23

### Project Attribution
- **Author:** Senthil Nasa ([github.com/senthilnasa](https://github.com/senthilnasa))
- **Official Repository:** [github.com/senthilnasa/zoom-pool-manager](https://github.com/senthilnasa/zoom-pool-manager)

### Added
- **Modern Vue 3 SPA Architecture & Left-Side Navigation:**
  - Sleek collapsible left sidebar with grouped navigation and active route indicators.
  - Mobile off-canvas slide-out drawer with backdrop blur.
  - Glassmorphic panels, ambient gradient glow cards, and unified responsive layout.
  - Full client-side SPA routing (`resources/js/router/index.js`), Pinia state stores, and Lucide icons.
- **Centralized Versioning & Auto-Update System:**
  - Central version configuration (`config/zpm.php` and `version.json`).
  - Automatic GitHub release detection with non-blocking cache.
  - GUI update interface (`/app/settings/updates`) with pre-update backups, update locking (`update.lock`), ZIP integrity & path traversal audit, and maintenance mode safe execution.
  - Artisan CLI update commands: `php artisan zpm:version` and `php artisan zpm:update`.
- **Core Allocation & Host Control Engine:**
  - Concurrency-safe atomic resource allocation engine with deadlock elimination (`SELECT ... FOR UPDATE` ordered by ID).
  - JIT `start_url` resolution directly to Zoom without database storage.
  - Secure Host Key reveal with 6-digit numeric PIN auto-rotation after meeting end.
  - Dynamic pre-meeting and post-meeting buffer window enforcement.
- **Approvals & Governance:**
  - Sequential multi-step approval chains (`ANY` / `ALL`) with Anti-Self-Approval enforcement.
  - Dynamic user and department quotas with automatic waitlist reallocation.
  - Security profile enforcement (passcodes, waiting rooms, AI companion restrictions).
- **Communication & Recordings:**
  - RFC 5545 `.ics` calendar invitation generator.
  - Pluggable email providers (SMTP, Gmail API, Microsoft Graph).
  - Cloud recordings management with logical owner mapping and secure playback.
- **Operations & Security:**
  - Real-time operations health monitor (`/app/operations/health`) and anomaly alert manager.
  - Automated database backup service with archive verification.
  - REST API with scoped API keys and Dedoc Scramble OpenAPI documentation (`/docs/api`).
  - Multi-language localization (English, Tamil, Hindi).
  - Simulated Demo Mode (`php artisan zpm:demo:seed`).
  - Mandatory TOTP two-factor authentication (MFA) and single-use emergency recovery codes.
