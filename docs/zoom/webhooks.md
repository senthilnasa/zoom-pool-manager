# Webhook Intake & CRC Handshake

Zoom Pool Manager relies on incoming webhooks to keep meeting statuses, host availability, participant attendance, and cloud recordings synchronized in real time.

---

## 📡 Webhook Intake URLs

ZPM provides two dedicated webhook intake endpoints (both accept identical payloads):

```text
# Primary Endpoint
https://zoom.yourdomain.com/webhooks/zoom

# Alternative / API Endpoint
https://zoom.yourdomain.com/api/webhooks/zoom
```

> [!NOTE]
> If navigating to either endpoint in a web browser via `GET`, ZPM responds with a `200 OK` JSON health verification payload explaining that the endpoint is active and awaiting `POST` webhooks.

---

## 🤝 Zoom URL Validation (CRC Handshake)

When you configure an Event Notification Endpoint in the Zoom Marketplace, Zoom immediately issues an automated HTTP `POST` validation challenge to confirm endpoint ownership.

### Handshake Structure:
1. Zoom sends:
   ```json
   {
     "event": "endpoint.url_validation",
     "payload": {
       "plainToken": "sample_plain_token_string"
     }
   }
   ```
2. ZPM computes an HMAC-SHA256 hash using your **Webhook Secret Token**:
   $$\text{encryptedToken} = \text{hash\_hmac}('sha256', \text{plainToken}, \text{secretToken})$$
3. ZPM immediately replies with `200 OK`:
   ```json
   {
     "plainToken": "sample_plain_token_string",
     "encryptedToken": "computed_hmac_sha256_hash"
   }
   ```
4. Zoom validates the hash and marks your endpoint with a green checkmark.

---

## 🔒 HMAC Signature Verification & Replay Protection

Every production webhook sent by Zoom includes two critical verification headers:
- `x-zm-request-timestamp`: Epoch timestamp in seconds.
- `x-zm-signature`: `v0=` followed by the computed HMAC-SHA256 signature.

### Verification Guardrails:
- **Timestamp Freshness**: Requests older than 5 minutes (300 seconds) are rejected with `401 Unauthorized` to prevent replay attacks.
- **HMAC Check**: The raw request body is verified against the signature using `hash_hmac('sha256', "v0:{$timestamp}:{$rawBody}", $secretToken)`.
- **Deduplication**: Every incoming event has a unique event ID or computed hash recorded in `zoom_webhook_events`. Repeated identical events return `already_received` without duplicate queue jobs.
