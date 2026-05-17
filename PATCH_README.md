# gov.cabnet.app Patch — EDXEIX Submit Diagnostic v3.2.20

Generated for Andreas on 2026-05-17.

## What changed

This patch keeps the project on the ASAP full-automation track by adding a safe diagnostic layer for the queue 2398 blocker: EDXEIX returned HTTP 302, but no remote/reference ID was captured and no saved contract was confirmed.

Key changes:

- Adds a private EDXEIX submit diagnostic library that can analyze a selected booking through the existing live-submit gate.
- Adds a CLI diagnostic tool that defaults to dry-run/read-only.
- Adds a dry-run `/ops/` diagnostic page for operator visibility and copy/paste terminal commands.
- Adds safe redirect-chain tracing and classification for supervised CLI transport diagnostics.
- Updates `SCOPE.md` so the immediate ASAP track is diagnostic redirect tracing, success proof, then controlled one-shot testing.
- Updates `HANDOFF.md`, `CONTINUE_PROMPT.md`, `README.md`, and `PROJECT_FILE_MANIFEST.md` to preserve continuity.
- Adds `docs/EDXEIX_SUBMIT_DIAGNOSTIC_v3.2.20.md`.

No unattended live submission is enabled. The web page never POSTs to EDXEIX.

## Files included

- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`
- `PROJECT_FILE_MANIFEST.md`
- `README.md`
- `SCOPE.md`
- `docs/EDXEIX_SUBMIT_DIAGNOSTIC_v3.2.20.md`
- `gov.cabnet.app_app/lib/edxeix_submit_diagnostic_lib.php`
- `gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-diagnostic.php`

## Exact upload paths

Upload/replace:

```text
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/PATCH_README.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/docs/EDXEIX_SUBMIT_DIAGNOSTIC_v3.2.20.md
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

php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --json
```

Optional dry-run for a selected booking:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --booking-id=BOOKING_ID --json
```

Supervised transport trace command for later only, after Andreas explicitly authorizes a real eligible future booking and server-only one-shot gates are enabled:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_submit_diagnostic.php --booking-id=BOOKING_ID --transport=1 --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX' --json
```

## Verification URLs

```text
https://gov.cabnet.app/ops/edxeix-submit-diagnostic.php
https://gov.cabnet.app/ops/live-submit-readiness.php
https://gov.cabnet.app/ops/edxeix-final-submit-gate.php
```

## Expected result

- Web diagnostic loads inside `/ops/` and clearly says it is dry-run/read-only.
- CLI dry-run returns JSON with booking analysis, session summary, blockers, payload field names, and classification `DRY_RUN_DIAGNOSTIC_ONLY`.
- No EDXEIX HTTP POST occurs unless `--transport=1` is used and all server-only live-submit gates are already enabled for one selected booking.
- If transport is requested while gates are disabled, result is `TRANSPORT_BLOCKED_BY_SAFETY_GATE`.
- No cookies, CSRF tokens, raw EDXEIX HTML, or raw payload values are printed.

## Git commit title

Add EDXEIX submit diagnostic tracing

## Git commit description

Adds dry-run EDXEIX submit diagnostics and a gated CLI redirect-chain trace tool so HTTP 302 can be classified before moving toward full automation. Updates scope, handoff, README, manifest, and documentation for the ASAP automation track while keeping unattended live submission disabled.

No SQL changes. No unattended worker. No live-submit behavior enabled by default.
