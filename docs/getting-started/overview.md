# Platform Overview

**Zoom Pool Manager (ZPM)** was built to solve a major challenge faced by educational institutions, universities, and enterprise organizations: **Zoom license proliferation and utilization waste**.

In traditional environments, each faculty member or host requires a dedicated, paid Zoom Business or Enterprise license. However, the majority of meetings occur at staggered times throughout the week. This results in heavy annual license expenditures while average concurrent utilization remains under 20%.

Zoom Pool Manager introduces a **dynamic resource pooling architecture**. Instead of licensing hundreds of users individually, an organization maintains a shared pool of licensed host accounts. ZPM schedules meetings, handles conflict resolution, auto-assigns host keys, provisions recording access, and enforces institutional governance automatically.

---

## 💡 The Core Problem & The ZPM Solution

| Traditional Approach | Zoom Pool Manager Approach |
| :--- | :--- |
| **1:1 Licensing**: 500 faculty = 500 paid Zoom Enterprise licenses. | **N:1 Resource Pooling**: 500 faculty share a dynamic pool of 30-50 licenses based on peak concurrency. |
| **Manual Host Key Management**: Admins manually distribute host keys and passwords. | **Automated Host Delegation**: Host keys are rotated and revealed securely 15 minutes prior to meeting start. |
| **Double-Booking & Overlaps**: Overlapping bookings cause meetings to terminate unexpectedly. | **Conflict Prevention Engine**: Microsecond-accurate scheduling with mandatory buffer times between sessions. |
| **Unrestricted Cloud Storage**: Cloud storage fills up rapidly with no retention policy. | **Automated Recording Harvesting**: Automatically syncs recordings to object storage or local archives, then maps them to the booking owner. |
| **No Approval Governance**: Any user can schedule multi-hour meetings at peak times. | **Multi-Tier Workflows**: Automated auto-approval rules, departmental quotas, and escalation chains. |

---

## 🏛️ High-Level Component Stack

- **Backend Foundation**: Laravel 11.x on PHP 8.2+ with support for MySQL, MariaDB, and SQLite.
- **Frontend SPA**: Vue 3 with Composition API, Vite 5, Tailwind CSS, Pinia state stores, and Lucide icons.
- **Zoom Integration**: Server-to-Server OAuth 2.0 with automated token refresh, exponential backoff, and rate-limiting compliance.
- **Security & RBAC**: Spatie Permission with 9 canonical roles and 36 granular capabilities, TOTP two-factor authentication, cryptographic audit logging, and CSP security headers.
- **Automation Pipeline**: Cron scheduler running asynchronous queue workers for webhook processing, recording harvesting, directory sync, and drift reconciliation.
