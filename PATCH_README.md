# Patch README — v3.0.85 Public Utility Dependency Evidence

## What changed

Improves the read-only Public Utility Relocation Plan so it classifies dependency evidence for the six guarded public-root utilities before any relocation.

## Files included

- `gov.cabnet.app_app/cli/public_utility_relocation_plan.php`
- `public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php`
- `docs/LIVE_PUBLIC_UTILITY_DEPENDENCY_EVIDENCE_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

Upload:

- `/home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/public-utility-relocation-plan.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "requires=".($j["summary"]["requires_cron_or_bookmark_check"]??"?").PHP_EOL; echo "blocking_refs=".($j["summary"]["blocking_dependency_reference_count"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'

curl -I --max-time 10 https://gov.cabnet.app/ops/public-utility-relocation-plan.php
```

Expected:

- PHP syntax clean.
- `ok=true`.
- version is `v3.0.85-public-utility-relocation-plan-dependency-evidence`.
- `requires=6` or otherwise all active dependency risks are visible.
- `final_blocks=[]`.
- unauthenticated browser request returns 302 to `/ops/login.php`.

## Safety

No route is moved or deleted. No DB, Bolt, EDXEIX, AADE, or filesystem-write action is performed.
