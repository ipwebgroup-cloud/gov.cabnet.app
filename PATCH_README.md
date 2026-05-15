# Patch: v3.0.93 Legacy Public Utility Usage Audit Route Summary

## What changed

Updates the read-only legacy usage audit JSON so route summaries expose stable field names for CLI inspection.

## Files included

- `gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php`
- `docs/LIVE_LEGACY_PUBLIC_UTILITY_USAGE_AUDIT_ROUTE_SUMMARY_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

Upload:

```text
/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php
```

Docs/continuity files are for the local repo.

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "mentions=".($j["summary"]["usage_mentions_total"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL; foreach (($j["route_mention_summary"]??[]) as $r) { echo PHP_EOL; echo "route=".($r["route"]??"?").PHP_EOL; echo "mentions=".($r["mentions"]??"?").PHP_EOL; echo "last_seen=".(($r["last_seen"]??"") ?: "none").PHP_EOL; echo "recommendation=".($r["recommended_action"]??"?").PHP_EOL; }'
```

Expected:

```text
No syntax errors detected
ok=true
version=v3.0.93-legacy-public-utility-usage-audit-route-summary
final_blocks=[]
route=/bolt-api-smoke-test.php
mentions=<number>
```
