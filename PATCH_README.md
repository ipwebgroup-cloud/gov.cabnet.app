# gov.cabnet.app patch — v3.0.92 legacy public utility usage audit

## What changed

Adds a read-only usage audit for legacy guarded public-root utilities and links it from the Developer Archive.

## Files included

- `gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php`
- `public_html/gov.cabnet.app/ops/legacy-public-utility-usage-audit.php`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `docs/LIVE_LEGACY_PUBLIC_UTILITY_USAGE_AUDIT_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-usage-audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-usage-audit.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-usage-audit.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "files_scanned=".($j["summary"]["files_scanned"]??"?").PHP_EOL; echo "mentions=".($j["summary"]["usage_mentions_total"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'

grep -n "v3.0.92\|Legacy Utility Usage Audit\|legacy_public_utility_usage_audit" \
  /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-usage-audit.php \
  /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected: syntax clean, ops URL redirects unauthenticated users to `/ops/login.php`, CLI returns `ok=true` and `final_blocks=[]`.
