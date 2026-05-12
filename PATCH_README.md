# gov.cabnet.app Patch — Phase 52 EDXEIX Submit Connector Dev

## What changed

Adds a disabled dry-run EDXEIX submit connector contract and a development GUI page.

## Files included

```text
gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php
public_html/gov.cabnet.app/ops/edxeix-submit-connector-dev.php
docs/OPS_UI_SHELL_PHASE52_EDXEIX_CONNECTOR_DEV_2026_05_12.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php
→ /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php

public_html/gov.cabnet.app/ops/edxeix-submit-connector-dev.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-connector-dev.php
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitConnector.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-connector-dev.php
```

## Verification URL

```text
https://gov.cabnet.app/ops/edxeix-submit-connector-dev.php
```

## Production safety

The production pre-ride tool is unchanged:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

No Bolt calls, EDXEIX calls, AADE calls, DB writes, queue staging, or live submit behavior are added.
