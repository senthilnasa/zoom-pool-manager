# Drift Reconciliation

In production, external changes can cause the state of meetings in Zoom to diverge from the schedule in ZPM (e.g. an administrator manually deletes a meeting directly in the Zoom Web Portal, or reschedules it via the Zoom desktop client).

Zoom Pool Manager's **Drift Reconciliation Engine** (`/app/drift`) detects and resolves these discrepancies automatically.

---

## 🔍 Drift Scan Detection

The drift engine periodically compares active reservations in ZPM against live Zoom API records:

| Drift Anomaly | Detection | Automated Action |
| :--- | :--- | :--- |
| **External Deletion** | Meeting exists in ZPM as `scheduled`, but returns `404 Not Found` in Zoom. | Marks meeting as cancelled in ZPM and frees the pooled resource immediately. |
| **Time Discrepancy** | Meeting was rescheduled directly on Zoom. | Updates start and end times in ZPM and re-validates buffer window compliance. |
| **Unmanaged Meeting** | A meeting was created directly on a pooled host account outside ZPM. | Flags the conflict and prevents ZPM from scheduling overlapping sessions on that account. |

---

## 🛠️ Manual & Automated Resolution

Administrators can trigger an on-demand scan via the **Scan for Drift** button or rely on the background cadence (`php artisan zpm:drift:scan`). Detected conflicts can be accepted, reassigned, or dismissed with a single click.
