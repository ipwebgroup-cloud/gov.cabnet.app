# gov.cabnet.app — v3.2.1 Real Future Candidate Watch Snapshot

Date: 2026-05-15

## Purpose

v3.2.1 extends the read-only v3.2.0 real future candidate capture readiness layer with an operator-friendly one-shot watch snapshot.

The snapshot is designed to answer one question quickly:

> Is there a real future pre-ride candidate that needs operator review before pickup expiry?

## Safety posture

- No Bolt API calls.
- No EDXEIX calls.
- No AADE calls.
- No DB writes.
- No queue status changes.
- No filesystem writes.
- No cron installation.
- No notification delivery.
- No live-submit enablement.
- Production Pre-Ride Tool remains untouched.

## New CLI options

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --watch-json
```

Returns compact JSON with:

- action_code
- action_label
- severity
- candidate counts
- next candidate summary, if visible
- safety confirmation flags
- final blocks and warnings

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line
```

Returns a single line suitable for manual terminal polling with `watch`.

Example:

```bash
watch -n 30 '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --status-line'
```

This is manual terminal polling only. It does not create a background process, cron job, notification, DB write, log file, queue mutation, or live submission.

## Expected safe no-candidate state

```text
action=WAIT_NO_CANDIDATE
severity=clear
future=0
review=0
alerts=0
urgent=0
live_risk=no
```

## Operator interpretation

- `WAIT_NO_CANDIDATE`: no real future candidate is currently visible.
- `REVIEW_INCOMPLETE_FUTURE_CANDIDATE`: a future candidate exists but needs field/mapping review.
- `REVIEW_OPERATOR_ALERT`: operator attention is useful before expiry.
- `REVIEW_COMPLETE_FUTURE_CANDIDATE`: a complete candidate is ready for closed-gate review only.
- `BLOCKED_LIVE_GATE_RISK`: live gate posture is unsafe; do not continue.
- `BLOCKED_FINAL_BLOCKS`: the audit has final blocks; fix them first.

## Still not allowed

v3.2.1 does not approve or perform live EDXEIX submission. Live submit remains blocked unless Andreas explicitly requests a separate live-submit update and all gates pass against a real eligible future trip.
