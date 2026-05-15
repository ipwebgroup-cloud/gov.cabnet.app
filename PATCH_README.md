# gov.cabnet.app Patch — v3.0.96 Legacy Public Utility Stats Source Audit

## What changed

Adds a read-only audit that consumes the legacy utility usage audit and classifies evidence source kinds, especially cPanel stats/cache-only evidence.

## Files included

- `gov.cabnet.app_app/cli/legacy_public_utility_stats_source_audit.php`
- `public_html/gov.cabnet.app/ops/legacy-public-utility-stats-source-audit.php`
- `docs/LIVE_LEGACY_PUBLIC_UTILITY_STATS_SOURCE_AUDIT_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_stats_source_audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-stats-source-audit.php`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_stats_source_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-stats-source-audit.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-stats-source-audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_stats_source_audit.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "cpanel_only=".($j["summary"]["cpanel_stats_cache_only_routes"]??"?").PHP_EOL; echo "live_log=".($j["summary"]["live_access_log_evidence_routes"]??"?").PHP_EOL; echo "move_now=".($j["summary"]["move_recommended_now"]??"?").PHP_EOL; echo "delete_now=".($j["summary"]["delete_recommended_now"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

## Expected result

- Syntax checks pass.
- Ops page redirects unauthenticated users to `/ops/login.php`.
- CLI returns `ok=true`.
- `move_now=0`.
- `delete_now=0`.
- `final_blocks=[]`.

## Safety posture

No route behavior is changed. Legacy public-root utilities remain untouched.
