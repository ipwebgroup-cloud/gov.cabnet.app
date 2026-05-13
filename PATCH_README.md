# gov.cabnet.app — V3 Submit Control Panel Patch

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue.php
docs/PRE_RIDE_EMAIL_TOOL_V3_SUBMIT_CONTROL_PANEL.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue.php
```

Docs remain in the local GitHub Desktop repo.

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue.php
```

Then open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-queue.php
```

## Safety

- Production `/ops/pre-ride-email-tool.php` is not included.
- No EDXEIX calls.
- No AADE calls.
- No writes to production `submission_jobs`.
- No writes to production `submission_attempts`.
- Operator actions write only to V3 queue/events tables.
