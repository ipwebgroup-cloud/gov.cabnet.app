# gov.cabnet.app patch — v3.0.98 Legacy Public Utility Readiness Board

## What changed

Adds a read-only aggregate board for the legacy public utility cleanup audits.

The board summarizes:

- Usage Audit
- Quiet-Period Audit
- Stats Source Audit
- Phase 2 Reference Preview

It provides one supervised `/ops` page and one CLI JSON report.

## Files included

```text
gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php
public_html/gov.cabnet.app/ops/legacy-public-utility-readiness-board.php
docs/LIVE_LEGACY_PUBLIC_UTILITY_READINESS_BOARD_20260515.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php
/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-readiness-board.php
```

Docs/continuity files can be kept in the repo.

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-readiness-board.php

curl -I --max-time 10 https://gov.cabnet.app/ops/legacy-public-utility-readiness-board.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "move_now=".($j["summary"]["move_recommended_now"]??"?").PHP_EOL; echo "delete_now=".($j["summary"]["delete_recommended_now"]??"?").PHP_EOL; echo "redirect_now=".($j["summary"]["redirect_recommended_now"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

Expected:

```text
No syntax errors
HTTP 302 to /ops/login.php when unauthenticated
ok=true
version=v3.0.98-legacy-public-utility-readiness-board
move_now=0
delete_now=0
redirect_now=0
final_blocks=[]
```

## Safety

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database, or filesystem write actions are performed. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.

## Commit title

Add legacy public utility readiness board

## Commit description

Adds a read-only aggregate readiness board for the guarded legacy public-root utility cleanup audits.

The board summarizes the legacy usage audit, quiet-period audit, stats-source audit, and Phase 2 reference preview into one supervised ops page and CLI JSON report.

This creates a stable checkpoint for the legacy public utility cleanup phase without approving route retirement.

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database, or filesystem write actions are performed.

Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
