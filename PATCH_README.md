# gov.cabnet.app — Ops UI Shell Phase 9 Apply Preferences

## What changed

Adds supported preference application to the shared `/ops` shell and adds `/ops/my-start.php` as a safe redirect to the logged-in operator's preferred landing page.

## Files included

- `public_html/gov.cabnet.app/assets/css/gov-ops-shell.css`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/my-start.php`
- `public_html/gov.cabnet.app/ops/profile-preferences.php`
- `docs/OPS_UI_SHELL_PHASE9_APPLY_PREFERENCES_2026_05_11.md`

## Upload paths

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
→ /home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css

public_html/gov.cabnet.app/ops/_shell.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

public_html/gov.cabnet.app/ops/my-start.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/my-start.php

public_html/gov.cabnet.app/ops/profile-preferences.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/profile-preferences.php
```

## SQL

None. Uses existing Phase 8 table `ops_user_preferences`.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/my-start.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile-preferences.php
```

## Expected result

- `/ops/profile-preferences.php` says preferences are now applied where supported.
- `/ops/my-start.php` redirects to the logged-in user's selected landing page.
- Shared-shell pages apply compact sidebar/table density where selected.
- Safety notices can be hidden only on supported shared-shell pages.
- `/ops/pre-ride-email-tool.php` is not modified.

## Git commit title

Apply ops user preferences to shared shell

## Git commit description

Applies stored operator UI preferences to the shared `/ops` shell and adds `/ops/my-start.php` for safe preferred landing-page routing. Supported shared-shell pages now honor sidebar density, table density, and safety notice visibility preferences.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, workflow writes, queue staging, or live submission behavior are added.
