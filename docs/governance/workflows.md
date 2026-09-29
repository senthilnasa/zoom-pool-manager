# Multi-Tier Workflow Approvals

Zoom Pool Manager enables organizations to define automated workflow rules and multi-tier approval chains (`/app/workflows` and `/app/approvals`).

---

## 🔀 Workflow Rule Engine

Workflow rules automatically evaluate booking requests against criteria to decide whether a meeting requires manual approval or can be auto-approved:

### Condition Criteria:
- **Participant Capacity**: e.g., Meetings over 200 participants require Department Head sign-off.
- **Duration**: e.g., Sessions longer than 3 hours require approval.
- **Pool Type**: e.g., The `Executive Webinar Pool` always requires approval.
- **Time of Day / Weekend**: e.g., Bookings outside normal university operating hours.
- **Requester Department / Role**: e.g., Faculty members auto-approve, student organizations require approval.

---

## 👥 Multi-Tier Approval Chains

Approval chains support flexible institutional routing:
- **Sequential Tiers**: Step 1 (Department Chair) $\rightarrow$ Step 2 (Dean of Academic Affairs).
- **Consensus Types**:
  - `ANY`: Any one reviewer in the tier can approve the request.
  - `ALL`: Every listed reviewer must sign off before the request advances.

---

## 🛡️ Anti-Self-Approval Rule

To prevent governance evasion, ZPM strictly enforces the **Anti-Self-Approval Rule**:
- If a Department Chair submits a meeting request for themselves, the system prevents them from approving their own booking.
- The approval item is automatically routed to their designated colleague or escalated to the next tier.

---

## 🤝 Delegations

If an approver is out on leave or unavailable, they can configure a **Delegation** (`/app/delegations`):
- Assigns approval authority to a colleague for a specified date range.
- All actions taken by the delegate are clearly tagged in the audit trail.
