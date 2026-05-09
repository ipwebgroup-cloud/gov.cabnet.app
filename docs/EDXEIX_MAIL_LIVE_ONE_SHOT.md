# EDXEIX Mail Live One-Shot Submit v6.8.0

This patch adds a guarded live path for one exact EDXEIX booking created from pre-ride Bolt email intake.

## Source policy

- EDXEIX source: pre-ride Bolt email only.
- Bolt API is not an EDXEIX source.
- AADE source: Bolt API pickup timestamp worker only.
- Pre-ride email must never issue AADE.

## Safety

The live submit script:

- accepts one booking id only;
- requires source `bolt_mail` / `mail:*`;
- blocks `never_submit_live=1`;
- blocks receipt-only/no-EDXEIX/terminal/past/cancel reasons;
- requires future guard;
- requires mapped driver, vehicle, starting point, pickup, drop-off, start and end time;
- requires EDXEIX session ready;
- requires live config armed for the exact booking;
- requires exact confirmation phrase;
- auto-disarms live config after any HTTP submit attempt;
- does not create `submission_jobs` or `submission_attempts`.

## Normal live procedure

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --limit=20 --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=REAL_ID --create --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_live_mail_config_control.php --arm-mail-live --booking-id=BOOKING_ID --by=Andreas --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_mail_booking.php --booking-id=BOOKING_ID --confirm='I UNDERSTAND SUBMIT LIVE TO EDXEIX' --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_live_mail_config_control.php --status --json
```

## Emergency disarm

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_live_mail_config_control.php --disarm --by=Andreas --json
```
