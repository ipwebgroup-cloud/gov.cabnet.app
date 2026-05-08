# v6.2.6 — Bolt pickup audit + receipt passenger-name fix

Date: 2026-05-08
Project: gov.cabnet.app Bolt → EDXEIX bridge

## Source-of-truth inspected

This patch was prepared from the uploaded live source archive and SQL dump:

- `source-of-trouth-live-site.zip`
- `cabnet_gov.sql`

The SQL dump confirms:

- `submission_jobs` has no data inserts.
- `submission_attempts` has no data inserts.
- `bolt_mail_intake.id = 25` contains customer `Elizabeth Brokou`.
- `bolt_mail_intake.id = 25` is linked to `normalized_bookings.id = 64`.
- `normalized_bookings.id = 64` is a Bolt API row with empty `customer_name` and generic placeholder `passenger_name = Bolt Passenger` / `lessee_name = Bolt Passenger`.

## Problem fixed

The receipt builder previously resolved customer name in this order:

```php
$booking['customer_name'] ?? $booking['passenger_name'] ?? $intake['customer_name'] ?? 'ΠΕΛΑΤΗΣ ΛΙΑΝΙΚΗΣ'
```

For Bolt API-linked bookings, `customer_name` may be an empty string and `passenger_name` may be the placeholder `Bolt Passenger`. Because empty strings are not `null`, the real mail-intake customer name was not reached.

## v6.2.6 behavior

The receipt payload now resolves the receipt passenger/customer name in this order:

1. Matched Bolt mail intake `customer_name`
2. Booking `customer_name`, only if real/non-placeholder
3. Booking `passenger_name`, only if real/non-placeholder
4. Booking `lessee_name`, only if real/non-placeholder
5. Safe retail fallback `ΠΕΛΑΤΗΣ ΛΙΑΝΙΚΗΣ`

The driver receipt PDF row now uses the same real-name preference, so the PDF `Passenger` line should show the Bolt email customer name.

The AADE `lineComments` now includes `Passenger <name>` when a real passenger name exists, while preserving the strict max-length handling.

## Added diagnostic

New CLI:

```bash
/home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php
```

Purpose:

- Read recent sanitized Bolt raw payloads already stored by sync.
- Show order status and pickup/dropoff/finished timestamps.
- Show whether a matching Bolt mail intake exists and whether the customer name is present.
- Does not call EDXEIX.
- Does not issue AADE receipts.
- Does not create submission jobs or attempts.
- Does not print credentials, cookies, tokens, or full raw payloads.

Run during a live transfer:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php --watch --sleep=60 --minutes=240 --limit=50
```

Useful one-off command:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_live_order_audit.php --minutes=240 --limit=50
```

Interpretation:

- If `order_pickup_timestamp` appears before `order_finished_timestamp`, the current pickup worker can issue at pickup.
- If orders only appear/update after finish, `getFleetOrders` alone is not enough for true pickup-time receipt issuing.

## Safety notes

- No SQL migration is required.
- Existing production receipts are not reissued.
- EDXEIX live submission remains blocked.
- `submission_jobs` and `submission_attempts` are untouched.
