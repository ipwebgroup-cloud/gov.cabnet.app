# V3 Live-Submit Final Rehearsal

This patch adds a read-only final rehearsal layer for the gov.cabnet.app Bolt pre-ride email V3 automation path.

## Purpose

The rehearsal layer checks the final chain that a future live-submit worker must pass:

1. `live_submit_ready` queue status.
2. Master live-submit gate snapshot.
3. Valid per-row operator approval.
4. Verified EDXEIX starting-point option.
5. Required final adapter package fields.
6. Future pickup buffer.
7. Positive price.
8. End time after start time.

## Safety

This patch does not submit to EDXEIX, does not call AADE, and does not write to the database.

It does not modify production `pre-ride-email-tool.php`.

## Files

- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-rehearsal.php`

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-rehearsal.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php --limit=20
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php --limit=20 --json
```

## Page

`https://gov.cabnet.app/ops/pre-ride-email-v3-live-submit-rehearsal.php`
