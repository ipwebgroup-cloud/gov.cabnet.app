# gov.cabnet.app v3.8 Patch — Bolt Mail Status Dashboard

## Changed/added files

```text
public_html/gov.cabnet.app/ops/mail-status.php
docs/BOLT_MAIL_STATUS_DASHBOARD.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
/home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php
```

Optional documentation/repo files:
```text
docs/BOLT_MAIL_STATUS_DASHBOARD.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

No SQL required.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php
```

Open:

```text
https://gov.cabnet.app/ops/mail-status.php?key=YOUR_INTERNAL_API_KEY
```

## Safety

This is read-only:
- no mailbox scan
- no import
- no booking creation
- no jobs
- no Bolt call
- no EDXEIX call
- no live submit
