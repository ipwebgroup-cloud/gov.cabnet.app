# gov.cabnet.app — v3.0.84 Public Utility Relocation Plan Permission-Safe Scan Hotfix

Date: 2026-05-15

## Purpose

Fixes a production 500 error in `/ops/public-utility-relocation-plan.php` caused by the read-only scanner attempting to recurse into an unreadable private storage directory:

```text
/home/cabnet/gov.cabnet.app_app/storage/patch_backups
```

## Safety

- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No database connection.
- No filesystem writes.
- No route move.
- No route deletion.
- Production pre-ride tool is untouched.

## Changed file

```text
gov.cabnet.app_app/cli/public_utility_relocation_plan.php
```

## Upload path

```text
/home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php
```

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_relocation_plan.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'

curl -I --max-time 10 https://gov.cabnet.app/ops/public-utility-relocation-plan.php
```

Expected:

```text
No syntax errors detected
ok=true
version=v3.0.84-public-utility-relocation-plan-permission-safe-scan
final_blocks=[]
HTTP/1.1 302 Found when unauthenticated, or page loads after login
```
