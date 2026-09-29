# Webhook Live Debugger & Event Simulator

Testing webhooks in production can be challenging without real meetings running. Zoom Pool Manager includes an interactive **Webhook Debugger & Simulator** built into the administrative SPA.

Navigate to **Zoom Intake Logs** in the sidebar or browse directly to:
```text
/app/api/inbound-webhooks
/app/webhooks
/debug/webhooks
```

---

## ⚡ Key Debugger Capabilities

### 1. Real-Time Live Streaming (3s Auto-Poll)
Click **Enable Live Stream** to activate automated polling every 3 seconds. A pulsing green indicator highlights when the live intake stream is listening. You can trigger an event in Zoom and observe it appear in ZPM within seconds.

### 2. Interactive Event Simulator
Click **Simulate Event** to test webhooks on-demand without initiating an actual Zoom call:
- **Preset Selector**:
  - `meeting.started`: Simulates host starting a meeting.
  - `meeting.ended`: Simulates host or attendees concluding a session.
  - `meeting.participant_joined`: Simulates attendee check-in.
  - `endpoint.url_validation`: Tests the Zoom CRC handshake locally.
- **Custom JSON Editor**: Edit the JSON payload or meeting ID dynamically.
- Click **Dispatch Simulated Event** to record the event and execute the corresponding asynchronous queue jobs immediately.

### 3. 1-Click CRC Handshake Verification
Click **Test CRC Handshake** to simulate an automated Zoom URL validation challenge. ZPM generates a random `plainToken`, hashes it with the configured `webhook_secret_token`, and verifies that the output matches Zoom's exact cryptographic handshake specification.

### 4. Event Inspector Modal
Click any event row to inspect:
- **HMAC Signature Status**: Verifies if the signature matched the payload.
- **Client IP & Timestamp**: The source IP address and time received.
- **Attempts & Execution Errors**: If a webhook processor failed, inspect the error message and trace.
- **Raw JSON Payload**: Syntax-highlighted, formatted JSON with a **Copy Payload** button.
- **Replay Event**: Re-queues the event into the queue worker to re-test processing logic.

### 5. Clear Simulated Events
To prevent test records from cluttering production data, click **Clear Simulated** to delete all events prefixed with `sim-` while preserving authentic Zoom webhook records.
