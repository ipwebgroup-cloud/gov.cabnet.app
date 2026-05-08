# gov.cabnet.app v6.2.6 — Pickup audit + passenger-name receipt fix

## What changed

- Fixes receipt passenger/customer-name resolution for Bolt API bookings linked to Bolt mail intake.
- Prefers the real `bolt_mail_intake.customer_name` over empty API booking fields and generic placeholders such as `Bolt Passenger`.
- Adds passenger name to AADE line comments when available.
- Ensures driver receipt PDF/email row uses the real passenger name from the matched Bolt email.
- Adds a read-only Bolt order audit CLI for proving whether pickup timestamps appear before finish.

## Files included

```text
gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
gov.cabnet.app_app/cli/bolt_live_order_audit.php
docs/V6_2_6_BOLT_PICKUP_AUDIT_AND_PASSENGER_NAME.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Exact upload paths

Upload files to:

```text
/home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
/home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
/home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php
/home/cabnet/docs/V6_2_6_BOLT_PICKUP_AUDIT_AND_PASSENGER_NAME.md
/home/cabnet/PATCH_README.md
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
```

## SQL

No SQL migration is required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php
```

Preview payload customer name for a linked booking, for example booking 64:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=64
```

Expected preview result:

```text
summary.customer_name = Elizabeth Brokou
```

Run live/raw audit once:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php --minutes=240 --limit=50
```

Run during next active ride:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php --watch --sleep=60 --minutes=240 --limit=50
```

## Expected result

- New AADE receipt payloads use the real passenger/customer name from the matched Bolt email.
- New driver PDF/email receipt copies show the passenger name.
- Existing issued receipts are not reissued.
- EDXEIX remains untouched.

## Git commit title

```text
v6.2.6 Fix Bolt receipt passenger name and add pickup audit
```

## Git commit description

```text
- Prefer matched Bolt mail intake customer name for AADE receipt payloads.
- Ignore empty API booking names and generic Bolt Passenger/Bolt Customer placeholders.
- Add passenger name to AADE lineComments when available.
- Ensure driver receipt PDF/email copy displays the real passenger name.
- Add read-only Bolt live/raw order audit CLI for pickup timestamp diagnostics.
- Keep EDXEIX live submission disabled and queues untouched.
```
