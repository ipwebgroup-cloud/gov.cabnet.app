# gov.cabnet.app patch — Ops login system and Firefox helper download

## What changed

- Adds a plain PHP/mysqli operator login system.
- Adds global PHP auth guarding through `.user.ini` / `auto_prepend_file`.
- Adds login/logout screens under `/ops/`.
- Adds a CLI helper to create/update operator users without exposing passwords.
- Adds additive SQL tables for users, login attempts, and audit logs.
- Adds an authenticated Firefox helper ZIP download page.
- Provides a root `.htaccess` with no IP allow/deny restriction and with direct access denied for auth helper/config files.

## Safety

- Does not enable live automatic EDXEIX submission.
- Does not modify AADE receipt behavior.
- Does not store or expose secrets.
- Does not include cookies, sessions, logs, tokens, or private config.
- Public PHP endpoints become session/key protected after `.user.ini` is active.

## Files included

```text
public_html/gov.cabnet.app/.htaccess
public_html/gov.cabnet.app/.user.ini
public_html/gov.cabnet.app/_auth_prepend.php
public_html/gov.cabnet.app/ops/_auth.php
public_html/gov.cabnet.app/ops/login.php
public_html/gov.cabnet.app/ops/logout.php
public_html/gov.cabnet.app/ops/firefox-extension.php
gov.cabnet.app_app/src/Auth/OpsAuth.php
gov.cabnet.app_app/cli/create_ops_user.php
gov.cabnet.app_sql/2026_05_11_ops_login_system.sql
docs/OPS_LOGIN_AND_FIREFOX_EXTENSION.md
PATCH_README.md
```

## Exact upload paths

Upload each file to the matching live path:

```text
/home/cabnet/public_html/gov.cabnet.app/.htaccess
/home/cabnet/public_html/gov.cabnet.app/.user.ini
/home/cabnet/public_html/gov.cabnet.app/_auth_prepend.php
/home/cabnet/public_html/gov.cabnet.app/ops/_auth.php
/home/cabnet/public_html/gov.cabnet.app/ops/login.php
/home/cabnet/public_html/gov.cabnet.app/ops/logout.php
/home/cabnet/public_html/gov.cabnet.app/ops/firefox-extension.php
/home/cabnet/gov.cabnet.app_app/src/Auth/OpsAuth.php
/home/cabnet/gov.cabnet.app_app/cli/create_ops_user.php
/home/cabnet/gov.cabnet.app_sql/2026_05_11_ops_login_system.sql
```

Repo/doc-only files:

```text
docs/OPS_LOGIN_AND_FIREFOX_EXTENSION.md
PATCH_README.md
```

## SQL to run

```bash
mysql -u YOUR_DB_USER -p YOUR_DB_NAME < /home/cabnet/gov.cabnet.app_sql/2026_05_11_ops_login_system.sql
```

## Create the first operator user

```bash
php /home/cabnet/gov.cabnet.app_app/cli/create_ops_user.php \
  --username=andreas \
  --email=YOUR_EMAIL_HERE \
  --display-name="Andreas" \
  --role=admin
```

The script prompts for the password twice. Do not paste the password into chat or commit it.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Auth/OpsAuth.php
php -l /home/cabnet/gov.cabnet.app_app/cli/create_ops_user.php
php -l /home/cabnet/public_html/gov.cabnet.app/_auth_prepend.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/login.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/logout.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/firefox-extension.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/login.php
https://gov.cabnet.app/ops/pre-ride-email-tool.php
https://gov.cabnet.app/ops/firefox-extension.php
```

## Expected result

- `/ops/login.php` loads without IP restriction.
- Unauthenticated access to `/ops/pre-ride-email-tool.php` redirects to login.
- After login, `/ops/pre-ride-email-tool.php` opens normally.
- Root public PHP endpoints return authentication-required JSON unless the user is logged in or the request includes a valid `X-Gov-Cabnet-Key` header.
- The Firefox helper page allows an authenticated ZIP download if the server extension files exist and PHP ZipArchive is available.

## Removing the old IP restriction

Only remove the old IP restriction after the login test succeeds.

Check for any of these in live `.htaccess` files or cPanel IP Blocker/Directory Privacy and remove only the old IP-only block:

```apache
Require ip ...
Require all denied
Deny from all
Allow from ...
```

Do not remove the new `<FilesMatch>` block that protects `_auth_prepend.php`, `.env`, `.user.ini`, and config files.

## Git commit title

```text
Add ops login and authenticated Firefox helper download
```

## Git commit description

```text
Adds a plain PHP/mysqli operator login layer for gov.cabnet.app, replacing IP-only access with session-based authentication while preserving the guarded EDXEIX workflow.

Includes additive ops user/login/audit SQL tables, login/logout screens, a global auth prepend guard, a CLI user creation helper, and an authenticated page to download the current Firefox EDXEIX autofill helper package.

Live automatic EDXEIX submission remains disabled. AADE receipt issuing remains separate. Public PHP endpoints are protected by session or internal API key after deployment.
```
