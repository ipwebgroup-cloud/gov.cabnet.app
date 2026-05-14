# Patch README — V3 Live Adapter Contract Test

Package: `gov_v3_live_adapter_contract_test_20260514.zip`

## What changed

Adds a read-only, non-submitting V3 live adapter contract harness.

The harness builds the would-be future EDXEIX request envelope from a selected V3 queue row and displays:

- Method and endpoint label.
- Headers without secrets.
- Timeout policy.
- Idempotency/request ID shape.
- Normalized EDXEIX payload.
- Payload SHA-256.
- Future live preconditions.
- Response-normalization contract.
- Adapter class posture without calling `submit()`.

Live submission remains disabled.

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php
docs/V3_LIVE_ADAPTER_CONTRACT_TEST_20260514.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Exact upload paths

Upload/extract the files to these paths:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php
```

The documentation and continuity files are for the local GitHub Desktop repo/commit package. They are not required on the live server for the ops page to function.

For the local GitHub Desktop repo, keep these paths at repo root:

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php
docs/V3_LIVE_ADAPTER_CONTRACT_TEST_20260514.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php

curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716 --json"
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php?queue_id=716
```

Unauthenticated request should redirect to `/ops/login.php`.

## Expected result

Expected safe posture:

```text
network_allowed=false
adapter_submit_allowed=false
adapter_submit_called=false
edxeix_call_made=false
adapter is_live_capable=false
adapter submitted=false
```

`ok` may be false if queue #716 is no longer future-safe or the closed-gate rehearsal approval has expired. That is safe and expected. The important result is that the contract test remains non-network, non-submitting, and blocks expired/ineligible rows.

## Git commit title

Add V3 live adapter contract test

## Git commit description

Adds a read-only V3 live adapter contract test for the future EDXEIX submitter.
The new CLI and ops page build the would-be request envelope, headers-without-secrets, timeout policy, idempotency shape, payload hash, future live preconditions, and response-normalization contract from a selected queue row.
The harness does not call Bolt, EDXEIX, AADE, database write paths, production submission tables, V0 workflows, or adapter submit().
Live EDXEIX submission remains disabled and the current edxeix_live adapter remains skeleton-only/non-live.
