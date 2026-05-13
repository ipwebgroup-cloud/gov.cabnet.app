# gov.cabnet.app patch — V3 live-submit final rehearsal

## What changed

Adds a read-only final rehearsal layer for the V3 full-automation path. It validates `live_submit_ready` rows against the master gate, per-row operator approval, verified starting-point options, required adapter package fields, future-time checks, and price/time sanity.

## Files included

- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php`
- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-rehearsal.php`
- `docs/PRE_RIDE_EMAIL_TOOL_V3_LIVE_SUBMIT_REHEARSAL.md`
- `PATCH_README.md`

## Upload paths

- `gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php`
  → `/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php`

- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-rehearsal.php`
  → `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-rehearsal.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-submit-rehearsal.php

php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php --limit=20
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-submit-rehearsal.php
```

## Safety

- No EDXEIX call.
- No AADE call.
- No DB writes.
- No production `submission_jobs` writes.
- No production `submission_attempts` writes.
- Production `public_html/gov.cabnet.app/ops/pre-ride-email-tool.php` is untouched.

## Expected result right now

With no `live_submit_ready` rows:

```text
Rows checked: 0
Pre-live passed: 0
Blocked: 0
No EDXEIX call. No AADE call. No DB writes.
```
