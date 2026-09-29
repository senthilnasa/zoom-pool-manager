# Quotas & Fair-Share Limits

To prevent individual departments or high-frequency users from monopolizing shared pool resources, Zoom Pool Manager includes a comprehensive **Quota Enforcement Engine** (`/app/quotas`).

---

## 📊 Quota Dimensions

Quotas can be defined across two independent dimensions:
1. **Meeting Count Quota**: Maximum number of scheduled sessions per calendar month (e.g. 20 meetings/month).
2. **Hour Allocation Quota**: Maximum cumulative scheduled duration in hours per calendar month (e.g. 50 hours/month).

---

## 🎯 Target Levels

- **Departmental Quotas**: Enforced across all members belonging to an academic department or business unit (e.g. `School of Humanities` limited to 300 hours/month).
- **User-Level Quotas**: Enforced on individual faculty members or event coordinators.

---

## ⚡ Dynamic Enforcement & Exceptions

- **Real-Time Check**: Quotas are evaluated at booking submission time. If a requested meeting would cause the user or department to exceed their threshold, booking is blocked with a clear notice.
- **Cancellation Recovery**: If a scheduled meeting is cancelled before its start time, the allocated hours and meeting counts are **automatically credited back** to the user and department balances.
- **Privileged Exemption**: Users holding the `Super Administrator` or `IT Administrator` role can override quota restrictions when scheduling critical sessions.
