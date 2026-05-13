# gov.cabnet.app — V3 Candidate Scanner Patch

## Purpose

V3 currently blocks correctly when the latest Maildir pre-ride email is historical. This patch makes V3 inspect recent Maildir candidates and automatically select the first future-ready candidate when available.

## Production isolation

This patch does not include or modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php
docs/PRE_RIDE_EMAIL_TOOL_V3_CANDIDATE_SCANNER.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php

gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

Expected result:

- A recent Maildir candidates table appears when server messages are available.
- V3 auto-selects the first future-ready candidate.
- If all candidates are past/blocked, active helper payload remains empty and diagnostic preview remains available.
