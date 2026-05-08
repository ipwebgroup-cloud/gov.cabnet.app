# gov.cabnet.app v6.2.5 Pickup Receipts Patch

## What changed

- Added Bolt API pickup-swipe receipt worker.
- Updated Bolt sync wrapper to canonical library path.
- Updated canonical Bolt sync library for Fleet Orders and Athens timestamps.
- Added AADE auto-issuer gate for Bolt API bookings linked to real Bolt mail intake rows.
- Added receipt copy delivery to mykonoscab@gmail.com.
- Included lessor-scoped starting point SQL migration.

## Files included

- public_html/gov.cabnet.app/.htaccess
- gov.cabnet.app_app/cli/bolt_mail_production_tick.php
- gov.cabnet.app_app/cli/sync_bolt.php
- gov.cabnet.app_app/cli/bolt_pickup_receipt_worker.php
- gov.cabnet.app_app/lib/bolt_sync_lib.php
- gov.cabnet.app_app/src/Mail/BoltMailDriverNotificationService.php
- gov.cabnet.app_app/src/Mail/BoltMailIntakeBookingBridge.php
- gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php
- gov.cabnet.app_app/src/Receipts/AadeReceiptPayloadBuilder.php
- gov.cabnet.app_app/src/Receipts/AadeReceiptAutoIssuer.php
- gov.cabnet.app_sql/2026_05_08_v6_0_2_lessor_scoped_starting_points.sql
- docs/V6_2_5_PICKUP_RECEIPTS.md

## SQL

Already applied on live server.

Backup before SQL:
`/home/cabnet/gov_pre_v6_0_2_lessor_starting_points_20260508_124120.sql`

## Expected result

- Bolt API sync returns ok.
- Pickup receipt worker returns ok.
- Already issued bookings are skipped.
- New valid pickup-swiped bookings are issued and emailed.
- submission_jobs remains 0.
- submission_attempts remains 0.

## Git commit title

Add Bolt pickup-swipe AADE receipt worker

## Git commit description

Adds production Bolt pickup-swipe AADE receipt issuing for gov.cabnet.app. The workflow syncs Bolt fleet orders, detects order_pickup_timestamp, links API orders back to real Bolt mail intake rows, uses the first value from the Bolt estimated price range, issues AADE/myDATA receipts, emails official PDFs to drivers, and sends an office copy to mykonoscab@gmail.com. EDXEIX submission remains disabled and no submission jobs or attempts are created.
