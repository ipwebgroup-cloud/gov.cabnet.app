# V3 Helper Fill Capture

Adds fill-only progress capture for the isolated V3 EDXEIX helper.

## Added

- `/ops/pre-ride-email-v3-helper-callback.php`
- `tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js` v3.0.16
- `tools/firefox-edxeix-autofill-helper-v3/manifest.json` v3.0.2

## Behavior

When the V3 Firefox helper fills a queued EDXEIX form, it can report these V3-only events back to gov.cabnet.app:

- `helper_fill_started`
- `helper_redirect_company`
- `helper_fill_completed`
- `helper_fill_failed`
- `helper_diagnostic_reported`

The callback endpoint validates `queueId` and `dedupeKey` against `pre_ride_email_v3_queue` before inserting an event.

## Safety

- No EDXEIX POST/save is added.
- No AADE call is added.
- No writes to `submission_jobs`.
- No writes to `submission_attempts`.
- No change to `/ops/pre-ride-email-tool.php`.
- No queue status changes; the callback inserts only into `pre_ride_email_v3_queue_events`.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php
node --check tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js
```

Reload the isolated V3 Firefox helper after replacing the local extension files.
