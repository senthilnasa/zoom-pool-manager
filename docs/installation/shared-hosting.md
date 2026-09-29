# Shared Hosting Deployment (Hostinger / cPanel / Plesk)

Zoom Pool Manager is engineered specifically to run out-of-the-box on shared hosting environments with **Zero CLI requirement**. You do not need SSH access or terminal commands to install and run ZPM.

---

## 🚀 1. Upload & Extract Package

1. Download the latest pre-packaged release: `zoom-pool-manager-vX.X.X.zip` from [GitHub Releases](https://github.com/senthilnasa/zoom-pool-manager/releases).
2. Log in to your hosting control panel (e.g. Hostinger hPanel, cPanel File Manager).
3. Upload the ZIP archive to your website directory (e.g. `public_html/`).
4. Extract the contents.

---

## 📂 2. Configure Document Root

For maximum security and proper Laravel routing, your web server must point to the `public/` directory:

- **Hostinger**:
  In hPanel → **Websites** → **Manage** → **General** → Change **Document Root** to `public_html/public`.
- **cPanel**:
  In cPanel → **Domains** → Edit the document root of your subdomain/domain to `/home/username/public_html/public`.

### If You Cannot Change Document Root:
If your shared hosting forces the document root to stay strictly at `public_html/`, move the contents of `public/` into `public_html/`, and place all other folders (like `app/`, `bootstrap/`, `vendor/`) one level above in your private home folder.

---

## 🗄️ 3. Create a MySQL / MariaDB Database

1. In your hosting panel, create a new MySQL database (e.g., `u12345_zpm`).
2. Create a database user with a strong password (e.g., `u12345_zpmuser`).
3. Assign the user to the database with **All Privileges**.

> [!WARNING]
> On shared hosts (such as Hostinger), database users often connect through `127.0.0.1` or `localhost`. Ensure you note the exact Database Name and Username prefixes assigned by your host.

---

## 🧙‍♂️ 4. Run the Web Setup Wizard

1. Open your browser and navigate to your website:
   ```text
   https://zoom.yourdomain.com
   ```
2. ZPM will automatically initialize the environment (`.env` and `APP_KEY`), bypass database session locks, and launch the **Web Setup Wizard**:
   - **Step 1**: Requirements & Permissions Verification.
   - **Step 2**: Enter your database Host (`127.0.0.1` or `localhost`), Database Name, Username, and Password. Click **Test Connection**.
   - **Step 3**: Click **Run Migrations** to build the database schema automatically.
   - **Step 4**: Enter your Super Administrator name, email, and password. Scan the QR code with Google Authenticator or 1Password to set up MFA.
   - **Step 5**: Complete! The system writes `storage/installed.lock` and redirects you to the login screen.

---

## ⏱️ 5. Setting Up Scheduled Tasks (Cron)

In your cPanel or hPanel Cron Jobs manager, add a single cron entry that runs every minute:

```bash
* * * * * cd /home/u253658055/domains/zoom.yourdomain.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

This single command handles:
- Meeting host key rotations.
- Cloud recording sync.
- Zoom drift reconciliation.
- Automated database backups.
