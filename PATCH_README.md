# v3.0.51 — V3 Proof Dashboard Historical Proof Fix

## What changed

Updates `/ops/pre-ride-email-v3-proof.php` so the proof dashboard preserves historical live-readiness proof after the proof row is later blocked by the expiry guard.

The previous dashboard selected only a current `live_submit_ready` row. After pickup time passed, row 56 was safely changed to `blocked`, so the page showed "no current live-ready row" even though the proof had already succeeded.

The updated dashboard now uses V3 queue event history to show historical live-ready proof when no current live-ready row remains.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
docs/V3_PROOF_DASHBOARD.md
docs/V3_EDXEIX_LIVE_ADAPTER_FIELD_MAP_DRAFT.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
```

Docs go into the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-proof.php
```

## Expected result

If the proof row has expired, the page should show historical proof rather than implying the proof failed.

Expected safe posture:

```text
Historical live-ready proof found
No current live-ready row
Master gate closed
No live EDXEIX call
V0 untouched
```

## Safety

No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, queue mutation logic, cron schedules, or SQL schema are changed.
