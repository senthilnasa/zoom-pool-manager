# Changelog

All notable changes to **Zoom Pool Manager (ZPM)** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-09-23

### Project Attribution
- **Author:** Senthil Nasa ([github.com/senthilnasa](https://github.com/senthilnasa))
- **Official Repository:** [github.com/senthilnasa/zoom-pool-manager](https://github.com/senthilnasa/zoom-pool-manager)

### Added
- **Modern Gen-Z UI & Left-Side Navigation:**
  - Sleek collapsible left sidebar with grouped navigation and active route indicators.
  - Mobile off-canvas slide-out drawer with backdrop blur.
  - Glassmorphic panels, ambient gradient glow cards, and unified responsive layout.
  - Client-side SPA interaction engine (`zpm-spa.js`) with instant live table search, modal manager, and animated floating toast notifications.
- **Centralized Versioning & Auto-Update System:**
  - Central version configuration (`config/zpm.php`).
  - Automatic GitHub release detection with non-blocking cache.
  - GUI update interface (`/admin/system/updates`) with pre-update backups, update locking (`update.lock`), ZIP integrity & path traversal audit, and maintenance mode safe execution.
  - Artisan CLI update commands: `php artisan zpm:version` and `php artisan zpm:update`.
- **Core Allocation & Host Control Engine:**
  - Concurrency-safe atomic resource allocation engine with deadlock elimination (`SELECT ... FOR UPDATE` ordered by ID).
  - JIT `start_url` resolution directly to Zoom without database storage.
  - Secure Host Key reveal with 6-digit numeric PIN auto-rotation after meeting end.
- **Approvals & Governance:**
  - Sequential multi-step approval chains (`ANY` / `ALL`) with Anti-Self-Approval enforcement.
  - Dynamic user and department quotas with automatic waitlist reallocation.
- **Communication & Recordings:**
  - RFC 5545 `.ics` calendar invitation generator.
  - Pluggable email providers (SMTP, Gmail API, Microsoft Graph).
  - Cloud recordings management with logical owner mapping and secure playback.
- **Operations & Security:**
  - Real-time operations health monitor (`/admin/health`) and anomaly alert manager.
  - Automated database backup service with archive verification.
  - Rest API with scoped API keys and Dedoc Scramble OpenAPI documentation (`/docs/api`).
  - Multi-language localization (English, Tamil, Hindi).
  - Simulated Demo Mode (`php artisan zpm:demo:seed`).
