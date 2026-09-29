# Single Sign-On (SSO) & Directory Sync

Zoom Pool Manager integrates with enterprise identity providers (IdPs) via SAML 2.0 and automated directory synchronization protocols (`/app/settings/sso` and `/app/settings/directory-sync`).

---

## 🔑 SAML 2.0 Single Sign-On

ZPM supports all standard identity providers:
- **Microsoft Entra ID (Azure AD)**
- **Google Workspace / Google Cloud Identity**
- **Okta / Auth0**
- **Shibboleth / eduGAIN (Higher Ed InCommon Federation)**

### SP Metadata & Configuration:
- **Entity ID**: `https://zoom.yourdomain.com/saml/metadata`
- **Assertion Consumer Service (ACS) URL**: `https://zoom.yourdomain.com/saml/acs`
- **Single Logout (SLO) URL**: `https://zoom.yourdomain.com/saml/logout`

---

## 🔄 Directory Sync (SCIM & LDAP)

Keep user accounts, faculty departments, and roles in sync with your campus directory:
- **Scheduled Sync Cadence**: Synchronizes new faculty, role changes, and deactivations automatically.
- **Auto-Provisioning**: New users signing in via SSO are provisioned with their designated department and baseline permissions automatically.
- **Deactivation Handling**: Suspended campus directory users have their active meeting reservations reassigned or cancelled according to institutional policy.
