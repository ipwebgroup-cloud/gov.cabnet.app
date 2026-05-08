# gov.cabnet.app Handoff — v6.3.0 EDXEIX Pre-live Hardening

Project: Bolt → local bookings → AADE receipt → EDXEIX readiness bridge.

Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.

Critical production rules:

- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit action.
- EDXEIX `submission_jobs` and `submission_attempts` must remain zero unless explicitly approved.
- Historical, cancelled, terminal, expired, invalid, no-show, driver-did-not-respond, receipt-only, and past Bolt orders must never be submitted to EDXEIX.
- AADE/myDATA receipt issuing is live production and must remain duplicate-protected.
- Real config and session files are server-only and must not be committed.

Current stable production state:

- Bolt mail intake cron is live.
- Bolt driver directory cron is live.
- AADE receipt issuing is live.
- Driver PDF receipt email is live.
- Receipt delivery path is stable through `bolt_mail_receipt_worker.php`.
- v6.2.9 duplicate guard is active.
- EDXEIX queues verified clean:
  - `submission_jobs = 0`
  - `submission_attempts = 0`

Recent important production event:

- Intake 26 / booking 67 / Diego Rodrigue was issued and emailed successfully at pickup time.
- Intakes 27 and 28 were duplicate AADE receipts before duplicate guard activation; do not delete DB rows. Accountant-guided cancellation/correction may be required.

v6.3.0 purpose:

Move closer to EDXEIX live submission safely by hardening the live gate and adding a read-only pre-live audit.

v6.3.0 changed files:

```text
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
gov.cabnet.app_app/cli/edxeix_prelive_audit.php
gov.cabnet.app_sql/2026_05_08_v6_3_0_receipt_only_edxeix_block.sql
docs/V6_3_0_EDXEIX_PRELIVE_HARDENING.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

Important v6.3.0 safety additions:

- `source_system=bolt_mail` receipt-only rows are blocked from EDXEIX.
- `order_reference=mail:*` rows are blocked from EDXEIX.
- Receipt-only block reasons are recognized and blocked.
- Newly created receipt-only bookings now set `never_submit_live=1`.
- Existing receipt-only rows should be updated by the v6.3.0 SQL migration.
- Terminal status recognition expanded for `client_did_not_show`, `driver_did_not_respond`, and no-show statuses.
- New read-only audit CLI: `edxeix_prelive_audit.php`.

After upload, run:

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php

mysqldump cabnet_gov > /home/cabnet/gov_pre_v6_3_0_edxeix_prelive_$(date +%Y%m%d_%H%M%S).sql
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_3_0_receipt_only_edxeix_block.sql

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php --future-hours=24 --past-minutes=60 --limit=50 --json

mysql cabnet_gov -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs;"
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
```

Next safe step:

Use `edxeix_prelive_audit.php` during/after the next future Bolt API booking to identify whether a real future Bolt order is technically ready. Do not submit live yet. First run analyze-only:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_booking.php --booking-id=BOOKING_ID --analyze-only
```

Only after payload, mapping, session, and one-shot lock are correct should a controlled live-submit decision be made.
