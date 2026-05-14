# Patch v3.0.52 — V3 Live Package Export

## What changed

Adds a V3-only CLI and read-only Ops page for exporting local live-submit package artifacts from a V3 proof row.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-package-export.php
docs/V3_LIVE_PACKAGE_EXPORT.md
docs/V3_EDXEIX_LIVE_ADAPTER_FIELD_MAP.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-package-export.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-package-export.php
```

Docs go into the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-package-export.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php --queue-id=56 --allow-historical-proof"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php --queue-id=56 --allow-historical-proof --write"

ls -la /home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/ | tail
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-package-export.php
```

## Expected result

- CLI dry-run previews the package.
- CLI `--write` creates local JSON/TXT artifacts only.
- Ops page loads and shows the exact command.
- No EDXEIX call.
- No AADE call.
- No queue status change.
- V0 untouched.

## Git commit title

```text
Add V3 live package export
```

## Git commit description

```text
Adds a V3-only local live package exporter and read-only Ops page for closed-gate live adapter preparation.

The exporter builds payload, EDXEIX field, and safety report artifacts under storage/artifacts/v3_live_submit_packages without calling EDXEIX, calling AADE, changing queue status, writing production submission tables, or touching V0.

Also documents the V3-to-EDXEIX field map and updates handoff/continuation notes.
```
