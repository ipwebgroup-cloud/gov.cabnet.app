# gov.cabnet.app — V3 Dry-Run Queue Preview Patch

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
docs/PRE_RIDE_EMAIL_TOOL_V3_QUEUE_PREVIEW.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

Docs remain in the local GitHub Desktop repo.

## Production isolation

This patch does not include or modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

Expected result: a V3 dry-run queue preview appears under recent Maildir candidates. It shows which emails would queue later, but no queue rows are created.
