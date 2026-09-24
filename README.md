# Zoom Pool Manager (ZPM)

<div align="center">

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.2%20%7C%208.3%20%7C%208.4-777BB4.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg)](https://laravel.com)
[![Vue 3](https://img.shields.io/badge/Vue.js-3.x-4FC08D.svg)](https://vuejs.org/)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind-3.x-38B2AC.svg)](https://tailwindcss.com/)
[![Code Style](https://img.shields.io/badge/Code%20Style-Laravel%20Pint-000000.svg)](https://github.com/laravel/pint)
[![Static Analysis](https://img.shields.io/badge/PHPStan-Level%206-brightgreen.svg)](https://phpstan.org)
[![Automated Tests](https://img.shields.io/badge/Tests-214%20Passed%20(1132%20assertions)-success.svg)](tests/)
[![Docker Ready](https://img.shields.io/badge/Docker-Ready-2496ED.svg)](docker-compose.yml)

### Enterprise-Grade Intelligent Resource Pooling & Automated Scheduling for Zoom

Made with ❤️ by **[Senthil Nasa](https://github.com/senthilnasa)**  
Official Repository: **[https://github.com/senthilnasa/zoom-pool-manager](https://github.com/senthilnasa/zoom-pool-manager)**

*Independent open-source software. Not affiliated with or endorsed by Zoom Video Communications, Inc.*

---

[Key Features](#-key-features) •
[Architecture](#-architecture--technology-stack) •
[Quickstart](#-quickstart-with-docker) •
[Production Deployment](#-production-deployment-guide) •
[Configuration](#-configuration--environment-variables) •
[API & Webhooks](#-rest-api--webhooks) •
[Security](#-security--governance) •
[Contributing](#-contributing--license)

</div>

---

## 📖 Overview

**Zoom Pool Manager (ZPM)** is an independent, single-organization, enterprise-class web application designed for institutions (universities, colleges, research institutes, and enterprises) that possess a finite pool of licensed Zoom host accounts (e.g. 20–50 Pro/Business licenses) but serve hundreds or thousands of faculty, staff, and organizers.

Instead of purchasing expensive dedicated licenses for every user or manually juggling shared passwords, **ZPM treats licensed Zoom accounts as a dynamic, pooled computing resource**. It automatically matches incoming meeting requests and recurring lecture schedules to available Zoom hosts, generates Zoom meetings on the fly via Zoom Server-to-Server (S2S) OAuth APIs, provisions calendar invites (`.ics`), enforces buffer times, tracks attendance telemetry, and frees licenses immediately when meetings conclude.

### Why Choose ZPM?

- 💰 **Maximize License ROI:** Share 20 Zoom licenses across 500+ instructors and departments with zero scheduling collisions.
- 🔒 **Zero Host Password Exposure:** Organizers receive join links and one-click 6-digit Host Key PINs to start and claim host control directly inside Zoom client—without ever knowing the host account's password.
- ⚡ **Concurrency-Safe Atomic Allocation:** Uses database-level `SELECT ... FOR UPDATE` locking to guarantee zero double-bookings or race conditions.
- 🔄 **Full Recurring Series Engine:** Full RFC 5545 recurrence expansion (Daily, Weekly, Monthly) with single-resource dedication or pool-wide smart reallocation.
- 🏢 **Enterprise Directory Sync & SSO:** Out-of-the-box integration with Microsoft Entra ID (Azure AD), Google Workspace, and LDAP / Active Directory with automatic designation and department ingestion.
- 🎨 **Modern Gen-Z Glassmorphism SPA:** Single Page Application built on Vue 3 + Tailwind CSS featuring instant search across all 28 modules, light/dark themes, anti-FOUC scripts, and live status polling.

---

## 🚀 Key Features

```
┌────────────────────────────────────────────────────────────────────────┐
│                        ZOOM POOL MANAGER (ZPM)                         │
├───────────────────┬───────────────────┬────────────────────────────────┤
│  RESOURCE POOLING │ MEETING LIFECYCLE │     ENTERPRISE GOVERNANCE      │
│  • Atomic Locks   │ • Quick Booking   │  • Directory Sync (LDAP/Entra) │
│  • Buffer Padding │ • Reschedule/Edit │  • Visual Permissions Matrix   │
│  • Auto Rotation  │ • +15m/+30m Extend│  • Anti-Self-Approval Chain    │
│  • Pool Strategies│ • Attendance Sync │  • Quotas & Blackout Windows   │
│  • Drift Healing  │ • Cloud Recordings│  • Cryptographic Audit Trail   │
└───────────────────┴───────────────────┴────────────────────────────────┘
```

### 1. Intelligent Resource Allocation Engine
- **Atomic Concurrency Protection:** Deadlock-free row-level locking ensures that concurrent meeting requests never collide on the same Zoom license.
- **Configurable Pool Strategies:**
  - `least_hours_today`: Balances total occupied meeting time evenly across hosts.
  - `least_meetings_today`: Distributes meeting count to prevent license overuse.
  - `priority`: Allocates licenses by predefined resource priority order.
  - `random`: Randomized balanced allocation across active hosts.
- **Safety Buffers:** Automated pre- and post-meeting buffer periods (5–30 minutes) prevent back-to-back overlaps and allow host tear-down.

### 2. Comprehensive Meeting Lifecycle & Access Controls
- **One-Click Quick Booking & Interactive Calendar:** Timeline, Day, 3-Day, and Week resource views with drag-free slot reservation.
- **Meeting Reschedule & In-Place Editing:** Update dates, times, descriptions, passcodes, and security settings with automated conflict re-validation and Zoom API patching.
- **Active Session Extension (+15m / +30m):** Extend live meetings on demand if subsequent resource windows are clear.
- **Early Release:** Organizers can conclude meetings early, instantly releasing the host license back to the pool for waiting requests.
- **Formatted Invitation Generator:** One-click copy for email, WhatsApp, or Slack containing Topic, Time, Timezone, Join URL, Meeting ID, Passcode, and Host Key instructions.
- **Extended Room Security & Telemetry Flags:**
  - **Waiting Room:** Toggle Zoom Waiting Room admission.
  - **Auto-Start Without Host (`join_before_host`):** Enable participants to start sessions with configurable lead times (anytime, 5m, 10m, 15m).
  - **Host Key PIN Sharing:** Automated delegation of 6-digit Host Keys with claim host instructions (`Participants > Claim Host > Enter PIN`).
  - **Recording Mode:** Configurable per meeting (`None`, `Cloud Recording`, `Local Recording`).
  - **Attendance Tracking:** Participant join/leave telemetry tracking with duration analysis and CSV/Excel exports.

### 3. Directory Sync, SSO & Custom RBAC
- **Directory Synchronization:** Synchronize users, designations, and departments from Microsoft Entra ID (Azure AD), Google Workspace, or LDAP / Active Directory on scheduled intervals or on-demand.
- **Single Sign-On (SSO):** SAML 2.0 and OAuth 2.0 (Google, Microsoft) with JIT (Just-In-Time) user provisioning and domain whitelisting.
- **Custom Role Management:** Create and manage customized roles with a comprehensive, visual **Permissions Matrix** spanning 36 granular permissions.
- **Anti-Self-Approval:** Architectural security rule that strictly prohibits organizers from approving their own meeting requests.

### 4. Operations, Maintenance & Developer API
- **Universal Search:** Instant search filter input across every administrative and operational table view (28 modules).
- **Cryptographic Audit Trail:** Tamper-evident audit logging with SHA-256 HMAC cryptographic chain verification.
- **Automated Health Heartbeat:** Diagnostics endpoint, database latency monitor, Zoom token freshness test, and queue status check.
- **Full REST API (v1):** Authenticated with scoped API tokens (`Bearer zpm_...`) for automated scheduling from external LMS (Canvas, Moodle, Blackboard) or ERP systems.
- **Signed Outbound Webhooks:** Event notifications (`meeting.scheduled`, `meeting.started`, `meeting.completed`) dispatched with HMAC-SHA256 signatures.

---

## 🏗 Architecture & Technology Stack

| Layer | Technologies / Components |
| :--- | :--- |
| **Backend Framework** | [Laravel 12.x](https://laravel.com) (PHP 8.2 / 8.3 / 8.4) |
| **Frontend Framework** | [Vue 3](https://vuejs.org) (Composition API, `<script setup>`), [Vue Router 4](https://router.vuejs.org/) |
| **Styling & Design** | [Tailwind CSS 3.x](https://tailwindcss.com), Glassmorphism, [Lucide Icons](https://lucide.dev) |
| **Build Tooling** | [Vite 5.x](https://vitejs.dev) |
| **Database** | MySQL 8.0+ / PostgreSQL 15+ / SQLite 3.35+ |
| **Caching & Queues** | Redis 7+ or Database Queues |
| **API Integration** | Zoom Server-to-Server (S2S) OAuth REST API v2 |
| **Quality & Linting** | [Laravel Pint](https://github.com/laravel/pint), [PHPStan Level 6](https://phpstan.org), [PHPUnit / Pest](https://pestphp.com) |

---

## ⚡ Quickstart with Docker

The fastest way to evaluate or run Zoom Pool Manager locally is using the included Docker Compose configuration.

### Prerequisites
- [Docker](https://docs.docker.com/get-docker/) & [Docker Compose](https://docs.docker.com/compose/)
- Git

### 1. Clone & Launch
```bash
git clone https://github.com/senthilnasa/zoom-pool-manager.git
cd zoom-pool-manager

# Start containers in background (App, Nginx Web, and MySQL Database)
docker compose up -d
```

### 2. Initialize Application
```bash
# Install PHP dependencies
docker compose exec app composer install

# Set up environment file
docker compose exec app cp .env.example .env
docker compose exec app php artisan key:generate

# Run database migrations and seed default permissions
docker compose exec app php artisan migrate --seed

# Build frontend production assets
npm install && npm run build
```

### 3. Seed Demo Data (Optional for Evaluation)
```bash
# Seeds realistic sample departments, users, pools, and mock Zoom hosts
docker compose exec app php artisan zpm:demo:seed
```

Open your browser and navigate to **`http://localhost:8000`**:
- **Super Administrator:** `admin@demo.local` / `password`
- **Faculty / Organizer:** `professor@demo.local` / `password`

---

## 📦 Production Deployment Guide

For deploying ZPM to a production Linux server (Ubuntu 22.04 / 24.04 LTS):

### 1. Server Prerequisites
```bash
sudo apt update && sudo apt install -y nginx php8.3-fpm php8.3-cli php8.3-mysql \
    php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip php8.3-bcmath php8.3-intl \
    php8.3-ldap git supervisor mysql-server
```

### 2. Code Deployment & Optimization
```bash
cd /var/www
git clone https://github.com/senthilnasa/zoom-pool-manager.git zpm
cd zpm

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
# Edit .env with production database and Zoom credentials:
nano .env

php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link

# Cache configuration, routes, and views for optimal performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 3. Nginx VirtualHost Configuration
Create `/etc/nginx/sites-available/zpm`:
```nginx
server {
    listen 80;
    server_name zpm.institution.edu;
    root /var/www/zpm/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    index index.php;
    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```
Enable site and restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/zpm /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl restart nginx
```

### 4. Background Worker (Supervisor)
Create `/etc/supervisor/conf.d/zpm-worker.conf`:
```ini
[program:zpm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/zpm/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/zpm/storage/logs/worker.log
stopwaitsecs=3600
```
Update Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start zpm-worker:*
```

### 5. Scheduled Automation (Cron)
Add the Laravel schedule runner to `crontab -e -u www-data`:
```cron
* * * * * cd /var/www/zpm && php artisan schedule:run >> /dev/null 2>&1
```

---

## ⚙️ Configuration & Environment Variables

Key settings configured in `.env`:

| Variable | Description | Default / Example |
| :--- | :--- | :--- |
| `APP_ENV` | Application environment | `production` |
| `APP_DEBUG` | Debug mode (always `false` in production) | `false` |
| `APP_URL` | Public canonical URL | `https://zpm.institution.edu` |
| `DB_CONNECTION` | Database engine (`mysql`, `pgsql`, `sqlite`) | `mysql` |
| `ZOOM_ACCOUNT_ID` | Zoom S2S OAuth Account ID | `your_account_id` |
| `ZOOM_CLIENT_ID` | Zoom S2S OAuth Client ID | `your_client_id` |
| `ZOOM_CLIENT_SECRET` | Zoom S2S OAuth Client Secret | `your_client_secret` |
| `ZOOM_WEBHOOK_SECRET_TOKEN` | Secret token for validating inbound Zoom webhooks | `your_webhook_token` |
| `MAIL_MAILER` | Mail driver (`smtp`, `sendmail`, `log`) | `smtp` |

---

## 🔑 Zoom S2S OAuth Setup

1. Sign in to the [Zoom App Marketplace](https://marketplace.zoom.us).
2. Click **Develop → Build App** and select **Server-to-Server OAuth**.
3. Note your **Account ID**, **Client ID**, and **Client Secret**.
4. In **Scopes**, add the following required permissions:
   - `meeting:write:admin` (Create, update, and manage meetings)
   - `meeting:read:admin` (Inspect scheduled meetings)
   - `user:read:admin` (Discover and sync licensed Zoom users)
   - `recording:read:admin` (Discover cloud recordings)
   - `report:read:admin` (Attendance telemetry and participant sync)
5. In **Feature → Event Subscriptions**, add an endpoint:
   - URL: `https://zpm.institution.edu/webhooks/zoom`
   - Events: `Meeting has been started`, `Meeting has been ended`, `All Recording files have completed`
   - Copy the **Verification Token** to `ZOOM_WEBHOOK_SECRET_TOKEN`.
6. Enter credentials in ZPM under **Settings → Zoom Settings** and click **Test Connection**.

---

## 🌐 REST API & Webhooks

ZPM provides a full RESTful API for integrating institutional learning systems (Canvas LMS, Moodle, Blackboard) or custom portals.

### Authentication
Authenticate API requests using API tokens passed in the `Authorization` header:
```bash
curl -X GET "https://zpm.institution.edu/api/v1/availability?starts_at=2026-10-01T10:00:00Z&ends_at=2026-10-01T11:00:00Z" \
  -H "Authorization: Bearer zpm_your_scoped_api_key" \
  -H "Accept: application/json"
```

### Core API Endpoints
- `GET /api/v1/availability` — Check pooled resource availability for a timeframe.
- `GET /api/v1/meetings` — List scheduled meetings with filtering and pagination.
- `POST /api/v1/meetings` — Book a meeting with pool allocation, waiting room, JBH, and host key.
- `GET /api/v1/meetings/{publicId}` — Inspect meeting details and Zoom join credentials.
- `POST /api/v1/meetings/{publicId}/cancel` — Cancel a meeting and immediately release resources.
- `GET /api/v1/recordings` — Access synchronized cloud recordings.

---

## 🛡 Security & Governance

- **Credential Isolation:** Host Zoom account credentials (passwords, master emails) are strictly sequestered from organizers.
- **Log Sanitization:** Sensitive credentials (`client_secret`, passwords, host keys, auth tokens) are automatically redacted from system logs via [`LogRedactionProcessor`](app/Domain/Audit/Services/LogRedactionProcessor.php).
- **HMAC Webhook Ingestion:** Inbound Zoom webhooks verify signature freshness (`<= 300s`) and constant-time HMAC-SHA256 signatures to eliminate replay attacks.
- **Granular RBAC:** Complete Spatie Permission matrix with 36 distinct permissions across 9 functional categories.
- **Anti-Self-Approval:** Prevents users from approving their own meeting or quota override requests.

---

## 🧪 Testing & Code Quality

Zoom Pool Manager maintains strict quality verification gates:

```bash
# Run the complete automated test suite (214 tests, 1,132 assertions)
docker compose exec app php artisan test

# Verify PHPStan Level 6 static analysis across all files (0 errors)
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=512M

# Check code formatting with Laravel Pint
docker compose exec app ./vendor/bin/pint --test

# Compile frontend production bundle with Vite
npm run build
```

---

## 🤝 Contributing & License

Contributions, bug reports, and feature suggestions are welcome! Please review [CONTRIBUTING.md](CONTRIBUTING.md) and [SECURITY.md](SECURITY.md) before opening a pull request.

This project is open-source software licensed under the **[MIT License](LICENSE)**.

---

<div align="center">

**Zoom Pool Manager (ZPM)**  
Crafted with passion and precision by **[Senthil Nasa](https://github.com/senthilnasa)**  
[GitHub](https://github.com/senthilnasa) • [Issues](https://github.com/senthilnasa/zoom-pool-manager/issues) • [Releases](https://github.com/senthilnasa/zoom-pool-manager/releases)

</div>
