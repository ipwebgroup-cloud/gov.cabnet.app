Continue gov.cabnet.app from v6.2.5.

Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload.

Current state:
- Bolt mail intake works.
- Bolt API sync works.
- Pickup-swipe receipt worker works.
- Receipts issue after Bolt API order_pickup_timestamp.
- Receipt amount uses first number from Bolt estimated price range.
- Driver PDF receipt email works.
- Office copy to mykonoscab@gmail.com works.
- EDXEIX live submission remains disabled.
- submission_jobs and submission_attempts must remain zero unless Andreas explicitly approves live EDXEIX work.

Next safest steps:
1. Commit v6.2.5.
2. Add ops dashboard for receipt worker status.
3. Add safe recovery command for issued-but-email-failed receipts.
4. Keep EDXEIX disabled.
