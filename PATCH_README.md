# Patch README — v3.0.71 V3 Pre-live Proof Bundle Export

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php
docs/V3_PRE_LIVE_PROOF_BUNDLE_EXPORT.md
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

Keep documentation files in the local GitHub Desktop repo.

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

## Expected result

```text
OK: yes
Bundle safe: yes
adapter_live_capable: no
adapter_submitted: no
simulation_safe: yes
```

## Safety

No Bolt call, no EDXEIX call, no AADE call, no DB writes, no queue status changes, no production submission tables, no V0 changes.
