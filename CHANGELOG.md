# Changelog

All notable changes to **Zoom Pool Manager (ZPM)** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
