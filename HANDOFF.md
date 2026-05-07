# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current state: v5.0 Guarded Live Submit Armed / Session Disconnected patch prepared.

## Confirmed previous baseline

- v4.9 dry-run production freeze passed.
- Mail intake cron is healthy.
- Auto dry-run evidence cron is healthy.
- Bolt driver directory sync is healthy.
- Driver email copy by driver identity is validated.
- Driver copy formatting was adjusted: end time +30 minutes and price range first value only.
- Dry-run evidence exists.
- `submission_jobs = 0` and `submission_attempts = 0` at freeze time.
- Live submit remained OFF until Andreas explicitly requested live mode.

## v5.0 intent

Andreas explicitly requested moving toward live submission, using the EDXEIX session not being connected as the safety net.

v5.0 therefore supports a live-armed state where:

- `live_submit_enabled=true`
- `http_submit_enabled=true`
- `edxeix_session_connected=false`
- `require_one_shot_lock=true`

With `edxeix_session_connected=false`, the live gate blocks with `edxeix_session_not_connected` and no EDXEIX HTTP POST can occur.

## New v5.0 tools

- `/home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php`
- `/home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php`
- `/home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/live-submit-readiness.php`

## Safety rules remain

No live submit unless all are true:

- EDXEIX session is explicitly connected in config.
- Session file exists and has non-placeholder cookie and CSRF.
- A one-shot booking lock is set.
- The booking is a real Bolt booking, not lab/test/synthetic.
- The booking is not terminal/cancelled/finished/expired/past/too late.
- Future guard passes.
- Driver and vehicle mappings are valid.
- Duplicate success checks pass.
- Exact confirmation phrase is provided.

## First command after upload

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php --by=Andreas
```

Then open:

```text
https://gov.cabnet.app/ops/live-submit-readiness.php?key=INTERNAL_API_KEY&format=json
```

Expected:

```text
verdict = LIVE_ARMED_SESSION_DISCONNECTED
```
