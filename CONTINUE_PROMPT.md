# CONTINUE PROMPT — gov.cabnet.app Bolt → EDXEIX Bridge

You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

## Project identity

- Domain: https://gov.cabnet.app
- GitHub repo: https://github.com/ipwebgroup-cloud/gov.cabnet.app
- Stack: plain PHP, mysqli/MariaDB, cPanel/manual upload workflow
- No frameworks, Composer, Node build tools, or heavy dependencies unless Andreas explicitly approves.

Expected server layout:

```text
/home/cabnet/public_html/gov.cabnet.app
/home/cabnet/gov.cabnet.app_app
/home/cabnet/gov.cabnet.app_config
/home/cabnet/gov.cabnet.app_sql
/home/cabnet/tools/firefox-edxeix-autofill-helper
```

Live server is not a cloned Git repo. Workflow is:

1. Code with ChatGPT/Sophion.
2. Download zip patch/package.
3. Extract into local GitHub Desktop repo.
4. Upload manually to server.
5. Test on server.
6. Commit via GitHub Desktop after production confirmation.

## Source-of-truth priority

1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. `HANDOFF.md` and this `CONTINUE_PROMPT.md`.
3. `README.md`, `SCOPE.md`, `DEPLOYMENT.md`, `SECURITY.md`, `docs/`, and `PROJECT_FILE_MANIFEST.md`.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

## Current verified V3 milestone

```text
version: v3.0.75-v3-live-adapter-contract-test
queue_id: 716
payload_hash: e784e788532fc57824a46dad90debec9d0ad5a24f94679538c37d1d164e9f472
contract_test_ok: true
contract_safe: true
final_blocks: []
```

The Handoff Center was aligned in `v3.0.76-v3-handoff-center-alignment` so it displays the latest V3 milestone and separates package downloads into:

- Private Operational ZIP — may include database export; never commit.
- Git-Safe Continuity ZIP — DB-free, adds `GIT_SAFE_CONTINUITY_NOTICE.md`, validate before commit.

## Safety posture

Live EDXEIX submission remains disabled:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
adapter=edxeix_live_skeleton
adapter_live_capable=false
edxeix_call_made=false
aade_call_made=false
db_write_made=false
v0_touched=false
```

## Critical safety rules

- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Historical, cancelled, terminal, expired, invalid, duplicate, unmapped, or past Bolt orders must never be submitted to EDXEIX.
- V0 / existing production workflows must remain untouched unless Andreas explicitly requests otherwise.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, private credentials, AADE credentials, or EDXEIX credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Runtime storage artifacts and proof bundles may contain customer/trip/email data; do not commit them unless intentionally sanitized.

## Latest useful verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
curl -I https://gov.cabnet.app/ops/handoff-center.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_contract_test.php --queue-id=716 --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_gate_drift_guard.php --json"
su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_adapter_payload_consistency.php --queue-id=716 --json"
```

## Next safest major step

Continue improving V3 closed-gate operator visibility and package hygiene. Do not enable live submission.
