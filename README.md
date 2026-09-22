# Zoom Pool Manager (ZPM)

<div align="center">

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777BB4.svg)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg)](https://laravel.com)
[![Code Style](https://img.shields.io/badge/Code%20Style-Laravel%20Pint-000000.svg)](https://github.com/laravel/pint)
[![Static Analysis](https://img.shields.io/badge/PHPStan-Level%206-brightgreen.svg)](https://phpstan.org)
[![Automated Tests](https://img.shields.io/badge/Tests-152%20Passed-success.svg)](tests/)

**"Automate Your Zoom Resource Pool"**

Made with ❤️ by **[Senthil Nasa](https://github.com/senthilnasa)**  
Official Repository: **[github.com/senthilnasa/zoom-pool-manager](https://github.com/senthilnasa/zoom-pool-manager)**

*Independent open-source software. Not affiliated with or endorsed by Zoom Video Communications, Inc.*

</div>

---

## 1. Overview

**Zoom Pool Manager (ZPM)** is an independent, single-organization Laravel 12 web application. It centrally manages a shared pool of licensed Zoom accounts (e.g. 20–30 users in a university or corporate department) and automatically allocates them to classes, meetings, and events without exposing account credentials to organizers.

### Key Highlights
- **Modern Gen-Z UI & Glassmorphism Design System:** Built with Tailwind CSS and Alpine.js, featuring backdrop blur cards, ambient glows, micro-interactions, and a responsive left-side navigation sidebar.
- **SPA-Like Micro-Interactions:** Instant live search, animated modals, and floating toast notifications powered by `zpm-spa.js` with zero full-page reloads.
- **Centralized Versioning & Auto-Update Engine:** Seamless updates via official GitHub Releases with update locking (`update.lock`), automated pre-update backups, package integrity/traversal checks, safe database migrations, and both GUI (`/admin/system/updates`) and CLI (`php artisan zpm:update`) interfaces.
- **Concurrency-Safe Atomic Allocation:** Deadlock-free `SELECT ... FOR UPDATE` resource locking with 6-layer policy precedence.
- **Host Control & Security:** JIT `start_url` direct browser redirects (never stored in DB or logs) and automated post-meeting 6-digit numeric host key rotation.
- **Enterprise Governance:** Multi-step sequential approval chains (`ANY` / `ALL`), Anti-Self-Approval enforcement, monthly quotas, and FIFO waitlists.
- **Full Communications & Media:** RFC 5545 `.ics` calendar generator, pluggable email drivers (SMTP, Gmail API, Microsoft Graph), Zoom webhook ingestion, 30-day drift reconciliation, and cloud recording access management.

---

## 2. Quickstart with Docker

1. **Clone the repository:**
   ```bash
   git clone https://github.com/senthilnasa/zoom-pool-manager.git
   cd zoom-pool-manager
   ```

2. **Start the containers:**
   ```bash
   docker compose up -d
   ```

3. **Install dependencies & initialize database:**
   ```bash
   docker exec zpm-app composer install
   docker exec zpm-app cp .env.example .env
   docker exec zpm-app php artisan key:generate
   docker exec zpm-app php artisan migrate --seed
   ```

4. **Run in Demo Mode (Simulated Zoom):**
   ```bash
   docker exec zpm-app php artisan zpm:demo:seed
   ```
   Open `http://localhost:8000` and sign in with:
   - **Administrator:** `admin@demo.local` / `password`
   - **Faculty:** `professor@demo.local` / `password`

---

## 3. Application Versioning & Auto-Updates

### CLI Commands
- **Check application version:**
  ```bash
  php artisan zpm:version
  ```
- **Check for updates on GitHub:**
  ```bash
  php artisan zpm:version --check
  ```
- **Apply update safely:**
  ```bash
  php artisan zpm:update
  ```

### GUI Updates
Authorized administrators can navigate to **Operations → System Updates** (`/admin/system/updates`) to view current and latest versions, inspect release notes, and trigger safe automated updates.

---

## 4. Verification & Testing

ZPM enforces a strict verification gate across all code:
```bash
# Run 152 automated feature and unit tests
docker exec zpm-app ./vendor/bin/pest

# Run code style formatting check (PSR-12)
docker exec zpm-app ./vendor/bin/pint --test

# Run Level 6 static analysis
docker exec zpm-app ./vendor/bin/phpstan analyse
```

---

## 5. Building Production Release Artifacts

To create a zero-dependency standalone production ZIP archive:
```bash
bash tools/build-release.sh
```
The archive (`zpm-release-vX.Y.Z.zip`) and its SHA256 checksum (`.sha256`) will be generated inside `dist/`.

---

## 6. Security & Support

- **Security Policy:** See [SECURITY.md](SECURITY.md) for vulnerability disclosure procedures.
- **Contributing:** See [CONTRIBUTING.md](CONTRIBUTING.md) for development workflows.
- **Changelog:** See [CHANGELOG.md](CHANGELOG.md) for version release notes.
- **License:** [MIT License](LICENSE) © 2026 Senthil Nasa.
