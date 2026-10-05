# Single Sign-On (SSO) & Directory Sync

Zoom Pool Manager integrates with enterprise identity providers (IdPs) via Google Workspace (OAuth 2.0 / OIDC), Microsoft Entra ID (Azure AD), SAML 2.0, and automated directory synchronization protocols (`/app/settings/sso` and `/app/settings/directory-sync`).

---

## 🌐 Google Workspace / Google Cloud Identity (OAuth 2.0 / OIDC)

ZPM allows faculty, staff, and students to authenticate seamlessly using their Google Workspace or Google Cloud accounts with automatic Just-In-Time (JIT) provisioning.

### 🛠️ Google Cloud Console Setup

1. **Open Google Cloud Console**:
   Navigate to [Google Cloud Console](https://console.cloud.google.com/) and select or create your institution's project.
2. **Configure OAuth Consent Screen**:
   - Go to **APIs & Services** → **OAuth consent screen**.
   - User Type: Select **Internal** (restricted to your Google Workspace organization users) or **External**.
   - Fill in App Name (e.g., `Zoom Pool Manager`), user support email, and developer contact information.
   - Click **Save and Continue**.
3. **Create OAuth 2.0 Client ID**:
   - Go to **APIs & Services** → **Credentials**.
   - Click **+ Create Credentials** → **OAuth client ID**.
   - Application type: Select **Web application**.
   - Name: `Zoom Pool Manager Web Client`.
   - **Authorized JavaScript origins**:
     ```text
     https://zoom.yourdomain.com
     ```
     *(Replace with your actual ZPM root URL; do not include a trailing slash)*
   - **Authorized redirect URIs**:
     ```text
     https://zoom.yourdomain.com/auth/google/callback
     ```
     *(Required by Google: Enter the exact callback URL above. You may also add `https://zoom.yourdomain.com/auth/{provider_id}/callback`)*
   - Click **Create**.
4. **Copy Credentials**:
   - Note the generated **Client ID** (e.g., `xxxx.apps.googleusercontent.com`) and **Client Secret**.

### ⚙️ Configure in Zoom Pool Manager

1. In ZPM, navigate to **System Settings** → **SSO Identity Providers** (`/app/settings/sso`).
2. Click **+ Add Identity Provider**.
3. Set **Provider Type** to `Google Workspace (OAuth 2.0 / OIDC)`.
4. Enter a descriptive name (e.g., `University Google Workspace`).
5. Paste your **Client ID** and **Client Secret**.
6. *(Optional)* **Restricted Email Domains**: Specify authorized domains (e.g., `university.edu, college.edu`). Sign-ins from unauthorized Gmail or external Google domains will be rejected.
7. Click **Save Provider**.

> [!TIP]
> **Quick Copy in Dashboard**: When configuring Google Workspace in ZPM, your exact **Authorized Redirect URI** is automatically calculated and shown with a 1-click **Copy** button inside the modal dialog.

---

## 🔷 Microsoft Entra ID (Azure AD OAuth 2.0)

Enable single sign-on using Microsoft 365 / Microsoft Entra ID institutional credentials.

### 🛠️ Azure Portal Setup

1. Sign in to the [Azure Portal](https://portal.azure.com/) or [Microsoft Entra Admin Center](https://entra.microsoft.com/).
2. Navigate to **App registrations** → **New registration**.
3. Name: `Zoom Pool Manager`.
4. Supported account types: Select **Accounts in this organizational directory only** (Single tenant) or **Multitenant**.
5. **Redirect URI (optional during creation, required for sign-in)**:
   - Platform: **Web**.
   - Redirect URI:
     ```text
     https://zoom.yourdomain.com/auth/microsoft/callback
     ```
6. Click **Register**.
7. Under **Certificates & secrets** → **Client secrets**, click **+ New client secret** and copy the generated secret value.
8. Under **Overview**, copy your **Application (client) ID** and **Directory (tenant) ID**.

### ⚙️ Configure in Zoom Pool Manager

1. In ZPM, go to **System Settings** → **SSO Identity Providers** → **+ Add Identity Provider**.
2. Select **Microsoft Entra ID / Azure AD (OAuth 2.0)**.
3. Enter your **Tenant ID**, **Application (Client) ID**, and **Client Secret**.
4. Save the provider.

---

## 🔑 SAML 2.0 Single Sign-On (Okta, Shibboleth, InCommon)

ZPM supports all standard SAML 2.0 federation identity providers:
- **Microsoft Entra ID (Azure AD SAML)**
- **Google Workspace (SAML Application)**
- **Okta / Auth0**
- **Shibboleth / eduGAIN (Higher Ed InCommon Federation)**

### SP Metadata & Endpoints:
- **Entity ID**: `https://zoom.yourdomain.com/saml/metadata`
- **Assertion Consumer Service (ACS) URL**: `https://zoom.yourdomain.com/saml/acs`
- **Single Logout (SLO) URL**: `https://zoom.yourdomain.com/saml/logout`
- **SP Metadata XML Download**: Available directly via `/spa/settings/identity-providers/{provider_id}/sp-metadata` or the **View SP Metadata** button in the dashboard.

---

## 🔄 Directory Sync (SCIM & LDAP)

Keep user accounts, faculty departments, and roles in sync with your campus directory:
- **Scheduled Sync Cadence**: Synchronizes new faculty, role changes, and deactivations automatically via scheduled background jobs (`zpm:directory:sync`).
- **Auto-Provisioning**: New users signing in via SSO are provisioned with their designated department and baseline permissions automatically.
- **Deactivation Handling**: Suspended campus directory users have their active meeting reservations reassigned or cancelled according to institutional policy.

---

## ❓ Troubleshooting SSO

### Error 400: `redirect_uri_mismatch` (Google)
- **Cause**: The Redirect URI registered in Google Cloud Console does not match the exact URL from which the request originated.
- **Solution**: Ensure your **Authorized redirect URIs** in Google Cloud Console includes `https://<your-domain>/auth/google/callback` with matching protocol (`https://`), port, and domain.

### Email Domain [@domain.com] is not authorized
- **Cause**: The signing-in user's Google or Microsoft email does not match the **Allowed Domains** configured in ZPM.
- **Solution**: Edit the identity provider in ZPM and add the user's domain to the whitelist, or leave the whitelist blank to allow all authenticated institutional users.
