# V3.2.4 — Expired Candidate Safety Regression Audit

This patch adds a read-only regression audit proving that a queue row which was once `live_submit_ready` cannot remain eligible after pickup time passes.

## Scope

- Detects stale `live_submit_ready` rows whose pickup is no longer future-safe.
- Shows whether any stale row still appears eligible for closed-gate review or operator alert.
- Produces a sanitized `--expired-safety-json` audit snapshot.
- Keeps live EDXEIX submission disabled.

## Safety

- No SQL changes.
- No DB writes.
- No queue mutations.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No cron jobs.
- No notifications.
- Production Pre-Ride Tool remains untouched.

## CLI

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --expired-safety-json
```

Aliases:

```bash
--stale-ready-audit-json
--regression-audit-json
```

Expected safe outcome after a live_submit_ready row expires:

```text
snapshot_mode=read_only_expired_candidate_safety_regression_audit
audit_outcome=stale_live_ready_rows_safely_blocked
eligibility_regression_passed=true
edxeix_call_made=false
db_write_made=false
queue_mutation_made=false
```
