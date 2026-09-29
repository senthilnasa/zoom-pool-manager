# Meeting Booking & Conflict Prevention

Zoom Pool Manager features a concurrency-safe scheduling engine designed to eliminate double-booking, race conditions, and host collision.

---

## 📅 The Booking Process

Users book meetings through **Book Meeting** (`/app/meetings/create`):
1. **Details**: Title, description, and meeting type (Class, Seminar, Meeting, Office Hours).
2. **Timing**: Date, start time, and duration (in minutes).
3. **Pool Selection**: Target resource pool based on participant capacity or department.
4. **Security & Options**: Auto-recording options, waiting room toggles, and security profile selection.

---

## 🔒 Atomic Locking & Race Condition Prevention

When multiple users submit simultaneous requests for the last available license in a pool, standard database queries can create race conditions resulting in double bookings.

ZPM prevents this via **database-level pessimistic locking**:
```sql
SELECT * FROM zoom_resources 
WHERE pool_id = ? AND enabled = 1 
ORDER BY id ASC 
FOR UPDATE;
```

1. The allocation transaction locks the candidate pool records in deterministic order by `id` (preventing database deadlocks).
2. The engine checks every candidate against existing reservations, taking into account each meeting's dynamic buffer window.
3. The first available resource is allocated, the reservation is committed, and the database lock is released.
4. Any competing transaction immediately observes that the license is occupied.

---

## ⏳ Automated Waitlisting

If all licensed accounts in a pool are fully occupied at the requested time:
- Users can opt to join the **Waitlist**.
- If any meeting on that resource is cancelled or concludes early (via the **End Early** feature), ZPM's scheduler automatically reallocates the freed license to the next eligible waitlisted meeting, provisions the Zoom session, and emails the requester.
