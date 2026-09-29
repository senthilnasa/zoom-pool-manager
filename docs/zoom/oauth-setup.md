# Server-to-Server OAuth Setup

Zoom Pool Manager utilizes Zoom's **Server-to-Server (S2S) OAuth** authentication model. Unlike user-managed OAuth apps, Server-to-Server OAuth operates with account-level administrative permissions and does not require individual faculty member logins.

---

## 🛠️ Step 1: Create a Server-to-Server App in Zoom Marketplace

1. Navigate to the [Zoom App Marketplace](https://marketplace.zoom.us/) and sign in with an account that has **Account Owner** or **Admin** privileges.
2. In the top-right header, click **Develop** → **Build App**.
3. Locate the **Server-to-Server OAuth** card and click **Create**.
4. Enter an App Name (e.g. `University Zoom Pool Manager`) and click **Create**.

---

## 📋 Step 2: Retrieve App Credentials

1. In the left navigation of your newly created app, click **App Credentials**.
2. Note the following three credentials:
   - **Account ID**
   - **Client ID**
   - **Client Secret**

---

## ℹ️ Step 3: Add Basic Information

1. Under the **Information** tab, enter a short description and developer contact name and email.
2. Under the **Feature** tab, you can enable **Event Subscriptions** (see [Webhook Intake & CRC Handshake](webhooks.md)).

---

## 🛡️ Step 4: Add Granular Scopes

Navigate to the **Scopes** tab and click **Add Scopes**. Select the required permissions (see [Zoom App Scopes Reference](scopes.md)).

---

## 🚀 Step 5: Activate the App

Navigate to the **Activation** tab and click **Activate your app**. Your Zoom App is now ready to interface with Zoom Pool Manager.
