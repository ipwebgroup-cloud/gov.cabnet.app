# Patch v3.2.13 — Maildir Fixture Writer Go/No-Go CLI Dispatch Fix

## Files

- gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
- public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
- public_html/gov.cabnet.app/ops/_shell.php
- public_html/gov.cabnet.app/ops/_ops-nav.php
- docs/V3_MAILDIR_FIXTURE_WRITER_GO_NO_GO_CLI_DISPATCH_FIX_20260515.md
- HANDOFF.md
- CONTINUE_PROMPT.md
- PATCH_README.md

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --maildir-writer-go-no-go-json
```

## Safety

No Maildir writes, no writer, no DB writes, no queue mutation, no EDXEIX/AADE/Bolt calls, no live-submit enablement.
