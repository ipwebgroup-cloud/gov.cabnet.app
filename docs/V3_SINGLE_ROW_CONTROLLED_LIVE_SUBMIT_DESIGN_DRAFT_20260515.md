# V3.2.6 — Single-Row Controlled Live-Submit Design Draft

This patch adds a read-only design snapshot for a future single-row controlled live-submit patch.

## Safety posture

- Design only.
- No executable live submitter added.
- No live gate enablement.
- No DB writes.
- No queue mutations.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No cron jobs or notifications.
- Production Pre-Ride Tool remains untouched.

## New CLI modes

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --single-row-live-design-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --first-live-test-design-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --controlled-live-submit-design-json
```

## Purpose

The snapshot documents the boundaries, gate sequence, and single-row constraints that must be satisfied before Andreas explicitly requests any separate live-submit patch.

## Non-goal

This patch does not submit to EDXEIX and does not make live submission possible.
