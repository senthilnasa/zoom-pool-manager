# Architecture & Key Concepts

Zoom Pool Manager is engineered around a modular, domain-driven structure designed for high reliability, fault tolerance, and multi-tenant university environments.

---

## 🏛️ Domain Architecture

```
app/Domain/
├── Api/              # API keys, tokens, outbound webhooks
├── Audit/            # Cryptographic audit hash chaining
├── Governance/       # Workflow rules, multi-tier approvals, delegations
├── HostControl/      # Automated host key rotation and early start window
├── Meetings/         # Core scheduling state machine, series, conflict engine
├── Policies/         # Institutional rules, notice times, buffer constraints
├── Quotas/           # Departmental and user monthly allocation limits
├── Settings/         # Central configuration repository
├── System/           # 1-click update service, backups, maintenance mode
├── Users/            # User profiles, departments, designations
├── Webhooks/         # Inbound Zoom event intake, HMAC verification, jobs
└── Zoom/             # Zoom S2S OAuth client, sync engine, resource pools
```

---

## 🔑 Key Concepts

### 1. Resource Pools
A **Resource Pool** represents a collection of licensed Zoom host accounts. For example:
- `Classroom Pool`: 20 Zoom accounts with 300-participant capacity for daily lectures.
- `Executive Pool`: 5 Zoom accounts with 1,000-participant capacity and AI Companion enabled.
- `Webinar Pool`: 2 accounts with Zoom Webinars 3,000-participant capacity.

When a user requests a meeting, ZPM assigns an available account using one of three strategies:
- `Least Hours Today`: Distributes meeting load evenly across host licenses.
- `Priority / Sequential`: Fills the primary licenses first before activating secondary ones.
- `Round Robin`: Cycles through accounts sequentially.

### 2. The Buffer Time Window
Zoom meetings rarely start and finish exactly on the clock. If Meeting A runs until 10:00 AM and Meeting B is scheduled for 10:00 AM on the same license, attendees will collide or Meeting A will be abruptly terminated.

ZPM enforces **pre-meeting and post-meeting buffer times** (e.g., 10 minutes before and 15 minutes after). The resource remains occupied throughout the entire window:

$$\text{Occupied Period} = [\text{Start Time} - \text{Pre Buffer}, \quad \text{End Time} + \text{Post Buffer}]$$

### 3. Server-to-Server OAuth 2.0
Traditional Zoom apps require each individual faculty member to authorize their account via browser OAuth. ZPM utilizes **Zoom Server-to-Server (S2S) OAuth**, which operates at the Zoom Account level:
- Grants administrative API access to schedule and manage meetings.
- Eliminates individual user login credentials or token expirations.
- Handles token retrieval, caching, and automatic renewal in the background.

### 4. Meeting Lifecycle State Machine
Meetings transition strictly through an auditable state machine:

```
[Requested] ──► [Pending Approval] ──► [Approved] ──► [Scheduled]
     │                                                     │
     ▼                                                     ▼
[Waitlisted]                                           [Started]
     │                                                     │
     ▼                                                     ▼
[Cancelled]                                             [Ended]
```
