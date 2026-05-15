# PATCH README — v3.0.83 Public Utility Relocation Plan

## What changed

Adds a read-only no-break relocation planner for guarded public-root utility endpoints.

## Files included

```text
gov.cabnet.app_app/cli/public_utility_relocation_plan.php
public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/route-index.php
docs/LIVE_PUBLIC_UTILITY_RELOCATION_PLAN_20260515.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php
/home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php

curl -I --max-time 10 https://gov.cabnet.app/ops/public-utility-relocation-plan.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php --json"

grep -n "v3.0.83\|Public Utility Relocation Plan\|public_utility_relocation_plan" \
  /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/route-index.php
```

## Expected result

- CLI returns `ok=true`.
- Route page is protected by ops login.
- Delete recommended now remains `0`.
- Move recommended now remains `0`.
- The tool provides dependency-check commands before any future relocation.

## Git commit title

```text
Add public utility relocation plan
```

## Git commit description

```text
Adds a read-only no-break relocation planner for guarded public-root utility endpoints identified by the live public route exposure audit.

The planner classifies six public-root Bolt/EDXEIX utility routes, recommends future CLI or supervised ops targets, and provides dependency-check commands for cron, monitor, bookmark, and project references.

No routes are moved or deleted. No SQL changes are made. No Bolt, EDXEIX, AADE, DB, or filesystem write actions are performed. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
```
