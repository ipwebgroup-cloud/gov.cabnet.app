# HANDOFF — gov.cabnet.app v6.2.9

Current focus: stabilize Bolt pre-ride email → AADE receipt delivery at pickup time.

v6.2.8 proved the mail-intake path works, but a live run issued two receipts for two near-duplicate mail intake rows. v6.2.9 adds a duplicate logical-trip guard and a process lock.

Key rules:
- EDXEIX remains disabled/untouched.
- `submission_jobs` and `submission_attempts` must remain zero unless Andreas explicitly approves live EDXEIX work.
- The receipt worker uses parsed Bolt email intake as the reliable source.
- Duplicate official receipts already issued must not be modified automatically; accountant review is required.

Primary worker:
`/home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php`

Cron:
`* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php --minutes=240 --limit=25 >> /home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_receipts.log 2>&1`

Validation:
`php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php`

Safety:
`mysql cabnet_gov -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs;"`
`mysql cabnet_gov -e "SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"`
