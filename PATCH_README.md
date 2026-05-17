# gov.cabnet.app Patch v3.2.30 — Supervised Pre-Ride One-Shot EDXEIX Transport Trace

This patch adds an explicitly supervised one-candidate HTTP POST trace for a captured, future-safe pre-ride EDXEIX candidate.

## Important

This is the first patch in this path that can perform EDXEIX HTTP transport, but only when explicitly armed with:

- candidate ID,
- current payload hash,
- `--transport=1`,
- exact confirmation phrase,
- runtime readiness/rehearsal still passing.

Default CLI and web views are dry-run.

## Files

- `gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php`
- `public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php`
- `gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_transport_attempts.sql`
- `docs/EDXEIX_PRE_RIDE_ONE_SHOT_TRANSPORT_TRACE_v3.2.30.md`
- `docs/DEMO_PRE_RIDE_EMAIL_v3.2.30.txt`

## SQL

Optional but recommended before a live trace:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_transport_attempts.sql
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=2 --json
```

## Live trace command pattern

Use only when dry-run says `PRE_RIDE_TRANSPORT_TRACE_ARMABLE` and pickup remains more than 30 minutes in the future.

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php \
  --candidate-id=2 \
  --transport=1 \
  --expected-payload-hash=PAYLOAD_HASH_FROM_DRY_RUN \
  --confirm='I UNDERSTAND POST THIS ONE PRE-RIDE CANDIDATE TO EDXEIX' \
  --json
```

## Safety

No AADE call, queue job, normalized booking write, live config write, cron, or unattended worker is added.
