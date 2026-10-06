# Google Workspace Add-on Integration Guide

This guide describes how to configure and deploy the **Zoom Pool Manager (ZPM) Google Workspace Add-on** for **Google Calendar** and **Gmail**.

---

## 1. Overview & Architecture

The Zoom Pool Manager Google Workspace Add-on brings native Zoom meeting scheduling directly into Google Workspace:

1. **Google Calendar Conferencing:**
   - Appears under the standard **"Add video conferencing"** dropdown when editing calendar events.
   - Automatically allocates an available Zoom host from your organization's resource pools.
   - Attaches the dynamic Zoom Join Link, Meeting ID, Passcode, and Host Key PIN directly into the Google Calendar event details.
   - Synchronizes event title, start time, end time, and attendee list with ZPM.

2. **Gmail Compose 1-Click Link Insertion:**
   - Appears as a Zoom icon in the Gmail Compose toolbar and right-hand side panel.
   - Offers a 1-click meeting generator with duration picker (30m, 45m, 1h, 90m, 2h).
   - Injects a formatted responsive HTML invitation block (or plain text) directly into the email body draft.

3. **Enterprise Governance & Security:**
   - **Allowed Domains Whitelist:** Restricts Add-on scheduling exclusively to authorized institutional Google domains (e.g. `yourcompany.com`).
   - **1-Click Token Rotation:** Revoke and generate new API tokens instantly from the ZPM admin UI.
   - **Full Audit Trail:** Cryptographic SHA-256 audit log records every meeting provisioned through Google Calendar and Gmail.

---

## 2. Configuration in Zoom Pool Manager

1. Log in to Zoom Pool Manager as an **Administrator** or **Super Administrator**.
2. In the left navigation menu, go to **Developer & Integrations > Google Workspace Add-on** (`/app/settings/google-workspace`).
3. Verify or configure the following settings:
   - **Server URL:** The publicly reachable HTTPS URL of your ZPM instance (e.g. `https://zpm.yourdomain.com`).
   - **Google Workspace API Token:** The bearer token used by the Add-on to communicate with ZPM.
   - **Allowed Google Account Domains:** Comma-separated list of allowed domains (e.g., `krea.edu.in,alumni.krea.edu.in`).
   - **Default Resource Pool:** Select a specific pool or leave as *Automatic (Cluster Smart Allocation)*.
   - **Default Meeting Template & Duration:** Choose preferred defaults for session parameters.
   - **Share Host Key PIN:** Check if you want the calendar creator to be provided the 6-digit Host Key PIN to claim host in Zoom.
   - **Gmail HTML Insertion Template:** Customize the HTML snippet inserted into drafts.
4. Click **Save Configuration**.
5. Click **Download Add-on Package (.zip)** in the top right to download `zoom-pool-manager-google-workspace-addon.zip`.

---

## 3. Deployment via Google Apps Script (Developer / Pilot Mode)

For testing or deploying to selected pilot users:

1. Extract the downloaded ZIP file. You will see:
   - `appsscript.json` (Add-on manifest defining Calendar conferencing & Gmail compose triggers)
   - `Code.gs` (Apps Script connector code pre-filled with your ZPM server URL and token)
   - `README.md`
2. Open [Google Apps Script](https://script.google.com) and click **+ New project**.
3. Name your project **"Zoom (Zoom Pool Manager)"**.
4. In the left sidebar, click **Project Settings** (Gear icon ⚙️) and check **"Show 'appsscript.json' manifest file in editor"**.
5. Switch back to the **Editor** (`< >`) tab:
   - Open `appsscript.json` and replace its contents with the downloaded `appsscript.json`.
   - Open `Code.gs` and replace its contents with the downloaded `Code.gs`.
6. Click the **Save** disk icon (Ctrl+S / Cmd+S).
7. In the top right, click **Deploy > Test deployments**.
8. Under **Application**, click **Install**.
9. Grant the requested Google Workspace permissions:
   - Access Calendar events (to read event times and inject video conferencing data)
   - Access Gmail drafts (to insert the Zoom meeting card into the compose window)
   - Connect to external services (to call your ZPM API endpoint)
10. Open [Google Calendar](https://calendar.google.com) or [Gmail](https://mail.google.com) to test immediately!

---

## 4. Domain-Wide Deployment for Google Workspace Administrators

To deploy the Add-on automatically to **all users** in your Google Workspace organization without requiring individual installation:

1. **Link Apps Script to Google Cloud Project:**
   - In your Google Apps Script project, go to **Project Settings** > **Google Cloud Platform (GCP) Project** > click **Change project**.
   - Enter your organization's Google Cloud Standard Project Number.
2. **Enable Google Workspace Marketplace SDK:**
   - In Google Cloud Console, navigate to **APIs & Services** > **Library**.
   - Search for **Google Workspace Marketplace SDK** and click **Enable**.
3. **Configure the Marketplace SDK App Configuration:**
   - Under **App Configuration**:
     - Application type: **Google Workspace Add-on**
     - Deployment ID: Enter the Versioned Deployment ID from your Apps Script project (**Deploy > Manage deployments**).
   - Under **App Listing**:
     - Visibility: Select **Private** (Restricted to your Google Workspace domain users).
     - Provide title: *Zoom (Zoom Pool Manager)* and app description.
4. **Deploy via Google Admin Console:**
   - Go to [Google Admin Console](https://admin.google.com).
   - Navigate to **Apps > Google Workspace Marketplace apps > Apps list**.
   - Click **Install app** > select your Private Add-on > choose **Domain Install**.
   - Select Organizational Units (OUs) to install for (e.g. All staff and faculty).
5. The Zoom video conferencing option will instantly appear across Google Calendar and Gmail for all organizational users.

---

## 5. API Endpoints Reference

All requests to the ZPM Google Workspace API endpoints are secured by the configured bearer token:

### 1. `GET /api/v1/integrations/google-workspace/options`
Fetches configuration, active resource pools, and meeting templates.

**Headers:**
```http
Authorization: Bearer <google_workspace_api_token>
```

### 2. `POST /api/v1/integrations/google-workspace/book-conference`
Called by Google Calendar when a user clicks "Zoom Meeting (Zoom Pool Manager)".

**Payload:**
```json
{
  "user_email": "faculty@krea.edu.in",
  "user_name": "Prof. Rajesh Sharma",
  "title": "Advisory Committee",
  "calendar_event_id": "cal_evt_12345",
  "starts_at": "2026-10-15T14:00:00+05:30",
  "duration_minutes": 60,
  "invitees": ["dean@krea.edu.in", "student@krea.edu.in"]
}
```

**Response (`201 Created`):**
```json
{
  "success": true,
  "meeting": {
    "id": 142,
    "public_id": "01K99...",
    "title": "Advisory Committee",
    "zoom_meeting_id": "89167758868",
    "join_url": "https://zoom.us/j/89167758868",
    "passcode": "513720",
    "host_key": "876543",
    "starts_at": "2026-10-15T14:00:00+05:30",
    "ends_at": "2026-10-15T15:00:00+05:30",
    "timezone": "Asia/Kolkata"
  },
  "conference_data": {
    "conference_id": "89167758868",
    "entry_points": [
      {
        "entry_point_type": "VIDEO",
        "uri": "https://zoom.us/j/89167758868",
        "label": "Join Zoom Meeting",
        "meeting_code": "89167758868",
        "password": "513720",
        "pin": "876543"
      }
    ],
    "notes": "Zoom Meeting ID: 89167758868\nPasscode: 513720\nHost Key: 876543"
  }
}
```

### 3. `POST /api/v1/integrations/google-workspace/book-link`
Called by Gmail Compose when a user clicks "Generate & Insert Link".

**Payload:**
```json
{
  "user_email": "admissions@krea.edu.in",
  "user_name": "Admissions Officer",
  "title": "Interview Discussion",
  "duration_minutes": 30
}
```

**Response (`201 Created`):**
Returns the created meeting object along with rendered `email_snippets` (both responsive HTML and plain text) for in-place insertion into the Gmail draft.
