# gov.cabnet.app — Ops UI Shell Phase 7 Profile Edit

## What changed

Adds a self-service operator profile edit page and updates the shared shell/profile navigation.

## Files included

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/profile.php
public_html/gov.cabnet.app/ops/profile-edit.php
docs/OPS_UI_SHELL_PHASE7_PROFILE_EDIT_2026_05_11.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
→ /home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css

public_html/gov.cabnet.app/ops/_shell.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

public_html/gov.cabnet.app/ops/profile.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/profile.php

public_html/gov.cabnet.app/ops/profile-edit.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/profile-edit.php
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile-edit.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/profile.php
https://gov.cabnet.app/ops/profile-edit.php
```

## Expected result

- Profile page shows Edit Profile action.
- Edit Profile lets the logged-in operator update display name and email only.
- Role, username, and active status remain read-only.
- Production pre-ride tool remains unchanged.

## Safety

This patch does not modify `/ops/pre-ride-email-tool.php` and does not call Bolt, EDXEIX, AADE, stage jobs, write workflow data, or enable live EDXEIX submission.
