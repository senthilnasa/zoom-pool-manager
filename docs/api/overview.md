# REST API Overview & Authentication

Zoom Pool Manager provides a REST API enabling integration with Learning Management Systems (Canvas, Moodle, Blackboard), ERP portals, campus mobile applications, and custom scheduling tools.

---

## 🌐 Base URL & Format

```text
https://zoom.yourdomain.com/api/v1
```

- All requests must include the `Accept: application/json` header.
- Responses return structured JSON with ISO-8601 UTC timestamps.

---

## 🔑 Bearer Token Authentication

Every API request requires an authorized API Key passed in the `Authorization` header:

```http
GET /api/v1/pools HTTP/1.1
Host: zoom.yourdomain.com
Authorization: Bearer zpm_live_xxxxxxxxxxxxxxxxxxxxxxxx
Accept: application/json
```

---

## ⚡ Idempotency Keys

For non-idempotent operations like booking a meeting, pass an `Idempotency-Key` header:

```http
POST /api/v1/meetings HTTP/1.1
Authorization: Bearer zpm_live_xxxxxxxxxxxxxxxxxxxxxxxx
Idempotency-Key: 7b35f299-19ec-458f-b98a-54316d24a7ee
Content-Type: application/json

{
  "title": "CS101 Algorithms Lecture",
  "pool_id": 1,
  "starts_at": "2026-10-15T10:00:00Z",
  "duration": 60
}
```

If a network timeout occurs and your client retries the request with the identical key, ZPM returns the previously created meeting response without allocating a second license.
