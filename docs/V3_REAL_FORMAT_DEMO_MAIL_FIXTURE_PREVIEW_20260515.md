# V3.2.8 — Real-Format Demo Mail Fixture Preview

This patch adds a read-only, redacted preview of a real-format pre-ride email fixture with future timestamps.

## Safety posture

- Preview only.
- No Maildir writes.
- No queue mutations.
- No DB writes.
- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No cron jobs or notifications.
- No executable mail writer added.
- Production Pre-Ride Tool remains untouched.

## New CLI modes

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --demo-mail-fixture-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --real-format-demo-mail-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --pre-ride-fixture-json
```

## Purpose

The snapshot lets the operator verify a clean real-format email fixture shape before any future explicit Maildir writer is considered. The body preview is redacted and intentionally does not create a message file.

## Non-goal

This patch does not create a demo email file and does not make live submission possible.
