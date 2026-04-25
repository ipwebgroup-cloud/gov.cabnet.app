# Live EDXEIX Submit Gate — Disabled Preparatory Patch

This patch prepares the final live-submission control path while keeping real EDXEIX submission blocked.

## Added files

- `gov.cabnet.app_app/lib/edxeix_live_submit_gate.php`
- `public_html/gov.cabnet.app/ops/live-submit.php`
- `gov.cabnet.app_config_examples/live_submit.example.php`
- `gov.cabnet.app_sql/2026_04_25_live_submission_audit.sql`

## Current behavior

The live-submit page analyzes recent normalized bookings and shows whether a candidate would pass the final safety gate.

It checks:

- real Bolt source only
- not LAB/test/never-live
- mapped driver
- mapped vehicle
- future guard
- non-terminal status
- EDXEIX session readiness
- configured EDXEIX submit URL
- duplicate successful submissions
- server-only live-submit flags

## Safety boundary

This patch does **not** execute live EDXEIX HTTP submission.

Even if the real server config is toggled, the PHP gate still returns:

`http_transport_not_enabled_in_this_patch`

This is intentional. The first actual live HTTP request should only be enabled in a later patch after:

1. a real future Bolt candidate is available,
2. preflight is reviewed,
3. the exact EDXEIX submit URL/session behavior is confirmed,
4. Andreas explicitly approves the live-submit execution patch.

## Server-only config

Copy:

```bash
cp /home/cabnet/gov.cabnet.app_config_examples/live_submit.example.php /home/cabnet/gov.cabnet.app_config/live_submit.php
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_config/live_submit.php
chmod 640 /home/cabnet/gov.cabnet.app_config/live_submit.php
```

The real config file must never be committed.

## Audit table

Run:

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_04_25_live_submission_audit.sql
```

The table is additive only and is used for future duplicate protection and live-submit audit history.

## Verification

Open:

```text
https://gov.cabnet.app/ops/live-submit.php
https://gov.cabnet.app/ops/live-submit.php?format=json
```

Expected now:

- config live disabled
- HTTP config disabled
- live HTTP transport blocked
- no EDXEIX request performed
- no live submission possible
