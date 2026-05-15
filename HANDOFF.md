# HANDOFF — gov.cabnet.app v3.0.94 legacy utility quiet-period audit

Current state:

- Production pre-ride tool is untouched.
- V3 live EDXEIX submission remains disabled.
- Legacy public-root utility endpoints remain in place.
- v3.0.93 usage audit summary reported 68 historical mentions:
  - `/bolt-api-smoke-test.php`: 14 mentions, last seen 24-Apr-2026 12:39:01 UTC
  - `/bolt-fleet-orders-watch.php`: 0 mentions
  - `/bolt_stage_edxeix_jobs.php`: 17 mentions, date not normalized
  - `/bolt_submission_worker.php`: 13 mentions, date not normalized
  - `/bolt_sync_orders.php`: 15 mentions, last seen Apr/25/26 7:40 PM
  - `/bolt_sync_reference.php`: 9 mentions, date not normalized

This patch adds v3.0.94:

- `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_quiet_period_audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-quiet-period-audit.php`

Purpose:

- Classify usage evidence by quiet-period posture.
- Identify possible future compatibility-stub review candidates.
- Do not move/delete/redirect any route.

Next safest step:

- Verify v3.0.94 on live.
- Review output before any compatibility-stub planning.
