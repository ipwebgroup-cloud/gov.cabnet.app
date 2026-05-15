# Patch v3.2.14 — Controlled One-Shot Maildir Fixture Writer

## Changed files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/_ops-nav.php`
- `docs/V3_CONTROLLED_ONE_SHOT_MAILDIR_FIXTURE_WRITER_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-fixture-writer-json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.14\|maildir-fixture-writer-json\|Controlled One-Shot Maildir\|write-one-maildir-fixture\|maildir_write_made" \
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected default result: preview-only, no Maildir write, no DB write, no queue mutation, no Bolt/EDXEIX/AADE calls, live submit blocked.
