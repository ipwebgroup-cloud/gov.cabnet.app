# Patch README — v3.0.50 V3 Proof Dashboard

## What changed

Adds a read-only V3 proof dashboard:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
```

Also adds documentation for the proof dashboard and a draft field map for the future closed-gate live adapter phase.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
docs/V3_PROOF_DASHBOARD.md
docs/V3_EDXEIX_LIVE_ADAPTER_FIELD_MAP_DRAFT.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php
```

Docs should be committed in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-proof.php
```

## Expected result

The page loads after Ops login and shows:

- V3 proof row/current queue state.
- Verified starting-point status.
- Master gate closed.
- No-live-call safety boundary.
- V0 untouched.

## Safety

No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, queue mutation logic, cron schedules, or SQL schema are changed.

## Commit title

```text
Add V3 proof dashboard
```

## Commit description

```text
Adds a read-only V3 proof dashboard summarizing the verified forwarded-email readiness path, current queue proof row, verified starting point, and closed live-submit gate state.

Also adds a draft V3-to-EDXEIX live adapter field map for the next closed-gate preparation phase.

No V0 production helper files, live-submit enabling, EDXEIX calls, AADE behavior, queue mutation logic, cron schedules, or SQL schema are changed.
```
