# System Requirements

Before deploying Zoom Pool Manager, verify that your hosting environment meets the following specifications.

---

## 🖥️ Server Environment

- **PHP Version**: `8.2.0` or higher (Fully tested on PHP 8.2, 8.3, and 8.4)
- **Web Server**: Apache with `mod_rewrite`, Nginx, or LiteSpeed (cPanel / Hostinger)
- **Database**:
  - MySQL `8.0` or higher
  - MariaDB `10.5` or higher
  - SQLite `3.35` or higher (Supported for testing and lightweight deployments)

---

## 🧩 Required PHP Extensions

Ensure the following standard PHP extensions are enabled in your `php.ini` or cPanel PHP Selector:

| Extension | Purpose | Mandatory? |
| :--- | :--- | :--- |
| `bcmath` | Arbitrary precision mathematics | Yes |
| `ctype` | Character type checking | Yes |
| `curl` | HTTP communication with Zoom API & webhooks | Yes |
| `fileinfo` | File type detection for backups & exports | Yes |
| `json` | JSON payload serialization | Yes |
| `mbstring` | Multibyte string processing | Yes |
| `openssl` | Encrypted credential storage and HTTPS | Yes |
| `pdo` | Database abstraction | Yes |
| `pdo_mysql` | MySQL/MariaDB database driver | Yes (if using MySQL/MariaDB) |
| `tokenizer` | Code parsing and template compilation | Yes |
| `xml` | SAML 2.0 metadata and ICS export | Yes |
| `zip` | Standalone backup creation & update extraction | Yes |

---

## 🔒 Directory Permissions

The following directories must be writable by the web server process (`chmod 775` or `chmod 755` depending on your hosting setup):

```text
storage/
storage/app/
storage/app/backups/
storage/app/updates/
storage/framework/
storage/logs/
bootstrap/cache/
```

> [!TIP]
> The automated **Web Setup Wizard** will check every required extension and directory permission automatically during Step 1 of installation.
