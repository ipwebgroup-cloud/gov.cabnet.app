# Patch README — v3.0.95 legacy quiet-period stable fields

## What changed

Updates the read-only quiet-period audit CLI so route-level classifications are exposed with stable JSON keys.

## Files included

- `gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php`
- `docs/LIVE_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_STABLE_FIELDS_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

- `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php`

Docs/continuity files are for repo continuity.

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "summary=".json_encode($j["summary"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL; foreach (($j["routes"]??[]) as $r) { echo PHP_EOL; echo "route=".($r["route"]??"?").PHP_EOL; echo "classification=".($r["quiet_period_classification"]??"?").PHP_EOL; echo "stub_candidate=".(!empty($r["stub_review_candidate"])?"yes":"no").PHP_EOL; echo "unknown_date=".(!empty($r["usage_evidence_unknown_date"])?"yes":"no").PHP_EOL; }'
```

Expected: `ok=true`, version `v3.0.95-legacy-public-utility-quiet-period-stable-fields`, `move_recommended_now=0`, `delete_recommended_now=0`, and `final_blocks=[]`.
