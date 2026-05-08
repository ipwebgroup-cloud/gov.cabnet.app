# v6.3.0 EDXEIX Pre-live Hardening Patch

## Files included

```text
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
gov.cabnet.app_app/cli/edxeix_prelive_audit.php
gov.cabnet.app_sql/2026_05_08_v6_3_0_receipt_only_edxeix_block.sql
docs/V6_3_0_EDXEIX_PRELIVE_HARDENING.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
/home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
/home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php
/home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_3_0_receipt_only_edxeix_block.sql
/home/cabnet/docs/V6_3_0_EDXEIX_PRELIVE_HARDENING.md
/home/cabnet/HANDOFF.md
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/PATCH_README.md
```

## SQL

Back up first:

```bash
mysqldump cabnet_gov > /home/cabnet/gov_pre_v6_3_0_edxeix_prelive_$(date +%Y%m%d_%H%M%S).sql
```

Apply:

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_08_v6_3_0_receipt_only_edxeix_block.sql
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
php -l /home/cabnet/gov.cabnet.app_app/cli/bolt_mail_receipt_worker.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_prelive_audit.php --future-hours=24 --past-minutes=60 --limit=50 --json

mysql cabnet_gov -e "SELECT COUNT(*) AS submission_jobs FROM submission_jobs;"
mysql cabnet_gov -e "SELECT COUNT(*) AS submission_attempts FROM submission_attempts;"
```

Expected:

```text
submission_jobs = 0
submission_attempts = 0
```

## Important

This patch does not enable live EDXEIX submission. It makes the pre-live path safer by blocking receipt-only AADE bookings from EDXEIX and adding a read-only pre-live audit tool.
