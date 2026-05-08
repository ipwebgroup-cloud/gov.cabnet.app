# gov.cabnet.app Bolt → EDXEIX bridge handoff — v6.2.6

You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

## Project identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow.
- Server layout:
  - `/home/cabnet/public_html/gov.cabnet.app`
  - `/home/cabnet/gov.cabnet.app_app`
  - `/home/cabnet/gov.cabnet.app_config`
  - `/home/cabnet/gov.cabnet.app_sql`

## Critical safety rules

- Do not enable live EDXEIX submission unless Andreas explicitly asks.
- EDXEIX submission queues must remain zero unless explicitly approved.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- AADE receipt issuing is live production and must remain duplicate-protected.
- Never request or expose real credentials.
- Config examples may be committed; real config files must stay server-only.
- Preserve plain PHP/mysqli/cPanel deployment style. No frameworks, Composer, Node, or heavy dependencies.

## Current production state as of 2026-05-08

- Bolt mail intake is live.
- Bolt API sync is live through cron.
- AADE/myDATA production receipt issuing is live.
- Driver receipt PDF email copy is live.
- EDXEIX live submission remains blocked.
- Uploaded SQL dump confirmed no inserted rows for:
  - `submission_jobs`
  - `submission_attempts`

## v6.2.6 patch

Patch focus:

1. Fix missing passenger/customer name on receipts.
2. Add read-only Bolt live/raw order audit CLI to prove pickup timestamp availability.

Files:

```text
gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
gov.cabnet.app_app/cli/bolt_live_order_audit.php
docs/V6_2_6_BOLT_PICKUP_AUDIT_AND_PASSENGER_NAME.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Passenger-name issue found

Real example from uploaded SQL:

- `bolt_mail_intake.id = 25`
- customer: `Elizabeth Brokou`
- linked booking: `normalized_bookings.id = 64`
- API booking has empty `customer_name`
- API booking has placeholder `passenger_name = Bolt Passenger`
- old payload builder preferred booking fields before intake, so receipt did not show the real passenger name.

v6.2.6 behavior:

- Prefer `bolt_mail_intake.customer_name`.
- Ignore empty strings and placeholders such as `Bolt Passenger`, `Bolt Customer`, `ΠΕΛΑΤΗΣ ΛΙΑΝΙΚΗΣ`.
- Add real passenger name to AADE `lineComments` when available.
- Use same resolved passenger name in driver PDF/email receipt copy.

## Pickup timing issue still open

Production observation:

- Intake 25 receipt email arrived when trip was finished, not immediately at pickup/client-in-car.

Next diagnostic:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php --watch --sleep=60 --minutes=240 --limit=50
```

Use during a live ride to determine whether `getFleetOrders` exposes `order_pickup_timestamp` before `order_finished_timestamp`.

## Validation commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_receipt_payload.php --booking-id=64
```

Expected payload preview:

```text
summary.customer_name = Elizabeth Brokou
```

## SQL

No SQL migration required for v6.2.6.

## Git commit title

`v6.2.6 Fix Bolt receipt passenger name and add pickup audit`
