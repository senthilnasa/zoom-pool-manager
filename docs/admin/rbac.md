# Role-Based Access Control (RBAC)

Zoom Pool Manager enforces granular, auditable permissions through an enterprise RBAC implementation built on Spatie Permission.

---

## 👥 Canonical System Roles

ZPM is pre-seeded with 9 distinct operational roles:

| Role Name | Scope & Authority |
| :--- | :--- |
| **`Super Administrator`** | Full institutional authority, system updates, billing, Zoom S2S credentials, and audit logs. |
| **`IT Administrator`** | Technical maintenance, pool management, host rotation, drift reconciliation, and API keys. |
| **`Security Officer`** | Audit log inspection, security profiles, AI Companion compliance, and data retention policies. |
| **`Department Administrator`** | Manages departmental users, department quotas, and departmental workflow rules. |
| **`Department Approver`** | Reviews and signs off on pending meeting requests within their department. |
| **`Faculty Member / Host`** | Books meetings from assigned pools, reveals host keys during windows, and accesses recordings. |
| **`Event Coordinator`** | Schedules large-scale webinars, multi-session conferences, and recurring series. |
| **`Standard User`** | Submits basic meeting booking requests subject to departmental approval. |
| **`Read-Only Auditor`** | Compliance and reporting observer with read-only visibility into schedules and attendance. |

---

## 🔑 Granular Capabilities

Access is governed by 36 distinct permissions (e.g. `meetings.create`, `meetings.emergency_override`, `recordings.play_all`, `settings.manage`, `api.manage`). Custom roles can be tailored to unique institutional hierarchies without modifying application source code.
