# Live Legacy Public Utility Readiness Board — 2026-05-15

## Version

v3.0.98-legacy-public-utility-readiness-board

## Purpose

Add a read-only aggregate board for the guarded legacy public-root utility cleanup work.

The board summarizes the existing read-only audits:

- Legacy Public Utility Usage Audit
- Legacy Public Utility Quiet-Period Audit
- Legacy Public Utility Stats Source Audit
- Public Utility Phase 2 Reference Cleanup Preview

## Safety posture

This patch does not:

- execute legacy public-root utilities
- move routes
- delete routes
- add redirects
- connect to the database
- write files
- call Bolt
- call EDXEIX
- call AADE
- enable live EDXEIX submission
- touch the production pre-ride tool

## Added files

- `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-readiness-board.php`

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-readiness-board.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-readiness-board.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "move_now=".($j["summary"]["move_recommended_now"]??"?").PHP_EOL; echo "delete_now=".($j["summary"]["delete_recommended_now"]??"?").PHP_EOL; echo "redirect_now=".($j["summary"]["redirect_recommended_now"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

Expected:

- syntax checks pass
- unauthenticated ops page returns `302` to `/ops/login.php`
- `ok=true`
- `move_now=0`
- `delete_now=0`
- `redirect_now=0`
- `final_blocks=[]`

## Decision guardrail

The readiness board is an audit/readiness summary only. It does not approve route retirement. Any future compatibility-stub work must require explicit approval and one final dependency scan.
