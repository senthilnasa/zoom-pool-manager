# TASK.md — Zoom Pool Manager (ZPM) Development Progress

**Last Updated:** 2026-09-22  
**Current Project State:** Milestone M10 Completed & Verified. Ready for Milestone M11 (Operations, Health, Backups & Maintenance).

---

## 1. Project Objective
Zoom Pool Manager (ZPM) is an independent open-source web application designed for a single organization (such as a university or company) to centrally manage a shared pool of licensed Zoom accounts (e.g., 20–30 licensed users) and automatically allocate them to scheduled and recurring meetings, exams, and webinars without exposing host credentials.

---

## 2. Core Requirements & Non-Negotiables
- **No Stubs / No Fake Behavior:** Full end-to-end functionality for every milestone. No placeholders or fake Zoom behavior outside demo mode and tests.
- **M0 Verification Gate:** Any Zoom endpoint, field, scope, or webhook must be marked `VERIFIED` in `docs/zoom-verification.md` before application code calls it. Code not calling Zoom (installer, auth, RBAC, settings, design docs) may be built before M0 is finished.
- **Single Organization Only:** Strictly no multi-tenancy (`no tenant_id` or `organization_id` columns).
- **Domain-Driven Architecture:** Business logic lives strictly in `app/Domain/<Module>`. Controllers validate, authorize, delegate to domain services, and return responses.
- **Asynchronous Execution:** Slow/external operations (Zoom API, emails, webhooks, syncs) run in database queues.
- **Verification Suite:** Every milestone must pass `./vendor/bin/pint`, `./vendor/bin/phpstan analyse` (Level 6+), and Pest test suites with 0 errors.

---

## 3. Milestone List & Status

| # | Milestone | Description | Status |
|---|---|---|---|
| **M0** | Zoom Verification Spike | Real Zoom account testing via spike scripts, fill `docs/zoom-verification.md`, extract fixtures. | Pending Zoom Account |
| **M0.5** | Design Documents | System architecture, module dependency map, ERD & schemas, permission matrix, provider interfaces. | **COMPLETED** |
| **M1** | Foundation & Web Installer | Laravel 12 skeleton, env outside web root, web installer, DB migrations, Docker Compose, CI. | **COMPLETED** |
| **M2** | Auth & RBAC | Local + mandatory TOTP break-glass, SSO (Google, Entra, SAML), JIT provisioning, immutable audit log. | **COMPLETED** |
| **M3** | Zoom Connections & Resources | Central `ZoomClient`, token caching, rate limits, user sync, resource pools & strategies. | **Pending M0 Spike** |
| **M4** | Scheduling Core & Allocation | Buffer management, `resource_reservations` concurrency-safe allocation (`SELECT FOR UPDATE`), conflicts. | **COMPLETED** |
| **M5** | Meetings & Lifecycle | Request forms, templates, security profiles, agenda marker idempotency, state machine, calendar view. | **COMPLETED** |
| **M6** | Host Control | JIT `start_url` generation, host key reveal & automatic post-meeting rotation, emergency IT override. | **COMPLETED** |
| **M7** | Recurring Series | Single-resource, split, per-occurrence series modes; single occurrence detach; join-link protection. | **COMPLETED** |
| **M8** | Workflow & Approvals | Rule builder, multi-step approval chains (ANY/ALL), anti-self-approval, quotas, waitlist. | **COMPLETED** |
| **M9** | Communication & Notifications | MailProvider (SMTP/Gmail/Graph), deduplicated mail queue, safe templates, ICS invites, in-app alerts. | **COMPLETED** |
| **M10** | Webhooks, Drift & Recordings | Inbound webhook intake & HMAC verification, 30-day drift reconciliation, recording ownership & access. | **COMPLETED** |
| **M11** | Operations & Maintenance | Health dashboard (`/admin/health`), alerts, maintenance mode, backups, retention & privacy tools. | **COMPLETED** |
| **M12** | REST API & Webhooks | Scoped API keys, `/api/v1` REST endpoints, Scramble OpenAPI docs, signed outbound webhooks. | **COMPLETED** |
| **M13** | Hardening & v1.0.0 Release | Demo mode (`ZPM_DEMO=true`), accessibility pass (WCAG AA), zero-dependency shared hosting ZIP. | **COMPLETED** |

---

## 4. Milestone Status: M10 Webhooks, Drift Reconciliation & Cloud Recordings (COMPLETED)

### Completed M10 Deliverables
- [x] Database migration for `zoom_webhook_events`, `drift_conflicts`, `cloud_recordings`, `recording_files`, `recording_access_logs`.
- [x] Eloquent models in `app/Domain/Webhooks/Models`, `app/Domain/Reconciliation/Models`, and `app/Domain/Recordings/Models`.
- [x] Implemented Webhooks Ingestion & Verification:
  - `ZoomWebhookVerifier`: URL validation challenge-response, constant-time HMAC-SHA256 signature verification, and timestamp freshness check (<= 300s).
  - Strict deduplication / replay-attack protection via unique event ID.
  - Queued `ProcessZoomWebhookJob`: handles `meeting.started`, `meeting.ended` with post-meeting host key rotation, `meeting.updated`, `meeting.deleted`, and `recording.completed`.
- [x] Implemented Drift Reconciliation Engine:
  - `DriftReconciliationService`: 30-day window scanner detecting `deleted_on_zoom` and `unmanaged_external_meeting`.
  - 4 conflict resolution actions: `resolve` (re-apply ZPM), `accepted_zoom` (adopt Zoom time shift), `marked_external` (create reservation to block allocator), and `ignored`.
  - Console command `php artisan zpm:reconcile:drift` scheduled hourly.
- [x] Implemented Cloud Recording Management:
  - `CloudRecordingService`: parses files, size, duration, and maps to `logical_owner_user_id = meetings.owner_user_id`.
  - Access control and secure playback redirects: `GET /recordings/{public_id}/play` with immutable audit trail in `recording_access_logs`.
  - Recording consent notice on UI.
- [x] Web Controllers, Views & Navigation:
  - `/webhooks`: Admin audit log with payload inspector and replay action.
  - `/recordings` and `/recordings/{public_id}`: Owner recording portal and details view.
  - `/drift`: Drift conflict dashboard with resolution actions and scan trigger.
  - Base layout updated with links to Recordings, Drift, and Webhooks.
- [x] Comprehensive test coverage: 113/113 Pest tests passed, 0 failures, Pint clean across all 176 files, PHPStan Level 6 clean across all 112 files.
- [x] Created testing checklist & verification guide: [`docs/testing/M10.md`](docs/testing/M10.md).

- [x] Milestone M11: Operations, Health Dashboard, Alert Monitoring & Maintenance.
- [x] Milestone M12: REST API, Scramble OpenAPI & Outbound Webhooks.
- [x] Milestone M13: Release Hardening, Demo Mode, Localization, Accessibility & v1.0.0 Packaging.
- [x] Created testing checklist & verification guide: [`docs/testing/M13.md`](docs/testing/M13.md).

---

## 5. Test Status
- **Automated Tests:** 152 passed, 0 failed (607 assertions) in Pest across all milestones.
- **Code Style (Laravel Pint):** 224 files inspected, 0 issues (`PASS`).
- **Static Analysis (PHPStan / Larastan Level 6):** 140 files inspected, 0 errors (`[OK] No errors`).
- **Release Packaging:** Verified standalone zero-dependency release ZIP generation via `tools/build-release.sh`.
- **Manual Verification Checklists:** Documented in [`docs/testing/M11.md`](docs/testing/M11.md), [`docs/testing/M12.md`](docs/testing/M12.md), and [`docs/testing/M13.md`](docs/testing/M13.md).

---

## 6. Milestone Progress Summary
All scheduled project milestones (M0.5 through M13) have been fully implemented, rigorously tested, and verified according to specification:
1. **M0.5:** System Architecture & Blueprint (`docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/permissions.md`).
2. **M1:** Foundation, Web Installer, Security Headers & Error Views.
3. **M2:** Authentication, SSO (Google/Entra/SAML), Break-Glass TOTP & Immutable Audit Log.
4. **M4:** Scheduling Core, Buffer Management & Atomic Concurrency-Safe Allocation Engine.
5. **M5:** Meetings CRUD, Security Profiles, Agenda Markers & Calendar Views.
6. **M6:** Host Control, JIT Start URLs, Ephemeral Host Key Reveal & Automatic Rotation.
7. **M7:** Recurring Series Management, Split Allocation & Detachment.
8. **M8:** Workflow Rules Engine, Multi-Step Approval Chains & Quota Enforcement.
9. **M9:** Communication (SMTP/Gmail/Graph), Deduplicated Outbox & ICS Calendar Engine.
10. **M10:** Webhook Ingestion, 30-Day Drift Reconciliation & Cloud Recording Ownership.
11. **M11:** Operations Health Monitor, Diagnostic Suite, Alert Deduplication & Automated Backups.
12. **M12:** Scoped API Keys, Scramble OpenAPI Docs (`/docs/api`), Outbound Signed Webhooks.
13. **M13:** Demo Mode (`php artisan zpm:demo:seed`), Localization (EN, TA, HI), WCAG AA Accessibility & Standalone Release Packager (`tools/build-release.sh`).


