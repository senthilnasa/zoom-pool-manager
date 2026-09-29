# Background Jobs & Automation Cadence

Zoom Pool Manager utilizes background jobs and a task scheduler to automate ongoing tasks (`/app/settings/jobs`).

---

## ⚙️ Automated System Tasks

| Scheduled Job | Recommended Cadence | Description |
| :--- | :--- | :--- |
| **`zpm:holds:release`** | Every minute (`* * * * *`) | Frees expired meeting reservation holds older than 10 minutes. |
| **`zpm:drift:scan`** | Every 15 minutes (`*/15 * * * *`) | Compares active Zoom schedule against ZPM database to detect external drift. |
| **`zpm:recordings:sync`** | Hourly (`0 * * * *`) | Scans for newly completed cloud recordings and maps them to meeting owners. |
| **`zpm:directory:sync`** | Daily (`0 2 * * *`) | Synchronizes campus directory accounts, departments, and role updates. |
| **`zpm:backup:run`** | Daily (`0 3 * * *`) | Generates verified database SQL dumps and cleans up aged snapshots. |
| **`zpm:health:check`** | Every 5 minutes (`*/5 * * * *`) | Evaluates database latency, queue backlog, and Zoom API connectivity. |

---

## 💻 Manual Execution from UI

Administrators can inspect the execution log of every registered background job and click **Run Now** in the web interface to trigger any task immediately.
