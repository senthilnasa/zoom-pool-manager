# Database Connection & Host Denied Fixes

Resolution steps for database authentication and host permission issues.

---

## 🚫 `SQLSTATE[HY000] [1045] Access denied for user 'user'@'host'`

This error indicates that the database server rejected the username, password, or host permission.

### On Shared Hosting (Hostinger, cPanel):
1. **User Prefix Check**: Shared hosting often prepends a customer ID (e.g. `u253658055_zpm` instead of `zpm`). Verify the full username in your control panel.
2. **Database Permissions**: Ensure the user has been added to the database with **All Privileges**.
3. **Host Address**:
   - In 95% of shared environments, the database host must be set to `127.0.0.1` or `localhost`.
   - If `127.0.0.1` fails with Access Denied, try `localhost` (which utilizes the Unix socket).
4. **Testing in the Web Installer**:
   - Re-visit `https://zoom.yourdomain.com/installer/database`.
   - Enter your credentials and use the **Test Connection** button to verify connectivity before proceeding.

---

## ⏳ Database Lock Wait Timeout

If a transaction takes too long during peak hours:
- Ensure database tables use the **InnoDB** storage engine (ZPM migrations automatically enforce InnoDB).
- Avoid long-running external transactions that hold row-level locks on `zoom_resources`.
