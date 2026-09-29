# Security Profiles & AI Policies

Security Profiles (`/app/security-profiles`) define mandatory security baselines and AI companion policies enforced across meetings scheduled through the pool.

---

## 🔒 Configurable Controls

| Policy | Description | Recommended Setting |
| :--- | :--- | :--- |
| **Passcode Enforcement** | Requires all attendees to provide an encrypted meeting passcode | Enabled |
| **Waiting Room** | Forces participants into a virtual waiting room until admitted by host | Enabled for Public/Student pools |
| **Only Authenticated Users** | Restricts joining to users logged in with institutional Zoom accounts | Enabled for Faculty/Exams |
| **AI Companion Policy** | Allows, restricts, or completely forbids Zoom AI Companion summary generation | Strict / Policy-driven |
| **Watermarking** | Overlays attendee email as a visual watermark on shared screen feeds | Enabled for Confidential meetings |
| **Recording Restrictions** | Restricts local recording or requires cloud recording | Configurable |

---

## 🤖 Zoom AI Companion Compliance

For privacy and institutional intellectual property protection, ZPM allows administrators to govern AI features at the template or profile level:
- **`Disabled / Blocked`**: Explicitly disables AI Companion, preventing meeting summaries from being captured or sent to external models.
- **`Host-Only Approval`**: Requires explicit host permission before any participant can start an AI summary.
- **`Enabled`**: Full AI summary capabilities active.
