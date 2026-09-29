# Recurring Meeting Series

For academic semesters, weekly seminars, or repeating committee meetings, Zoom Pool Manager supports **Recurring Meeting Series** (`/app/series`).

---

## 🔁 Recurrence Cadences

ZPM supports standard recurring frequencies:
- **Daily**: Every $N$ days (or every weekday).
- **Weekly**: Specific days of the week (e.g. Every Monday, Wednesday, Friday) for a specified number of weeks or until a fixed end date.
- **Monthly**: Specific day of the month.

---

## 🧩 Intelligent Atomic Allocation

Unlike standard calendars that require a single host account to be free across all recurring dates, ZPM supports **Per-Instance Dynamic Pool Allocation**:

- If Account A is free on Mondays but busy on Wednesdays, ZPM allocates Account A on Monday and Account B on Wednesday.
- All instances belong to the unified Series parent record, allowing bulk updates or single-instance exceptions.
- If a specific date encounters an institutional holiday or blackout period, ZPM skips or flags that instance without failing the remaining series.
