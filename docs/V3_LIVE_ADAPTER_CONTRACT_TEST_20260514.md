# V3 Live Adapter Contract Test — 2026-05-14

## Purpose

This patch adds a read-only, non-submitting V3 harness that defines the future EDXEIX live adapter request contract without making any external calls.

The harness is designed to answer one question safely:

> If V3 is later approved for real EDXEIX live submission, what exact request envelope, payload hash, timeout policy, idempotency policy, and response-normalization shape will the submitter use?

## Safety posture

The patch does **not** enable live submission.

Hard guarantees in this milestone:

- No Bolt API call.
- No EDXEIX call.
- No AADE call.
- No DB writes.
- No queue status changes.
- No production submission table writes.
- No adapter `submit()` call.
- No V0 workflow impact.
- No secrets loaded into the displayed request contract.

## Added files

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php
docs/V3_LIVE_ADAPTER_CONTRACT_TEST_20260514.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## CLI harness

Path:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
```

Example command:

```bash
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716 --json"
```

Expected safe results for the current closed-gate pre-live state:

```text
contract_safe: true
network_allowed: false
adapter_submit_allowed: false
adapter_submit_called: false
edxeix_call_made: false
adapter is_live_capable: false
adapter submitted: false
```

`ok` may be `false` when queue #716 is no longer future-safe or its rehearsal approval has expired. That is acceptable and safe. The important safety checks are that no network/submit path is open and the final blocks explain why the row is no longer eligible.

## Ops page

Path:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php
```

URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php?queue_id=716
```

Expected unauthenticated behavior:

```text
302 redirect to /ops/login.php
```

Expected authenticated badges:

```text
contract safe
network disabled
adapter submit not called
adapter not live
```

## Request contract fields

The harness builds and displays:

- HTTP method label.
- Endpoint label only; real endpoint URL is not loaded.
- Headers without secrets.
- Request ID / idempotency key shape.
- Timeout policy.
- Normalized would-be EDXEIX payload.
- Payload SHA-256.
- Response normalization contract.
- Future live preconditions.

## Current adapter posture

The current EDXEIX adapter remains intentionally non-live:

```text
Bridge\BoltMailV3\EdxeixLiveSubmitAdapterV3
name: edxeix_live_skeleton
is_live_capable: false
submit_called: false by this contract test
```

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php

curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-live-adapter-contract-test.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716"

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716 --json"
```

## No SQL migration

This patch requires no SQL migration.

The harness reads from existing V3 tables only:

```text
pre_ride_email_v3_queue
pre_ride_email_v3_starting_point_options
pre_ride_email_v3_live_submit_approvals
```

## Next safe step after this patch

After the contract test is uploaded and verified, the next safe step is to add a small fixture-driven contract test that can run without a live DB row. That would preserve the same no-network/no-submit posture while making the future EDXEIX request shape easier to regression-test after row #716 expires.
