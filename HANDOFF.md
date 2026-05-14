# HANDOFF — gov.cabnet.app V3 Automation

Current version target: `v3.0.71-v3-pre-live-proof-bundle-export`

## Project

- Domain: `https://gov.cabnet.app`
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload
- Live submit remains disabled.
- V0 must remain untouched.

## Verified state

V3 has proven:

- future-safe queue intake
- submit dry-run readiness
- live-submit readiness
- starting-point guard
- payload audit
- package export
- operator approval
- final rehearsal
- kill-switch approval alignment
- pre-live switchboard
- adapter row simulation
- payload consistency harness

## Current patch

Adds a read-only proof bundle exporter:

```text
/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php
```

The CLI can write local proof artifacts only under:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles
```

## Safety

No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes, no queue status changes, no production submission table writes, no V0 changes.
