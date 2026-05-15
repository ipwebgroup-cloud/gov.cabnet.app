# Patch v3.2.9 — Controlled Maildir Fixture Writer Design Draft

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php`
- `/home/cabnet/docs/V3_CONTROLLED_MAILDIR_FIXTURE_WRITER_DESIGN_20260515.md`

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --demo-mail-fixture-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-design-json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.9\|maildir-writer-design-json\|Controlled Maildir Fixture Writer\|maildir_write_allowed_now\|executable_mail_writer_added" \
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```
