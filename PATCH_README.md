# Patch v3.2.11 — Maildir Fixture Writer Authorization Packet

## Changed files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/_ops-nav.php`
- `docs/V3_MAILDIR_FIXTURE_WRITER_AUTHORIZATION_PACKET_20260515.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload paths

- `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php`
- `/home/cabnet/docs/V3_MAILDIR_FIXTURE_WRITER_AUTHORIZATION_PACKET_20260515.md`

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --demo-mail-fixture-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-preflight-json
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-authorization-json

curl -I --max-time 10 https://gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php

grep -n "v3.2.11\|maildir-writer-authorization-json\|Maildir Fixture Writer Authorization\|maildir_write_allowed_now\|executable_mail_writer_added" \
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php \
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## Safety

No live submit, no Maildir write, no write probe, no executable writer, no DB write, no queue mutation, and no external calls.
