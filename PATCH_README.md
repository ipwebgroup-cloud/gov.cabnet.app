# gov.cabnet.app Patch v3.2.31 — Candidate Closure + Retry Prevention + Form Token Diagnostic

## What changed

Adds a safe closure/retry-prevention layer after the v3.2.30 server-side POST trace returned HTTP 419/session expired.

This patch:

- Adds a candidate closure table.
- Adds CLI and ops page to mark a candidate manually submitted via V0/laptop.
- Archives manually submitted candidates in `edxeix_pre_ride_candidates`.
- Blocks retry by candidate ID, source hash, or payload hash.
- Fixes `--latest-ready=1` so old/past candidates are not selected.
- Adds an EDXEIX form-token diagnostic GET with sanitized output only.
- Holds future server POST attempts until fresh-token integration is implemented.

## Files included

- `gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php`
- `gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php`
- `gov.cabnet.app_app/cli/pre_ride_candidate_mark_manual.php`
- `gov.cabnet.app_app/cli/pre_ride_transport_rehearsal.php`
- `gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php`
- `public_html/gov.cabnet.app/ops/pre-ride-candidate-closure.php`
- `public_html/gov.cabnet.app/ops/pre-ride-transport-rehearsal.php`
- `public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php`
- `gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_candidate_closures.sql`
- `docs/EDXEIX_PRE_RIDE_CANDIDATE_CLOSURE_v3.2.31.md`
- root continuity docs

## SQL

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_17_edxeix_pre_ride_candidate_closures.sql
```

## Verify syntax

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_mark_manual.php
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-candidate-closure.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php
```

## Mark candidate 4 as manually submitted via V0

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_candidate_mark_manual.php \
  --candidate-id=4 \
  --method=v0_laptop_manual \
  --submitted-by=Andreas \
  --note='Real ride submitted manually through V0/laptop after server POST returned HTTP 419 session expired.' \
  --json
```

## Verify retry prevention

```bash
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_one_shot_transport_trace.php --candidate-id=4 --json
```

Expected: no POST performed; candidate closed/manual V0 blocker visible.

## Safety

No EDXEIX POST, no AADE call, no queue job, no normalized booking write, no live-submit config write, and no V0 production change.
