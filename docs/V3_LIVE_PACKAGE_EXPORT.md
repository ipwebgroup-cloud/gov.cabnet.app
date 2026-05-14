# V3 Live Package Export

Version: `v3.0.52-v3-live-package-export`

## Purpose

This patch starts the closed-gate live adapter preparation phase by adding a V3-only local package exporter.

The exporter builds the exact local package that a future V3 live-submit adapter would consume, but it does not submit anything.

## Safety boundary

The exporter:

- does not call EDXEIX
- does not call AADE
- does not change queue status
- does not write to production submission tables
- does not touch V0 laptop/manual helper files
- does not open the live-submit gate

## CLI

Path:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php
```

Default mode is dry-run preview only:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php"
```

Export local artifacts for a current `live_submit_ready` row:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php --queue-id=ID --write"
```

Export local proof artifacts for a historical proof row that has since expired/blocked:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_package_export.php --queue-id=56 --allow-historical-proof --write"
```

## Artifact output

Default output path:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages/
```

Files written:

```text
queue_<id>_<timestamp>_payload.json
queue_<id>_<timestamp>_edxeix_fields.json
queue_<id>_<timestamp>_safety_report.json
queue_<id>_<timestamp>_safety_report.txt
```

## Ops page

Path:

```text
/ops/pre-ride-email-v3-live-package-export.php
```

This page is read-only and only shows package eligibility plus the exact CLI command to run.
