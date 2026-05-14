# gov.cabnet.app — V3 Automation Handoff

Checkpoint: `v3.0.72-v3-proof-bundle-runner-and-ops-hotfix`

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Private app: `/home/cabnet/gov.cabnet.app_app`
- Public webroot: `/home/cabnet/public_html/gov.cabnet.app`
- Config: `/home/cabnet/gov.cabnet.app_config`

## Current V3 state

V3 forwarded-email automation path is in pre-live proof mode.

Proven:

- Gmail-forwarded Bolt pre-ride email intake works.
- V3 queue rows can become `live_submit_ready` when future-safe and mapped.
- Past/expired rows are blocked.
- Starting-point guard uses operator-verified options.
- Closed-gate approval exists and expires.
- Package export creates local artifacts only.
- Adapter simulation calls the future EDXEIX skeleton and returns `submitted=false`.
- Payload consistency harness confirms DB payload, artifact payload, and adapter hash match.

## v3.0.72 hotfix

Fixes:

1. Ops page hard failure: `Ops auth include missing.`
2. Proof bundle child commands incorrectly showing `exit_code=-1` despite valid decoded JSON output.

Safety remains unchanged:

- No Bolt call
- No EDXEIX call
- No AADE call
- No DB writes
- No queue status changes
- No production submission table writes
- V0 untouched
- Live submit disabled

## Next step

Upload v3.0.72 changed files, then run:

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --write"
```

Then open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php
```
