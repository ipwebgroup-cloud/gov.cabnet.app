# Patch v3.2.10 — Maildir Fixture Writer Preflight Audit

## Changed files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/_ops-nav.php`
- `docs/V3_MAILDIR_FIXTURE_WRITER_PREFLIGHT_AUDIT_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php`
- `/home/cabnet/docs/V3_MAILDIR_FIXTURE_WRITER_PREFLIGHT_AUDIT_20260515.md`

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-preflight-json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.10\|maildir-writer-preflight-json\|Maildir Fixture Writer Preflight\|write_probe_performed\|executable_mail_writer_added" \
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Safety

No live submit, no Maildir write, no DB write, no queue mutation, no external calls.
