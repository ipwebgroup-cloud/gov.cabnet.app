# gov.cabnet.app patch — v3.0.94 legacy utility quiet-period audit

## What changed

Adds a read-only quiet-period audit for legacy public-root utility usage evidence.

## Files included

- `gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php`
- `public_html/gov.cabnet.app/ops/legacy-public-utility-quiet-period-audit.php`
- `docs/LIVE_LEGACY_PUBLIC_UTILITY_QUIET_PERIOD_AUDIT_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-quiet-period-audit.php`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-quiet-period-audit.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-quiet-period-audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "candidates=".($j["summary"]["quiet_period_stub_review_candidates"]??"?").PHP_EOL; echo "unknown=".($j["summary"]["usage_evidence_with_unknown_date"]??"?").PHP_EOL; echo "move_now=".($j["summary"]["move_recommended_now"]??"?").PHP_EOL; echo "delete_now=".($j["summary"]["delete_recommended_now"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

## Expected result

- Syntax checks pass.
- Ops page redirects unauthenticated users to `/ops/login.php`.
- CLI returns `ok=true` and `final_blocks=[]`.
- `move_now=0` and `delete_now=0`.
