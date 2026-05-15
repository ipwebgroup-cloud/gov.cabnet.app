# V3.2.5 — Controlled Live-Submit Readiness Checklist

## Purpose

Adds a read-only go/no-go snapshot that summarizes whether the V3 observation stack is ready for a future controlled live-submit discussion.

This patch does not enable live EDXEIX submission. It adds no submit pathway and makes no external calls.

## New CLI modes

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --live-readiness-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --controlled-live-readiness-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --go-no-go-json
```

## Safety posture

- No DB writes.
- No queue mutation.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No cron jobs.
- No notifications.
- Live submit remains blocked by design.

## Output meaning

The new snapshot reports:

- current readiness outcome;
- hard no-go reasons, if any;
- whether a fresh complete future candidate is visible;
- whether dry-run preview passed;
- whether expired candidate safety regression passed;
- required manual gates before any future live-submit patch.

Even if all checks are green, the output keeps `live_submit_allowed_now=false` and requires Andreas to explicitly request a future live-submit update.
