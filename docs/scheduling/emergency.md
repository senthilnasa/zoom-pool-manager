# Emergency Overrides

In high-priority situations (e.g. an urgent Vice Chancellor address, emergency faculty senate meeting, or critical technical failure), authorized administrators can invoke **Emergency Overrides** (`/app/operations/emergency`).

---

## 🚨 Emergency Capabilities

### 1. Emergency Reallocation
Allows an IT Administrator or Super Admin to reassign an ongoing or imminent meeting from one Zoom host account to another license within the pool without cancelling the session.

### 2. Priority Preemption
If all licenses are occupied, an administrator with the `emergency.override` capability can bump an existing lower-priority meeting to the waitlist or reassign it, freeing the license immediately for the emergency event.

### 3. Immediate Termination
Instantly ends a meeting in progress on Zoom and resets the host key if a session violates institutional security policies or runs severely over schedule.

---

## 🔒 Mandatory Audit Justification

Every emergency override requires:
- A mandatory text justification recorded in the database.
- An immutable entry appended to the **Cryptographic Audit Log** (`/app/audit`).
- Automated email notifications dispatched to both the affected meeting host and the overriding administrator.
