# EMT8640 Existing V3 Queue Blocker

This patch adds a V3-only cleanup/audit layer for the permanent EMT8640 vehicle exemption.

## Permanent rule

Vehicle `EMT8640` and Bolt vehicle identifier `f9170acc-3bc4-43c5-9eed-65d9cadee490` must never receive:

- voucher generation / handoff
- driver email notification
- invoice / AADE receipt
- EDXEIX submission
- V3 queue processing

## Files

- `gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-emt8640-exemption-audit.php`

## Safety

The blocker defaults to dry-run. With `--commit`, it writes only to:

- `pre_ride_email_v3_queue`
- `pre_ride_email_v3_queue_events`

It does not call EDXEIX or AADE and does not write to production submission tables.

## Commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-emt8640-exemption-audit.php

php /home/cabnet/gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php --limit=500
php /home/cabnet/gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php --limit=500 --commit
```

## Ops page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-emt8640-exemption-audit.php
```

## Expected result

If no old EMT8640 V3 rows exist:

```text
Matched active EMT8640 V3 rows: 0 | Blocked: 0
```

If old active rows exist, commit mode marks them:

```text
queue_status = blocked
last_error = vehicle_exempt_emt8640_no_voucher_no_driver_email_no_invoice
```
