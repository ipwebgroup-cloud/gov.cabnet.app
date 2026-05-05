# Patch README — gov.cabnet.app v3.7 Mail Intake → Preflight Candidate Bridge

## What this patch adds

- Private reusable bridge class:
  - `gov.cabnet.app_app/src/Mail/BoltMailIntakeBookingBridge.php`
- Guarded Ops page:
  - `public_html/gov.cabnet.app/ops/mail-preflight.php`
- Additive SQL index:
  - `gov.cabnet.app_sql/2026_05_05_bolt_mail_preflight_bridge.sql`
- Documentation:
  - `docs/BOLT_MAIL_PREFLIGHT_BRIDGE.md`

## Safety

This patch does not create submission jobs and does not submit to EDXEIX.

It only creates local `normalized_bookings` rows when an operator manually approves a valid `future_candidate` mail intake row.

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailIntakeBookingBridge.php
/home/cabnet/public_html/gov.cabnet.app/ops/mail-preflight.php
/home/cabnet/gov.cabnet.app_sql/2026_05_05_bolt_mail_preflight_bridge.sql
```

## SQL

```bash
mysql DB_NAME < /home/cabnet/gov.cabnet.app_sql/2026_05_05_bolt_mail_preflight_bridge.sql
```

## Verify PHP syntax

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailIntakeBookingBridge.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-preflight.php
```

## Verify URL

```text
https://gov.cabnet.app/ops/mail-preflight.php?key=YOUR_INTERNAL_API_KEY
```

## Expected result

Current historical test rows remain blocked and cannot be converted.

The next valid future Bolt pre-ride email should appear as a future candidate and can be manually approved for local preflight booking creation.
