# 1-Click Zero-Downtime Updates

Zoom Pool Manager features an enterprise-grade automated update engine (`/app/settings/updates`) inspired by modern production systems. It eliminates manual FTP uploads, terminal commands, and unexpected deployment breaks.

---

## 🚀 The 7-Step Update Lifecycle

When a new version is released on GitHub, a prominent notification badge appears in the navigation. Clicking **Update Now** executes the safe update sequence:

```
[0. Check Release] ──► [1. Download & Verify SHA-256] ──► [2. Pre-Update Backup]
                                                                  │
┌─────────────────────────────────────────────────────────────────┘
│
└──► [3. Extract & Preserve] ──► [4. Run Migrations] ──► [5. Clear Caches] ──► [6. Verify & Ready]
```

1. **Checking latest version**: Queries the GitHub API for the latest signed release asset.
2. **Downloading update**: Downloads the release package directly to `storage/app/updates/` and verifies the SHA-256 checksum against GitHub release metadata.
3. **Creating pre-update backup**: Automatically creates a verified database SQL snapshot and a ZIP archive of critical code directories (`app/`, `config/`, `routes/`, `resources/`).
4. **Installing update**: Puts the application into maintenance mode (`php artisan down`) and extracts updated files, **strictly protecting your `.env`, uploaded storage, and installed locks**.
5. **Running migrations**: Executes `php artisan migrate --force` to upgrade database schemas automatically.
6. **Rebuilding caches & exiting maintenance**: Clears stale view/route caches and brings the application back online (`php artisan up`).
7. **Verifying installation**: Asserts active version consistency and records an immutable audit entry.

---

## 🛡️ Safeguards & Zero-Downtime Polling

- **Maintenance Mode Exemption**: Update progress endpoints (`/spa/settings/updates/progress`) are exempted from maintenance mode in `bootstrap/app.php`. The browser can poll real-time progress without receiving `503 Service Unavailable`.
- **Automatic Rollback**: If an unhandled exception or migration error occurs at any point, ZPM immediately restores the pre-update file archive and database snapshot, ensuring your service remains online.
- **Manual Reload Control**: When the update finishes, ZPM displays a success banner and gives the administrator full control to review logs before clicking **Reload Application Now**.
