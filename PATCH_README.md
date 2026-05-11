# gov.cabnet.app — Ops UI Shell + Profile Patch

## What changed

Adds a shared EDXEIX-style operations UI shell and a read-only user profile page.

## Files included

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/profile.php
public_html/gov.cabnet.app/ops/ui-shell-preview.php
docs/OPS_UI_SHELL_PROFILE_2026_05_11.md
PATCH_README.md
```

## Exact upload paths

```text
/home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/profile.php
/home/cabnet/public_html/gov.cabnet.app/ops/ui-shell-preview.php
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/profile.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/ui-shell-preview.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/profile.php
https://gov.cabnet.app/ops/ui-shell-preview.php
```

## Expected result

Both pages require login and then show the shared EDXEIX-style shell with user profile navigation.

## Production safety

`/ops/pre-ride-email-tool.php` is not changed.

## Git commit title

```text
Add shared ops UI shell and profile page
```

## Git commit description

```text
Adds a reusable EDXEIX-style operations UI shell for future /ops pages and a read-only operator profile page. Includes profile/user navigation and a UI shell preview page for safe development.

Does not modify the production pre-ride email tool, does not call Bolt or EDXEIX, does not write database rows, and does not enable live EDXEIX submission.
```
