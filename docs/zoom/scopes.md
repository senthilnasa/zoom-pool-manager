# Zoom App Scopes Reference

To manage meetings, sync host accounts, and harvest recordings, your Server-to-Server OAuth app must have the appropriate scopes granted in the Zoom Marketplace.

---

## 📋 Required & Recommended Scopes

| Scope Category | Scope Name | Alternative / Granular Alias | Why It's Needed |
| :--- | :--- | :--- | :--- |
| **Meeting Management** | `meeting:write:admin` | `meeting:write` | Required to create, update, and cancel pooled Zoom meetings. |
| **Meeting Read** | `meeting:read:admin` | `meeting:read` | Inspect scheduled meetings, host start URLs, and settings. |
| **User Management** | `user:read:admin` | `user:read` | Query Zoom users to import and synchronize host licenses into ZPM resource pools. |
| **Host Key Rotation** | `user:write:admin` | `user:write` | Automatically rotate and reset host keys on accounts after meeting conclusion. |
| **Cloud Recordings** | `recording:read:admin` | `cloud_recording:read:recording:admin`<br>`cloud_recording:read:list_user_recordings:admin`<br>`cloud_recording:read:list_recording_files:admin` | Index completed cloud recordings, generate secure playback redirects, and sync transcripts. |
| **Usage Reports** | `report:read:admin` | `dashboard:read:admin` | Pull concurrency analytics, meeting participant counts, and duration telemetry. |

---

## 🔍 How ZPM Validates Scopes

When you test your Zoom connection in **System Settings** → **Zoom Settings** (`/app/settings/zoom`), ZPM checks the OAuth token's granted scopes payload.

- If a modern granular alias (such as `cloud_recording:read:recording:admin`) is returned instead of the legacy `recording:read:admin`, ZPM's scope normalizer detects and marks the capability as verified.
- The UI displays an interactive scope checklist showing which features are operational.
