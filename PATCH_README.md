# v3.0.72 — V3 Proof Bundle Runner and Ops Hotfix

## What changed

Fixes two issues discovered during v3.0.71 verification:

1. The proof bundle Ops page hard-failed with `Ops auth include missing.`
2. The proof bundle CLI reported child command `exit_code=-1` even when the child command produced valid decoded JSON.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php
docs/V3_PRE_LIVE_PROOF_BUNDLE_HOTFIX.md
docs/V3_AUTOMATION_NEXT_STEPS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php
```

Keep docs in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php --write"
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php
```

## Expected result

- Ops page loads.
- CLI child command exit codes no longer appear as `-1` when the command completed and JSON was decoded.
- Bundle safety can be `yes` when the proof state is healthy, even if the master live-submit gate remains closed.
- No Bolt, EDXEIX, AADE, DB writes, queue changes, production submission table writes, SQL changes, cron changes, or V0 changes.

## Git commit title

```text
Fix V3 proof bundle runner and Ops page
```

## Git commit description

```text
Fixes the V3 pre-live proof bundle Ops page and child process runner.

The Ops page no longer hard-fails when a specific auth include is absent on the current cPanel layout, while still using available Ops auth guards. The proof bundle CLI now preserves child process exit codes when proc_get_status observes them before proc_close, preventing valid decoded JSON child runs from appearing as exit_code=-1.

Closed live-submit gate blocks remain visible as expected proof state. No V0 files, live-submit enabling, EDXEIX calls, AADE behavior, DB writes, queue status changes, production submission table writes, cron schedules, or SQL schema are changed.
```
