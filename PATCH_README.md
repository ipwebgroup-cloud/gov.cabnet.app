# gov.cabnet.app Patch — EMT8640 Existing V3 Queue Blocker

## Purpose

Blocks any already-existing active V3 queue rows for vehicle `EMT8640`, completing the permanent exemption that was added to future intake/notification/invoice/submission paths.

## Files included

```text
gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-emt8640-exemption-audit.php
docs/PRE_RIDE_EMAIL_TOOL_EMT8640_EXISTING_V3_QUEUE_BLOCKER.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php
→ /home/cabnet/gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-emt8640-exemption-audit.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-emt8640-exemption-audit.php
```

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-emt8640-exemption-audit.php
```

## Dry-run

```bash
php /home/cabnet/gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php --limit=500
```

## Commit blocker

```bash
php /home/cabnet/gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php --limit=500 --commit
```

## Ops URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-emt8640-exemption-audit.php
```

## Safety

- No EDXEIX call.
- No AADE call.
- No production submission_jobs write.
- No production submission_attempts write.
- No production pre-ride-email-tool.php change.
- Commit mode writes only to V3 queue/status/events.
