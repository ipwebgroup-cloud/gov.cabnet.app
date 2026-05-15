# V3 Real-Mail Observation Milestone — 2026-05-15

## Scope

This milestone documents the closed-gate V3 real-mail observation work completed after the legacy public utility audit milestone.

The goal was to observe real Bolt pre-ride email intake and V3 queue health without enabling live EDXEIX submission, mutating queue rows, or touching the production V0/pre-ride workflow.

## Included work

### v3.1.0 — V3 real-mail queue health audit

Added a read-only CLI and authenticated ops page:

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php`

Purpose:

- Observe real-mail V3 queue intake.
- Separate canary rows from possible real-mail rows.
- Report future active rows, live-submit-ready rows, dry-run-ready rows, stale locks, missing fields, and `last_error` rows.
- Confirm closed live-gate posture and live-risk state.

Latest verified output:

```text
queue_health_ok=true
possible_real=12
future_active=0
live_risk=false
final_blocks=[]
```

### v3.1.1 — Real-mail queue health navigation

Added navigation links for:

- `/ops/pre-ride-email-v3-real-mail-queue-health.php`

Locations:

- Pre-Ride top dropdown
- Daily Operations sidebar

### v3.1.2 — V3 real-mail expiry reason audit

Added a read-only CLI and authenticated ops page:

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`

Purpose:

- Explain why V3 queue rows are blocked/expired.
- Classify rows by expiry/future-safety guard vs historical mapping-correction vs other blocked reasons.
- Separate canary rows from possible real-mail rows.
- Confirm no live-submit recommendation.

Verified output:

```text
ok=true
version=v3.1.2-v3-real-mail-expiry-reason-audit
expired_guard=12
possible_real_expired=11
live_risk=false
final_blocks=[]
```

### v3.1.3 — Expiry reason audit navigation

Added navigation links for:

- `/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php`

Locations:

- Pre-Ride top dropdown
- Daily Operations sidebar

### v3.1.4 — Expiry audit possible-real count alignment

Updated the expiry reason audit to expose stable alignment fields:

- `possible_real_mail_rows`
- `canary_rows`
- `possible_real_mail_expired_guard_rows`
- `possible_real_mail_non_expired_guard_rows`
- `possible_real_mail_mapping_correction_rows`
- `possible_real_mail_other_blocked_rows`
- `classification_counts`
- `queue_health_vs_expiry_count_mismatch_explained`
- `queue_health_vs_expiry_count_mismatch_note`

Purpose:

- Explain why queue health can report more possible-real rows than the expired-guard total.
- In the verified state, 12 possible-real rows existed; 11 were expired by the future-safety guard and 1 was a historical mapping-correction block.

Latest verified output:

```text
ok=true
version=v3.1.4-v3-real-mail-expiry-audit-alignment
possible_real=12
possible_real_expired=11
possible_real_non_expired=1
mapping_correction=1
mismatch_explained=true
live_risk=false
final_blocks=[]
```

## Safety conclusion

The V3 real-mail observation milestone confirms:

```text
No eligible future V3 row.
No live-submit-ready V3 row.
No dry-run-ready V3 row.
No EDXEIX submission recommended.
No live risk detected.
V3 live gate remains closed.
```

## Current queue interpretation

The observed V3 queue rows are not eligible for EDXEIX submission.

Known classification:

```text
12 possible real-mail rows total
11 possible real-mail rows expired by the V3 future-safety guard
1 possible real-mail row is a historical mapping-correction block
1 canary row is also expired/blocked safely
future_active=0
live_risk=false
final_blocks=[]
```

The common expiry reason is:

```text
v3_queue_row_expired_pickup_not_future_safe
```

This is correct closed-gate behavior after pickup time has passed.

## Explicit non-changes

This milestone did not perform any of the following:

```text
No live EDXEIX submit enablement.
No Bolt API calls.
No EDXEIX calls.
No AADE calls.
No database writes.
No queue status mutations.
No production submission table writes.
No route moves.
No route deletions.
No redirects.
No SQL migrations.
No V0/prod pre-ride workflow changes.
```

## Production tool status

The production pre-ride tool remains untouched:

```text
/ops/pre-ride-email-tool.php
```

Latest auth check confirmed it still returns a 302 redirect to `/ops/login.php` when unauthenticated.

## Live gate status

The live EDXEIX gate remains closed:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
```

No live-submit change is approved by this milestone.

## Recommended next safe step

The next safe step is to keep V3 in observation mode and build a read-only “next eligible future real-mail watcher” that highlights the first future row before pickup expiry.

Do not enable live submission until Andreas explicitly requests a live-submit update and a real eligible future Bolt trip passes all gates.
