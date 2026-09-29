# Connection Test & Account Sync

Once your Zoom Server-to-Server OAuth application is configured, link it to Zoom Pool Manager and import your licensed accounts.

---

## 🔗 Configuring the Connection in ZPM

1. Log in to ZPM as a **Super Administrator**.
2. Navigate to **System Settings** → **Zoom Settings** (`/app/settings/zoom`).
3. Enter your credentials:
   - **Account ID**
   - **Client ID**
   - **Client Secret**
   - **Webhook Secret Token** (optional, recommended for HMAC security)
4. Click **Save Changes**.

---

## 🧪 Testing the Live Connection

Click the **Test Connection** button.

ZPM performs the following automated checks:
1. **OAuth Token Acquisition**: Posts credentials to `https://zoom.us/oauth/token` to verify client credentials.
2. **Scope Inspection**: Audits the returned `scope` parameter against the required capabilities checklist.
3. **API Ping**: Issues a lightweight request to `GET /users/me` to confirm API availability.
4. **Result Card**: Displays connection latency, account name, and scope diagnostics with colored status badges.

---

## 👥 Syncing Zoom Users into Resource Pools

Once the connection is active:
1. Click **Sync Zoom Users** on the Zoom Settings page.
2. ZPM queries `GET /users` across your Zoom account and identifies all licensed (Type 2 Pro/Business/Enterprise) accounts.
3. The accounts are registered as **Zoom Resources** in ZPM.
4. You can now navigate to **Resource Pools** (`/app/pools`) to group these accounts into custom departmental or classroom pools.
