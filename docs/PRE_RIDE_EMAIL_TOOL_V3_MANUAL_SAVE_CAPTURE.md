# V3 Manual EDXEIX Save Capture

Adds V3-only recording for operator-confirmed manual EDXEIX saves.

## Purpose

After the isolated V3 Firefox helper fills the EDXEIX form and the operator manually reviews and saves inside EDXEIX, the helper can report that manual save back to gov.cabnet.app.

This closes the visibility loop without enabling automatic EDXEIX submit.

## Safety

- V3 helper still does not press Save or POST to EDXEIX.
- No AADE calls.
- No writes to production `submission_jobs`.
- No writes to production `submission_attempts`.
- Production `/ops/pre-ride-email-tool.php` is untouched.
- The callback validates `queueId` + `dedupeKey`.
- Manual save reporting is blocked for `blocked`, `cancelled`, or `failed` V3 queue rows.
- The only queue status update is V3-only: `helper_manual_save_reported` may set `pre_ride_email_v3_queue.queue_status = submitted` and `submitted_at = NOW()`.

## Files

- `public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php`
- `tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js`
- `tools/firefox-edxeix-autofill-helper-v3/manifest.json`

## Operator flow

1. V3 queue/watch shows a future-safe row.
2. Operator saves row to V3 helper and opens EDXEIX.
3. V3 helper fills the EDXEIX form.
4. Operator manually reviews and saves inside EDXEIX.
5. Operator clicks `Report manual EDXEIX save` in the V3 helper.
6. V3 queue records `helper_manual_save_reported` and marks the V3 row `submitted`.
