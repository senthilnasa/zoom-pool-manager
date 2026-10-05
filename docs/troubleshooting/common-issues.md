# Common Deployment Issues & Fixes

Quick diagnostic solutions for frequent server and configuration issues.

---

## 1. HTTP 500 / Blank Screen on Initial Visit

- **Cause**: PHP extensions are missing or storage permissions are restrictive.
- **Fix**:
  1. Ensure PHP version is `8.2` or higher (`php -v`).
  2. Ensure `storage/` and `bootstrap/cache/` are writable (`chmod -R 775 storage bootstrap/cache`).
  3. Inspect `storage/logs/laravel.log` for the exact error message.

---

## 2. 404 Not Found on SPA Routes (`/app/meetings`, `/app/pools`)

- **Cause**: Web server URL rewriting is disabled or document root is misconfigured.
- **Fix**:
  - **Apache**: Verify that `mod_rewrite` is active and that `public/.htaccess` exists. Ensure `AllowOverride All` is set in your Apache virtual host.
  - **Nginx**: Ensure your server block includes:
    ```nginx
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    ```

---

## 3. Zoom API Returns `401 Invalid Token` or `400 Bad Request`

- **Cause**: Incorrect Account ID, Client ID, or Client Secret.
- **Fix**:
  1. Open Zoom Marketplace → Your App → **App Credentials**.
  2. Verify that **Account ID**, **Client ID**, and **Client Secret** match exactly with zero leading/trailing spaces.
  3. Ensure the app has been toggled to **Activated** on Zoom Marketplace.

---

## 4. Zoom Webhook CRC Handshake Fails

- **Cause**: Webhook Secret Token mismatch.
- **Fix**:
  1. Open Zoom Marketplace → Feature → Event Subscriptions → Secret Token.
  2. In ZPM, go to **System Settings** → **Zoom Settings** and paste the identical Secret Token.
  3. Open the **Webhook Debugger** (`/app/api/inbound-webhooks`) and click **Test CRC Handshake** to verify signature computation.

---

## 5. Google Sign-In Error 400: `redirect_uri_mismatch`

- **Cause**: The redirect URI passed in the authorization request does not match the Authorized redirect URIs configured in Google Cloud Console.
- **Fix**:
  1. Open [Google Cloud Console](https://console.cloud.google.com/) → **APIs & Services** → **Credentials**.
  2. Click on your OAuth 2.0 Client ID.
  3. Under **Authorized redirect URIs**, ensure you have added:
     ```text
     https://zoom.yourdomain.com/auth/google/callback
     ```
     *(Ensure matching protocol `https://`, correct domain, and no trailing slash)*
  4. Under **Authorized JavaScript origins**, ensure you have added:
     ```text
     https://zoom.yourdomain.com
     ```
  5. Click **Save** and wait 2–5 minutes for Google's global routing cache to propagate.
