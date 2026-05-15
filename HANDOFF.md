# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Current checkpoint

As of 2026-05-15, the v3.0.80–v3.0.99 legacy public utility audit milestone is committed. No routes were moved/deleted/redirected and the production pre-ride tool remains untouched.

The next closed-gate V3 readiness step adds v3.1.0 real-mail intake and queue health observation.

## v3.1.0 added

- CLI: `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_mail_queue_health.php`
- Ops: `https://gov.cabnet.app/ops/pre-ride-email-v3-real-mail-queue-health.php`
- Docs: `docs/V3_REAL_MAIL_QUEUE_HEALTH_20260515.md`

## Safety posture

- Live EDXEIX submit remains disabled.
- V3 live gate remains closed.
- EDXEIX adapter remains non-live/skeleton unless explicitly changed later.
- Production `/ops/pre-ride-email-tool.php` remains untouched.
- V0/existing production workflow remains untouched.
- No SQL migration is included.

## Next safest action

Upload and verify v3.1.0. Then use it to observe the next real Bolt pre-ride email intake without enabling live submission.
