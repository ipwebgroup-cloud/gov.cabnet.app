# V3 Pre-live Proof Bundle Export

Version: `v3.0.71-v3-pre-live-proof-bundle-export`

This patch adds a V3-only proof bundle exporter that collects the current read-only proof state into local server-side artifacts.

## Purpose

The proof bundle exporter captures:

- V3 storage prerequisite check
- V3 automation readiness output
- V3 pre-live switchboard output
- V3 adapter row simulation output
- V3 adapter payload consistency output
- V3 closed-gate adapter diagnostics output

The bundle is intended as the final internal evidence package before any future real adapter implementation work.

## Safety

The exporter does not:

- call Bolt
- call EDXEIX
- call AADE
- write database rows
- change queue status
- write production submission tables
- touch V0
- enable live submit
- change SQL
- change cron schedules

With `--write`, it writes local proof files only under:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles
```

These artifacts are server-private and should not be committed because they may contain operational row data.

## CLI

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php"
```

Write local proof artifacts:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --write"
```

Optional row-specific export:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --queue-id=427 --write"
```

JSON preview:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --json"
```

## Ops page

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php
```

The Ops page is read-only. It does not run CLI commands and does not write files. It shows the command to run and lists the latest proof bundle artifacts.

## Expected result

The exporter should show:

- storage check OK
- switchboard captured
- adapter simulation captured
- payload consistency captured
- adapter live capable = no
- adapter submitted = no
- simulation safe = yes
- no EDXEIX call
- no AADE call
- V0 untouched
