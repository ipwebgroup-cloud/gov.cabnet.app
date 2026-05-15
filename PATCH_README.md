# Patch README — v3.0.91 Public Utility Phase 2 Preview Noise Filter

## What changed

Updates the read-only public utility Phase 2 preview scanner so intentional wrapper, registry, navigation, and milestone documentation references are ignored in actionable cleanup counts.

## Files included

- `gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php`
- `docs/LIVE_PUBLIC_UTILITY_REFERENCE_CLEANUP_PREVIEW_NOISE_FILTER_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload:

```text
/home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php
```

Docs/continuity files are for the repo.

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/public_utility_reference_cleanup_phase2_preview.php --json" \
| php -r '$j=json_decode(stream_get_contents(STDIN), true); echo "ok=".(($j["ok"]??false)?"true":"false").PHP_EOL; echo "version=".($j["version"]??"").PHP_EOL; echo "actionable=".($j["summary"]["actionable_references"]??"?").PHP_EOL; echo "safe_phase2=".($j["summary"]["safe_phase2_candidates"]??"?").PHP_EOL; echo "ignored=".($j["summary"]["inventory_or_planner_references_ignored"]??"?").PHP_EOL; echo "final_blocks=".json_encode($j["final_blocks"]??[], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE).PHP_EOL;'
```

## Expected result

- Syntax passes.
- `ok=true`.
- Version is `v3.0.91-public-utility-reference-cleanup-preview-ignore-wrapper-noise`.
- `final_blocks=[]`.
- Wrapper/registry/navigation references no longer inflate actionable cleanup counts.

## Commit title

```text
Filter legacy wrapper references from cleanup preview
```

## Commit description

```text
Updates the read-only Phase 2 public utility reference cleanup preview so intentional wrapper, registry, navigation, and milestone documentation references are ignored in actionable cleanup counts.

This prevents the v3.0.89 legacy wrapper and v3.0.90 navigation links from being treated as cleanup debt.

No routes are moved or deleted. No redirects are added. No SQL changes are made. No Bolt, EDXEIX, AADE, database, or filesystem write actions are performed. Live EDXEIX submission remains disabled and the production pre-ride tool is untouched.
```
