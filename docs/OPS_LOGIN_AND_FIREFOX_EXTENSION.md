# gov.cabnet.app — Ops Login and Firefox Helper Deployment

## Purpose

This patch replaces IP-only access with an authenticated operator login layer for `gov.cabnet.app` PHP tools.

The patch does **not** enable automatic EDXEIX submission. It only controls access to the existing tools.

## Security posture

- All public PHP requests are guarded by `public_html/gov.cabnet.app/.user.ini` using `auto_prepend_file`.
- `/ops/login.php` and `/ops/logout.php` remain public so staff can authenticate.
- All other PHP endpoints require either:
  - an authenticated operator session, or
  - a matching `X-Gov-Cabnet-Key` header using `app.internal_api_key` from server-only config.
- CLI execution is not affected.
- Passwords are stored with PHP `password_hash()`.
- Failed login attempts are recorded and rate-limited.
- Login/logout events are written to `ops_audit_log`.

## Deployment order

1. Upload the patch files.
2. Run the SQL migration.
3. Create the first operator user from CLI.
4. Test login in a private/incognito browser window.
5. Only after login works, remove the old IP restriction from live `.htaccess` or cPanel IP blocker.

## Create first operator user

```bash
php /home/cabnet/gov.cabnet.app_app/cli/create_ops_user.php \
  --username=andreas \
  --email=YOUR_EMAIL_HERE \
  --display-name="Andreas" \
  --role=admin
```

The script prompts for the password without printing it.

## Firefox helper

The authenticated page is:

```text
https://gov.cabnet.app/ops/firefox-extension.php
```

It packages files from:

```text
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

Expected files:

```text
manifest.json
edxeix-fill.js
gov-capture.js
README.md
```

Temporary Firefox loading still requires a local file selection through `about:debugging`. A permanent server-hosted install requires a signed XPI or enterprise policy deployment.
