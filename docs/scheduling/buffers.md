# Buffer Times & Occupancy Windows

In an institutional environment, back-to-back meetings without gaps frequently result in attendee collisions, cutoffs, or meetings running over schedule.

Zoom Pool Manager addresses this with **dynamic buffer windows**.

---

## 📐 Buffer Window Anatomy

Every meeting reservation occupies a window that extends beyond its nominal start and end times:

$$\text{Occupancy Window} = \left[ t_{\text{start}} - B_{\text{pre}}, \quad t_{\text{end}} + B_{\text{post}} \right]$$

Where:
- $t_{\text{start}}$: Scheduled meeting start time.
- $t_{\text{end}}$: Scheduled meeting end time ($t_{\text{start}} + \text{duration}$).
- $B_{\text{pre}}$: **Pre-meeting buffer** (e.g. 10 minutes) allowing the host to join early, test audio/video, and set up presentation slides.
- $B_{\text{post}}$: **Post-meeting buffer** (e.g. 15 minutes) providing a grace period for questions and discussions before the license is rotated to another user.

---

## 🛡️ Overlap Calculations

When scheduling a candidate meeting with window $[c_{\text{start}} - B_{\text{pre}}, c_{\text{end}} + B_{\text{post}}]$, the conflict engine ensures:

$$\max\left(c_{\text{start}} - B_{\text{pre}}, \; e_{\text{start}} - B_{\text{pre}}\right) < \min\left(c_{\text{end}} + B_{\text{post}}, \; e_{\text{end}} + B_{\text{post}}\right)$$

If an overlap exists with an existing meeting $e$ on the same resource, the resource is flagged as unavailable.

---

## ⚡ Early Release ("End Meeting Early")

If a meeting finishes ahead of its scheduled duration:
- The host or an administrator can click **End Meeting Early** in the dashboard.
- ZPM instantly releases the remaining duration and post-meeting buffer, returning the license to the pool.
- Any waitlisted requests for that time slot are immediately evaluated for promotion.
