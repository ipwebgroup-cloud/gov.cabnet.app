# v5.5 — AADE/myDATA Test Adapter Readiness

This patch prepares gov.cabnet.app for official AADE/myDATA receipt issuance without sending invoices automatically.

## Safety posture

- Normal Bolt driver copy remains available.
- Generated/non-AADE receipt emails should stay disabled.
- `receipt_pdf_mode` may be set to `aade_mydata`.
- The receipt email service will not fall back to generated/static PDFs when `aade_mydata` mode is selected.
- The public readiness page is read-only and does not call AADE.
- The CLI connectivity test performs a retrieval-style AADE request only; it does not call `SendInvoices`.
- No EDXEIX calls, jobs, attempts, bookings, or dry-run evidence changes are made by this patch.

## Added files

- `gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php`
- `gov.cabnet.app_app/cli/aade_mydata_readiness.php`
- `public_html/gov.cabnet.app/ops/aade-mydata-readiness.php`
- `gov.cabnet.app_sql/2026_05_07_receipt_issuance_attempts.sql`
- `gov.cabnet.app_config_examples/aade_mydata.example.php`

## Install SQL

```bash
DB_NAME=$(php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo $c["db"]["database"];')
mysql "$DB_NAME" < /home/cabnet/gov.cabnet.app_sql/2026_05_07_receipt_issuance_attempts.sql
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Receipts/AadeMyDataClient.php
php -l /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/aade-mydata-readiness.php
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
```

## Readiness page

```text
https://gov.cabnet.app/ops/aade-mydata-readiness.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/aade-mydata-readiness.php?key=INTERNAL_API_KEY&format=json
```

## CLI readiness check

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php
```

## Connectivity ping

This retrieval-style test validates endpoint reachability and authentication headers. It does not transmit invoices.

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/aade_mydata_readiness.php --ping --record --by=Andreas
```

## Next phase

v5.6 should prepare the actual AADE/myDATA invoice XML payload in sandbox mode only, with accountant-approved document type, classifications, VAT category, payment method, and issuer details.
