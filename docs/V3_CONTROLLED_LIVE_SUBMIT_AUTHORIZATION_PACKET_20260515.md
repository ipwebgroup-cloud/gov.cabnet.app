# V3.2.7 — Controlled Live-Submit Runbook / Authorization Packet

This patch adds a read-only, non-executable authorization packet for a future first controlled single-row live test.

## Safety posture

- Authorization packet only.
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
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --authorization-packet-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --controlled-live-runbook-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --first-live-authorization-json
```

## Purpose

The snapshot consolidates the runbook, authorization gates, non-goals, and component posture that must be reviewed before Andreas explicitly requests a separate single-row live-submit patch.

## Non-goal

This patch does not submit to EDXEIX and does not make live submission possible.
