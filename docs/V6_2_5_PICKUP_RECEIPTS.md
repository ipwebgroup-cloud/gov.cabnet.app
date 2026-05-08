# gov.cabnet.app v6.2.5 — Bolt Pickup Receipt Issuance

This patch adds the production Bolt pickup-swipe AADE receipt workflow.

Receipt trigger:
- Bolt API confirms `order_pickup_timestamp`
- order is matched to Bolt mail intake where possible
- receipt amount uses the first number in the Bolt estimated price range
- AADE/myDATA receipt is issued
- official PDF is emailed to the driver
- office copy is sent to mykonoscab@gmail.com

Safety:
- does not call EDXEIX
- does not create submission_jobs
- does not create submission_attempts
- skips cancelled, no-show, non-responded, zero-price, and already-issued orders

Active cron:
- bolt_mail_production_tick.php every minute
- sync_bolt_driver_directory.php every 15 minutes
- sync_bolt.php every minute
- bolt_pickup_receipt_worker.php every minute
