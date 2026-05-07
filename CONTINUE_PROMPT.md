Continue the gov.cabnet.app Bolt → EDXEIX bridge project from v5.0.

Project stack: plain PHP, mysqli/MariaDB, cPanel/manual upload. Do not introduce frameworks or heavy dependencies.

Current v5.0 goal: guarded live submit armed while EDXEIX session remains disconnected.

Important safety posture:
- Live submit was explicitly requested by Andreas.
- The safety net is `edxeix_session_connected=false`; no EDXEIX POST can occur while this is false.
- A one-shot lock is also required before any live submit: `allowed_booking_id` or `allowed_order_reference`.
- No live cron exists. Manual CLI only.
- Never create jobs/attempts or POST to EDXEIX unless Andreas explicitly proceeds and all gates pass.

Important files:
- `/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php`
- `/home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php`
- `/home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php`
- `/home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/live-submit-readiness.php`

After upload, verify:

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
php -l /home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php
php -l /home/cabnet/gov.cabnet.app_app/cli/set_live_submit_one_shot_lock.php
php -l /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/live-submit-readiness.php
```

Then arm session-disconnected mode:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/arm_live_submit_session_disconnected.php --by=Andreas
```

Expected live readiness verdict:

```text
LIVE_ARMED_SESSION_DISCONNECTED
```

Do not enable `edxeix_session_connected=true` unless Andreas explicitly requests the final live EDXEIX submit test.
