# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

## Current checkpoint

Patch/package: `v3.0.73-v3-proof-ledger`

Project: `gov.cabnet.app` Bolt pre-ride email V3 automation path.

Stack remains plain PHP + mysqli/MariaDB with manual cPanel upload. No Composer, no framework, no Node/build tooling.

## Server layout

Expected live paths:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
```

## Current state

V3 automation is in a closed-gate pre-live proof state.

Confirmed before this patch:

```text
V3 pre-live proof bundle export v3.0.72-v3-proof-bundle-runner-and-ops-hotfix
OK: yes
Bundle safe: yes
```

Safety flags confirmed:

```text
storage_ok: yes
payload_consistency_ok: yes
db_vs_artifact_match: yes
adapter_hash_match: yes
adapter_live_capable: no
adapter_submitted: no
simulation_safe: yes
edxeix_call_made: no
aade_call_made: no
db_write_made: no
v0_touched: no
```

## v3.0.73 deliverable

Adds a read-only proof ledger:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php
```

The CLI and Ops page index existing proof artifacts:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_live_submit_packages
```

The Ops page does not execute commands. It reads local artifact files only.
The CLI is read-only. It may connect to the DB for SELECT-only queue/approval counts, but it performs no writes.

## Absolute safety rules

Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit implementation/update.

Live submission must remain blocked unless all of the following are true in a future approved phase:

- There is a real eligible future Bolt/pre-ride row.
- The row is currently future-safe.
- Starting point is verified for the lessor.
- Payload is complete.
- Operator approval is valid and not expired.
- Master gate is explicitly enabled for live mode.
- Adapter is explicitly live-capable.
- Hard enable is true.
- Final rehearsal passes.

Historical, blocked, expired, cancelled, terminal, invalid, or past rows must never be submitted to EDXEIX.

## Recommended next step

After v3.0.73 is uploaded and tested, commit it as:

```text
Add V3 proof ledger for pre-live evidence review
```

Then continue with:

```text
v3.0.74 — V3 proof ledger integration polish
```

Suggested next scope:

1. Add proof-ledger link to the V3 Control Center/Ops Index.
2. Add latest-proof summary card to pre-live switchboard.
3. Add read-only retention/count warnings for proof artifacts.
4. Keep live submit disabled.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_proof_ledger.php --json"
```

Ops URL:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-proof-ledger.php
```
