# gov.cabnet.app v6.8.1 — EDXEIX browser-assisted live production path

## What changed

- Adds browser-fill arming through `edxeix_live_mail_config_control.php --arm-browser-fill`.
- Updates the Firefox payload endpoint to allow only exact locked future pre-ride `bolt_mail` bookings.
- Updates the mail preflight bridge so newly created mail-derived bookings are automatically made EDXEIX-eligible when they are exact future pre-ride candidates.
- Hardens server-submit so a 302 redirect is not treated as confirmed EDXEIX creation.

## Files included

```text
public_html/gov.cabnet.app/edxeix-extension-payload.php
gov.cabnet.app_app/cli/edxeix_live_mail_config_control.php
gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php
gov.cabnet.app_app/cli/live_submit_one_mail_booking.php
docs/EDXEIX_LIVE_BROWSER_ASSISTED.md
PATCH_README.md
```

## Upload paths

```text
/home/cabnet/public_html/gov.cabnet.app/edxeix-extension-payload.php
/home/cabnet/gov.cabnet.app_app/cli/edxeix_live_mail_config_control.php
/home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php
/home/cabnet/gov.cabnet.app_app/cli/live_submit_one_mail_booking.php
```

## SQL

No schema changes.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/edxeix-extension-payload.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_live_mail_config_control.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php
php -l /home/cabnet/gov.cabnet.app_app/cli/live_submit_one_mail_booking.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_live_mail_config_control.php --status --json
```

## Production use

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --limit=20 --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=REAL_ID --create --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_readiness_report.php --only-ready --future-hours=168 --limit=100 --json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_live_mail_config_control.php --arm-browser-fill --booking-id=BOOKING_ID --by=Andreas --json
```

Then use Firefox extension:

1. Fetch locked payload.
2. Fill current EDXEIX form tab.
3. Review.
4. Submit manually in EDXEIX.
5. Confirm in the EDXEIX list.

## Commit title

Go live with browser-assisted EDXEIX pre-ride mail flow

## Commit description

Adds the browser-assisted live EDXEIX production flow based strictly on pre-ride Bolt email.

Changes:
- Arms browser-fill for one exact future mail-derived booking.
- Allows the Firefox payload endpoint to return only the locked pre-ride mail booking payload.
- Auto-clears old no_edxeix/aade_receipt_only flags only when creating an exact future mail-derived preflight booking.
- Prevents server HTTP 302 responses from being counted as confirmed live EDXEIX creation.

Safety:
- No AADE action.
- No queue rows.
- No secrets exposed.
- No blind multi-booking submit.
- EDXEIX source remains pre-ride Bolt email only.
