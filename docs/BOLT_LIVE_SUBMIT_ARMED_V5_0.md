# gov.cabnet.app — v5.0 Guarded Live Submit Armed / Session Disconnected

## Purpose

v5.0 prepares the first controlled EDXEIX live-submit path while keeping the current safety net active: the EDXEIX session remains explicitly disconnected.

This phase is **not** an automatic live cron. It is a guarded manual live-submit path with these blockers:

- `live_submit_enabled=true` must be set in server-only `live_submit.php`.
- `http_submit_enabled=true` must be set in server-only `live_submit.php`.
- `edxeix_session_connected=true` must be set before any HTTP POST can occur.
- A valid EDXEIX session cookie and CSRF token must exist.
- A one-shot `allowed_booking_id` or `allowed_order_reference` must be set.
- The booking must be a real Bolt source, not lab/test/synthetic.
- The booking must not be terminal, cancelled, finished, expired, past, or too late.
- Driver and vehicle mappings must be valid.
- Duplicate success checks must pass.
- The exact confirmation phrase must be provided.

## New files

- `public_html/gov.cabnet.app/ops/live-submit-readiness.php`
- `gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php`
- `gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php`
- `gov.cabnet.app_app/cli/live_submit_one_booking.php`

## Changed file

- `gov.cabnet.app_app/lib/edxeix_live_submit_gate.php`

The live gate now contains real guarded HTTP transport, but it cannot run unless all blockers pass, especially `edxeix_session_connected=true` and a valid EDXEIX session.

## Arm live mode while keeping session disconnected

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php --by=Andreas
```

Expected:

```text
live_submit_enabled=true
http_submit_enabled=true
edxeix_session_connected=false
require_one_shot_lock=true
```

## Readiness page

```text
https://gov.cabnet.app/ops/live-submit-readiness.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/live-submit-readiness.php?key=INTERNAL_API_KEY&format=json
```

Expected while session is disconnected:

```text
verdict = LIVE_ARMED_SESSION_DISCONNECTED
```

## Analyze a candidate without submitting

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=BOOKING_ID --analyze-only
```

## Set the one-shot lock later

Only after a real future Bolt candidate is visible and reviewed:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php --booking-id=BOOKING_ID --by=Andreas
```

## Manual live submit command later

Only after `edxeix_session_connected=true`, a valid session exists, one-shot lock is set, and preflight passes:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=BOOKING_ID --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX'
```

## Safety notes

The patch does not add a cron and does not automatically submit queued rows. It does not create `submission_jobs` or `submission_attempts`. If a live HTTP POST is ever attempted, it writes to `edxeix_live_submission_audit` if that table exists.

The current intended v5.0 state is live-armed but session-disconnected, so no EDXEIX POST can occur.
