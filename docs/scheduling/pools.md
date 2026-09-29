# Resource Pools & Host Rotation

**Resource Pools** are the core scheduling abstraction in Zoom Pool Manager. A pool groups one or more licensed Zoom host accounts together into a logical unit assigned to specific departments, courses, or meeting types.

---

## 🏊 Pool Configuration Parameters

When creating or editing a pool in **Resource Pools** (`/app/pools`), administrators configure:

| Field | Description | Default |
| :--- | :--- | :--- |
| **Name** | Descriptive title (e.g. `Undergraduate Lectures Pool`, `Executive Boardroom`) | Required |
| **Allocation Strategy** | The algorithm used to assign a host account (`least_hours_today`, `priority`, `round_robin`) | `least_hours_today` |
| **Pre-Meeting Buffer** | Minutes reserved before the scheduled start time | `10` minutes |
| **Post-Meeting Buffer** | Minutes reserved after the scheduled end time | `15` minutes |
| **Notice Requirement** | Minimum lead time in advance to book a session | `30` minutes |
| **Max Meeting Duration** | Maximum allowable length for a single session | `240` minutes |
| **Concurrent Limit** | Optional hard ceiling on simultaneous sessions | Unlimited |

---

## 🔄 Allocation Strategies

1. **Least Hours Today (Recommended)**:
   Calculates total scheduled meeting minutes on each pool account for the current calendar day and selects the account with the lowest utilization. This distributes wear and prevents single accounts from being overloaded.
2. **Priority / Sequential**:
   Accounts are assigned integer priority scores ($1, 2, 3, \dots$). The engine always books the highest-priority available account first. Useful when you have primary dedicated accounts and secondary spillover accounts.
3. **Round Robin**:
   Cycles through accounts sequentially in order of their last assigned session.

---

## 🔑 Automated Host Key Rotation & Security

When a meeting is scheduled, ZPM does not share the host's Zoom account password. Instead:
1. Exactly **15 minutes before the meeting start time**, ZPM reveals the **6-digit numeric Host Key** to the meeting requester in their dashboard.
2. The user joins the meeting as a participant, clicks **Claim Host** in the Zoom desktop client, and enters the PIN.
3. Upon meeting conclusion (`meeting.ended` webhook), ZPM's background engine automatically invokes the Zoom API to **regenerate a new random 6-digit Host Key**, invalidating the previous PIN immediately.
