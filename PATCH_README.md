# gov.cabnet.app Patch — EDXEIX Submit Diagnostic v3.2.21

Generated for Andreas on 2026-05-17.

## What changed

This patch updates the EDXEIX submit diagnostic after server validation of v3.2.20.

v3.2.20 installed correctly and performed no EDXEIX transport, but its default dry-run selected an old finished/test-like booking and showed the current future guard as `0 min`. v3.2.21 keeps the ASAP automation track safe by adding candidate discovery and a diagnostic +30 minute guard floor.

Key changes:

- Adds candidate discovery to the CLI and web diagnostic page.
- Stops default analysis from falling back to arbitrary stale rows.
- Auto-selects only a real future Bolt candidate that passes diagnostic readiness filters.
- Adds `--list-candidates=1 --limit=75` support.
- Adds `candidate_report` to JSON output.
- Adds diagnostic safety blockers separate from live-gate blockers.
- Applies +30 minute minimum diagnostic future guard even if current config is lower.
- Blocks diagnostic transport unless a booking/order is explicitly selected or server-only one-shot config selects it.
- Updates scope, handoff, continue prompt, README, manifest, and diagnostic documentation.

## Files included

- `SCOPE.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `README.md`
- `PROJECT_FILE_MANIFEST.md`
- `PATCH_README.md`
- `docs/EDXEIX_SUBMIT_DIAGNOSTIC_v3.2.21.md`
- `gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php`
- `gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php`

## Exact upload paths

Upload/replace:

```text
/home/cabnet/SCOPE.md
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/PATCH_README.md
/home/cabnet/docs/EDXEIX_SUBMIT_DIAGNOSTIC_v3.2.21.md
/home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
/home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php
```

For local GitHub Desktop repo, extract this ZIP at the repository root. The ZIP root mirrors the repo/live layout directly and has no wrapper folder.

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php

php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json --list-candidates=1 --limit=75
```

Optional dry-run for a selected booking:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --booking-id=BOOKING_ID --json
```

Do not run transport unless explicitly authorized for a real future candidate.

## Verification URL

```text
https://gov.cabnet.app/ops/edxeix-submit-diagnostic.php
```

## Expected result

- Syntax checks pass.
- CLI returns `candidate_report`.
- If no real future Bolt candidate exists, classification is `NO_SAFE_CANDIDATE_AVAILABLE`.
- If configured guard is below +30, output shows `future_guard_floor_applied: true` and diagnostic safety blocker `configured_future_guard_below_30_minimum`.
- Web page shows candidate discovery and diagnostic safety blockers.
- No EDXEIX HTTP transport is performed by default.

## Git commit title

Harden EDXEIX diagnostic candidate discovery

## Git commit description

Improves the EDXEIX submit diagnostic after v3.2.20 server validation by adding candidate discovery, preventing stale default booking selection, applying a +30 minute diagnostic future-guard floor, requiring explicit booking/order selection for any transport trace, and exposing candidate_report plus diagnostic safety blockers in CLI and ops UI output.

No SQL changes. No unattended submit behavior enabled. Web mode remains dry-run/read-only.
