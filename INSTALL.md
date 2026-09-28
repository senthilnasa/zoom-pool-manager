# Zoom Pool Manager (ZPM) — Web Installation & Deployment Guide

This package is a standalone production build of **Zoom Pool Manager**.
All frontend assets are pre-compiled in `public/build/`, and production PHP dependencies are bundled in `vendor/`.

**No command-line setup is required.** Initial deployment and database setup are completed 100% through the browser-based Web Setup Wizard.

---

## System Requirements

- **PHP**: 8.2, 8.3, or 8.4
- **Required PHP Extensions**: `bcmath`, `ctype`, `fileinfo`, `json`, `mbstring`, `openssl`, `pdo`, `pdo_mysql` (or `pdo_sqlite`), `tokenizer`, `xml`, `zip`
- **Database**: MySQL 8.0+, MariaDB 10.5+, or SQLite 3.35+
- **Web Server**: Apache, Nginx, or LiteSpeed / cPanel

---

## 1. Initial Deployment (Web Setup Wizard)

1. **Extract Archive**:
   Upload and extract the release ZIP on your server into your website root directory.

2. **Web Server Document Root**:
   Configure your web server (or cPanel domain document root) to point to the `public/` directory:
   ```text
   /path/to/zoom-pool-manager/public
   ```

3. **Open Your Browser**:
   Navigate to your domain:
   ```text
   https://example.com
   ```
   If the application is not yet configured, you will automatically be redirected to the **Web Setup Wizard**:
   - **Step 1: System Requirements & Permissions Check**  
     Verifies PHP version (>= 8.2), required extensions, and writable permissions on `storage/` and `bootstrap/cache/`.
   - **Step 2: Database Configuration & Live Connection Test**  
     Enter your database credentials (Host, Port, Database, Username, Password) and click **Test Connection**. Once verified, it automatically writes your `.env` file.
   - **Step 3: Automated Migrations & RBAC Seeding**  
     Applies all database tables and seeds canonical system roles and templates.
   - **Step 4: Super Administrator Setup**  
     Creates the primary Super Administrator account with mandatory TOTP authenticator enrollment and backup codes.
   - **Step 5: Completion & Login**  
     Secures the installation with an `installed.lock` file and lands you at the **Login page**.

> **Note**: If `.env` is already configured or the app is already installed, any visit to `example.com` or `example.com/installer` will **automatically redirect directly to `/login`**.

---

## 2. Web Server Configuration

### Nginx
```nginx
server {
    listen 80;
    server_name zoom.yourdomain.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name zoom.yourdomain.com;
    root /var/www/zoom-pool-manager/public;

    index index.php index.html;
    charset utf-8;

    ssl_certificate /etc/letsencrypt/live/zoom.yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/zoom.yourdomain.com/privkey.pem;

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

### Apache (`public/.htaccess`)
An optimized `.htaccess` is included in `public/`. Ensure Apache has `mod_rewrite` enabled.

---

## 3. Background Services

### Cron Scheduler (Crontab)
Run Laravel's task scheduler every minute:
```cron
* * * * * cd /path/to/zoom-pool-manager && php artisan schedule:run >> /dev/null 2>&1
```

### Queue Worker (Supervisor or cPanel Cron)
Process email dispatch, recording sync, and Zoom webhooks:
```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

---

## 4. Future Updates (1-Click in UI)

After the initial installation, you do **not** need to manually download or replace files for subsequent updates:

1. Log in to the application as a **Super Administrator**.
2. Navigate to **System Settings** -> **System Updates**.
3. When a newer version is released on GitHub, click **Update Now**.
4. The live update runner will execute:
   - `Checking latest version...`
   - `Downloading update...` (with SHA-256 verification)
   - `Creating backup...` (automatic database snapshot & file archive)
   - `Installing update...` (safely preserving your `.env` and `storage/`)
   - `Running migrations...`
   - `Restarting application...` (clearing and rebuilding caches)
   - `Verifying installation...`
   - `Update completed successfully.`
5. The application reloads automatically. If an error is encountered during the update, an automatic rollback restores the previous state.
