# Patch README — v3.0.86 Public Utility Reference Cleanup Plan

## What changed

Enhances the read-only Public Utility Relocation Plan with a reference cleanup plan before any route movement.

The planner now shows:

- cleanup reference groups
- counts by ops/docs/private-app/public-root reference kind
- recommended no-break cleanup sequence
- clear statement that docs/operator guidance should be cleaned first

## Files included

- `gov.cabnet.app_app/cli/public_utility_relocation_plan.php`
- `public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php`
- `docs/LIVE_PUBLIC_UTILITY_REFERENCE_CLEANUP_PLAN_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

Upload:

- `/home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php`

Docs/continuity files are for local continuity.

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "cleanup_refs=".($j["summary"]["reference_cleanup_blocking_total"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'

curl -I --max-time 10 https://gov.cabnet.app/ops/public-utility-relocation-plan.php
```

Expected:

- syntax checks pass
- `ok=true`
- version is `v3.0.86-public-utility-reference-cleanup-plan`
- `final_blocks=[]`
- unauthenticated HTTP request redirects to `/ops/login.php`

## Expected browser result

After login, `/ops/public-utility-relocation-plan.php` should show:

- Public Utility Relocation Plan
- Reference cleanup plan
- No-break cleanup sequence
- no route moves/deletions recommended now

## Commit title

Add public utility reference cleanup plan

## Commit description

Enhances the read-only public utility relocation planner with a reference cleanup plan.

The planner now groups blocking dependencies by reference type and recommends a no-break cleanup sequence: documentation cleanup first, ops link review second, private CLI equivalents third, compatibility stubs fourth, and quiet-period removal review last.

No routes are moved or deleted. No SQL changes are made. No Bolt, EDXEIX, AADE, DB, or filesystem write actions are performed. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
