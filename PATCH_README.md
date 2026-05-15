# gov.cabnet.app Patch — v3.0.88 Public Utility Reference Cleanup Phase 2 Preview

## What changed

Adds a read-only Phase 2 preview scanner that identifies remaining actionable references to guarded public-root utility endpoints while ignoring route inventory, audit, and planner references.

## Files included

- `gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php`
- `public_html/gov.cabnet.app/ops/public-utility-reference-cleanup-phase2-preview.php`
- `docs/LIVE_PUBLIC_UTILITY_REFERENCE_CLEANUP_PHASE2_PREVIEW_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php
/home/cabnet/public_html/gov.cabnet.app/ops/public-utility-reference-cleanup-phase2-preview.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/public-utility-reference-cleanup-phase2-preview.php

curl -I --max-time 10 https://gov.cabnet.app/ops/public-utility-reference-cleanup-phase2-preview.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "actionable=".($j["summary"]["actionable_references"]??"?").PHP_EOL; echo "safe_phase2=".($j["summary"]["safe_phase2_candidates"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

Expected: syntax clean, 302 auth redirect when unauthenticated, ok=true, final_blocks=[].

## Commit title

Add public utility Phase 2 reference preview

## Commit description

Adds a read-only Phase 2 preview scanner for remaining references to guarded public-root Bolt/EDXEIX utility endpoints.

The scanner separates actionable docs/ops/private/public-root references from intentional inventory, audit, and planner references so future cleanup does not chase route inventory noise.

No routes are moved or deleted. No SQL changes are made. No Bolt, EDXEIX, AADE, DB, or filesystem write actions are performed. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
