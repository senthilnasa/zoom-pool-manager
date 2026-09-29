# Outbound Webhooks

Keep downstream campus systems, attendance databases, and notification services updated via **Outbound Webhooks** (`/app/api/outbound-webhooks`).

---

## 📡 Supported Outbound Events

- `meeting.scheduled`: Triggered when a meeting is confirmed and allocated a Zoom license.
- `meeting.started`: Dispatched when the host initiates the meeting.
- `meeting.ended`: Dispatched when the session concludes.
- `meeting.cancelled`: Dispatched upon cancellation or early release.
- `recording.available`: Dispatched when cloud recordings finish processing.

---

## 🔒 HMAC Signature Verification

Every outbound webhook delivery includes a signature header computed using your configured shared secret:

```http
X-ZPM-Signature: sha256=d7a8fbb307d7809469ca933b02d82941d3f73ffda97e60e2d019c305d7cadf02
X-ZPM-Timestamp: 1790615078
```

Your receiving server can verify authenticity:

```python
import hmac, hashlib

expected = hmac.new(
    secret.encode(),
    f"{timestamp}.{raw_body}".encode(),
    hashlib.sha256
).hexdigest()

assert hmac.compare_digest(f"sha256={expected}", header_signature)
```

---

## 🔁 Retry Policy

Failed webhook deliveries (non-2xx responses or network timeouts) automatically retry using exponential backoff across 5 attempts over 24 hours.
