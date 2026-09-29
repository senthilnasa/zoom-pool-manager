# Database Backups & Snapshots

Zoom Pool Manager includes a built-in **Backup & Snapshot Manager** (`/app/operations/backups`) designed to safeguard institutional data without requiring external database administrative tools.

---

## 💾 Snapshot Features

- **Automated Pre-Update Snapshots**: Every time an auto-update is executed, a full database snapshot is generated automatically.
- **On-Demand Backups**: Administrators can create an immediate full database snapshot at any time by clicking **Create Backup**.
- **Archive Verification**: Backups are verified for integrity upon creation and stored in `storage/app/backups/`.
- **Direct Secure Download**: Authorized Super Administrators can download database dumps directly through the web UI over HTTPS.

---

## 🔄 CLI Backup Commands

You can also trigger backups through Laravel's Artisan command line:

```bash
# Create a verified database backup
php artisan zpm:backup:run

# List existing backup snapshots
php artisan zpm:backup:list
```

---

## 🧹 Retention & Cleanup

ZPM automatically prunes old snapshots according to your configured retention window (default: retain backups for 30 days) to prevent disk exhaustion on shared hosting servers.
