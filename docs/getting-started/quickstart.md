# Quick Start Guide

Get your Zoom Pool Manager instance operational in less than 5 minutes.

---

## 1. Deploy the Application

Choose your preferred deployment method:

### Option A: Shared Hosting (Zero CLI)
1. Download the latest release package: `zoom-pool-manager-vX.X.X.zip` from [GitHub Releases](https://github.com/senthilnasa/zoom-pool-manager/releases).
2. Upload and extract into your web root (or point your domain document root to `public/`).
3. Open your browser at `https://your-domain.com`. The **Web Setup Wizard** will launch automatically.

### Option B: Docker Compose
```bash
git clone https://github.com/senthilnasa/zoom-pool-manager.git
cd zoom-pool-manager
docker compose up -d
```
Visit `http://localhost:8088` in your browser.

---

## 2. Complete the Web Setup Wizard

The interactive wizard guides you through:
1. **Requirements Check**: Automatically verifies PHP extensions and directory permissions.
2. **Database Configuration**: Enter MySQL/MariaDB credentials and click **Test Connection**.
3. **Automated Migration**: Builds all tables, default policies, and security templates.
4. **Administrator Enrollment**: Set your Super Admin credentials and configure 2FA (Authenticator App).

---

## 3. Connect Zoom Account

1. Navigate to **System Settings** → **Zoom Settings** (`/app/settings/zoom`).
2. Enter your Zoom **Account ID**, **Client ID**, and **Client Secret** (from your Zoom App Marketplace Server-to-Server OAuth app).
3. Click **Test Connection**. Once verified, click **Sync Zoom Users** to import your licensed accounts into the resource pool.

---

## 4. Configure Webhooks

1. Copy the webhook URL from **Zoom Settings** or the **Webhook Debugger**:
   ```text
   https://your-domain.com/webhooks/zoom
   ```
2. Paste it into your Zoom Marketplace App under **Feature** → **Event Subscriptions**.
3. Enter your **Secret Token** into ZPM settings.
4. Use the built-in **Live Webhook Debugger** (`/app/api/inbound-webhooks`) to simulate an event and verify the connection.

---

## 5. Schedule Your First Meeting

1. Go to **Book Meeting** (`/app/meetings/create`).
2. Choose a topic, start time, duration, and pool.
3. Click **Confirm & Schedule**. ZPM automatically checks for conflicts, applies the configured buffer window, and provisions the meeting on Zoom!
