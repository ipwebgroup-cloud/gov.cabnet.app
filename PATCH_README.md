# Patch v4.1 — Bolt Mail Status Clarity

## What changed

Replaces `/ops/mail-status.php` with a read-only dashboard that separates active candidates, linked local preflight rows, synthetic test rows, stale open rows, local mail-created bookings, and submission job/attempt counts.

## Files included

- `public_html/gov.cabnet.app/ops/mail-status.php`
- `docs/BOLT_MAIL_STATUS_CLARITY.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload paths

Upload:

- `/home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php`

Optional repo/docs:

- `docs/BOLT_MAIL_STATUS_CLARITY.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## SQL

No SQL required.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php
```

Open:

`https://gov.cabnet.app/ops/mail-status.php?key=INTERNAL_KEY`

## Expected result

The dashboard remains read-only and shows:

- active unlinked future candidates
- linked future rows
- mail-created bookings
- synthetic rows
- open submission jobs
- submission attempts
- stale open intake rows

No Bolt, EDXEIX, submission, or database write actions are performed by this page.
