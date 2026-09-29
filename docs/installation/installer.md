# Web Setup Wizard & Environment Config

Zoom Pool Manager features a dedicated, zero-command-line web installer that automatically launches when an unconfigured instance is visited for the first time.

---

## 🧙‍♂️ Step-by-Step Installation Flow

### Step 1: System Requirements & Permissions Check
The wizard scans your environment and validates:
- PHP version $\ge 8.2.0$.
- Required PHP extensions (`pdo_mysql`, `curl`, `mbstring`, `zip`, `openssl`, etc.).
- Read/write permissions for `storage/` and `bootstrap/cache/`.

### Step 2: Database Credentials & Live Connection Test
You are presented with a clean connection form:
- **Database Driver**: `mariadb`, `mysql`, or `sqlite`
- **Host**: `127.0.0.1` or `localhost`
- **Port**: `3306`
- **Database Name**: The name of your database
- **Username & Password**: Credentials with full schema privileges

Clicking **Test Connection** tests the credentials on the fly without reloading the page. Once verified, ZPM safely writes the credentials to `.env`.

### Step 3: Schema Migrations & System Seeders
With verified credentials, clicking **Run Database Setup** will:
- Apply all database migrations.
- Seed the 9 canonical system roles (`Super Administrator`, `IT Administrator`, `Department Lead`, etc.).
- Pre-populate default security profiles and meeting templates.

### Step 4: Super Administrator Setup & Mandatory 2FA
- Specify the primary administrator name and institutional email.
- Set a strong password.
- **Mandatory TOTP Enrollment**: An interactive QR code is rendered for Google Authenticator, Authy, or 1Password.
- **Single-Use Emergency Recovery Codes**: A set of emergency backup codes is displayed to safeguard against lost authenticators.

### Step 5: Lock & Security Finalization
The installer creates `storage/installed.lock`. Once this lock exists:
- All `/installer` routes are permanently blocked and redirect to `/login`.
- Session drivers transition to the primary secure store.
