# Frequently Asked Questions (FAQ)

Answers to frequently asked questions about Zoom Pool Manager.

---

### Does each faculty member need their own Zoom license?
**No.** That is the fundamental problem ZPM solves. Faculty members schedule meetings through ZPM, and ZPM dynamically assigns an available licensed account from your shared pool.

### How does a user host a meeting if they don't have the Zoom account password?
ZPM securely generates and reveals a **6-digit numeric Host Key PIN** to the meeting creator 15 minutes before the meeting starts. The user enters this PIN via **Claim Host** in Zoom. When the meeting concludes, ZPM rotates the host key automatically.

### Can faculty members access their cloud recordings?
**Yes.** ZPM automatically intercepts the Zoom `recording.completed` webhook, maps the recording to the faculty member who requested the meeting, and provides secure tokenized playback in their dashboard.

### How does 1-click update work without breaking my customizations?
ZPM's update manager creates a full database snapshot and file backup before extraction. It explicitly protects `.env`, `storage/installed.lock`, and user uploads from being overwritten. If any step fails, it executes an automatic rollback.

### Can I connect Zoom Pool Manager to Canvas, Moodle, or custom portals?
**Yes.** ZPM includes a complete REST API with scoped API keys, idempotency support, and outbound webhooks to integrate with your existing student information systems and LMS portals.
