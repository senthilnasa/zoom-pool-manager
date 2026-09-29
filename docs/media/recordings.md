# Cloud Recordings & Transcripts

Zoom Pool Manager automatically indexes, maps, and manages cloud recordings across pooled Zoom host accounts (`/app/recordings`).

---

## 🎥 The Recording Ownership Problem

In a shared license pool, meetings are hosted on shared Zoom accounts rather than individual faculty accounts. Consequently:
- In Zoom, recordings are listed under the shared account name.
- Faculty members cannot easily locate their lectures or student meetings.
- Unrestricted access risks students or unauthorized users viewing private recordings.

---

## 🧠 Logical Owner Mapping

ZPM solves this by listening for the `recording.completed` webhook:
1. When a recording finishes rendering, Zoom dispatches a webhook containing the Zoom meeting ID and downloadable file URLs.
2. ZPM matches the meeting ID to its internal database reservation.
3. The recording is **automatically mapped to the logical owner** (the faculty member who requested the meeting).
4. The recording appears immediately in the user's personal recordings dashboard.

---

## 🔒 Secure Playback & Tokenized Redirects

ZPM does not expose raw Zoom download tokens or credentials to attendees:
- When a user clicks **Play Recording**, ZPM verifies their role and ownership permissions.
- If authorized, ZPM generates a short-lived signed playback redirect with the required Zoom password embedded.
- Unrelated users or external guests are denied access.
