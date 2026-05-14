# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Updated: 2026-05-14  
Milestone: V3 closed-gate live adapter contract test verified and Handoff Center aligned

## Project identity

- Domain: https://gov.cabnet.app
- Repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- Live server is not a cloned Git repo.

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

## Current V3 state

Latest validated milestone:

```text
version: v3.0.75-v3-live-adapter-contract-test
queue_id: 716
queue_status: live_submit_ready at validation
payload_hash: e784e788532fc57824a46dad90debec9d0ad5a24f94679538c37d1d164e9f472
contract_test_ok: true
contract_safe: true
final_blocks: []
```

Safety posture remains closed-gate:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
adapter: edxeix_live_skeleton
adapter_live_capable=false
adapter_submit_called_by_contract_test=false
edxeix_call_made=false
aade_call_made=false
db_write_made=false
v0_touched=false
```

## Handoff Center alignment

`/ops/handoff-center.php` has been aligned with the latest V3 state.

It now separates package modes:

1. **Private Operational ZIP**
   - May include `DATABASE_EXPORT.sql`.
   - Admin-only.
   - Must remain private.
   - Must not be committed to GitHub.

2. **Git-Safe Continuity ZIP**
   - Built with `include_database=false`.
   - Defensively removes `DATABASE_EXPORT.sql` if present.
   - Adds `GIT_SAFE_CONTINUITY_NOTICE.md`.
   - Must still be validated and inspected before commit.

Important: server proof bundles and storage artifacts can contain customer/trip/email data. They must not be committed unless intentionally sanitized.

## Critical safety rules

- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Historical, cancelled, terminal, expired, invalid, duplicate, unmapped, or past Bolt orders must never be submitted to EDXEIX.
- V0 / existing production workflows must remain untouched unless Andreas explicitly requests otherwise.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, private credentials, AADE credentials, or EDXEIX credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Prefer read-only, dry-run, preview, audit, queue visibility, and preflight behavior.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
curl -I https://gov.cabnet.app/ops/handoff-center.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716 --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --queue-id=716 --json"
```

## Next safest step

Continue improving closed-gate operator visibility and package hygiene. Do not enable live submission.
