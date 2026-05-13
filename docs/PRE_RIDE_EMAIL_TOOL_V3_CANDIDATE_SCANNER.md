# V3 Maildir Candidate Scanner

This V3-only patch keeps the production pre-ride email tool untouched.

## Added behavior

`/ops/pre-ride-email-toolv3.php` now asks the V3 Maildir loader for recent matching Bolt pre-ride emails instead of only the newest message.

For each recent candidate, V3 runs the same safe preflight checks:

- V3 parser check.
- V3 read-only EDXEIX ID lookup.
- Future-time gate.

V3 automatically selects the first candidate that passes all gates. If no future-ready candidate exists, it keeps showing the newest candidate in blocked/preview-only mode.

## Safety

- Production `/ops/pre-ride-email-tool.php` is not included and not changed.
- No DB writes.
- No EDXEIX server-side calls.
- No AADE calls.
- No queue jobs.
- No Maildir messages are moved, deleted, marked read, stored, or logged.
- Maildir paths shown in the UI are sanitized tail names only.

## Files

- `public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php`
- `gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php`
