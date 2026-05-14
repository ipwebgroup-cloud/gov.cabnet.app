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

## Source of truth priority

1. Latest uploaded files, pasted code, screenshots, SQL output, or live audit output in the current chat.
2. `HANDOFF.md` and this `CONTINUE_PROMPT.md`.
3. `README.md`, `SCOPE.md`, `DEPLOYMENT.md`, `SECURITY.md`, `docs/`, and `PROJECT_FILE_MANIFEST.md`.
4. GitHub repo.
5. Prior memory/context only as background, never as proof of current code state.

## Critical safety rules

- Default to read-only, dry-run, preview, audit, queue visibility, and preflight behavior.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a live-submit update.
- Live submission must remain blocked unless there is a real eligible future Bolt trip, preflight passes, and the trip is sufficiently in the future.
- Historical, cancelled, terminal, expired, invalid, or past Bolt orders must never be submitted to EDXEIX.
- Never request or expose real API keys, DB passwords, tokens, cookies, session files, or private credentials.
- Config examples may be committed; real config files must remain server-only and ignored by Git.
- Sanitize all downloadable zips: exclude secrets, logs, sessions, raw data dumps, cache files, and temporary public diagnostic scripts unless explicitly needed and safe.

## Latest milestone state

A closed-gate pre-live V3 canary rehearsal has been validated using queue row `#716`.

Validated row summary:

```text
queue_id: 716
queue_status: live_submit_ready
customer_name: V3 Canary Marina 1778760875
vehicle_plate: ITK7702
driver_name: Efthymios Giakis
lessor_id: 2307
driver_id: 17852
vehicle_id: 11187
starting_point_id: 1455969
starting_point_label: ΧΩΡΑ ΜΥΚΟΝΟΥ
approval_status: approved
approval_scope: closed_gate_rehearsal_only
submitted_at: NULL
failed_at: NULL
last_error: NULL
```

Latest proof bundle:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles/bundle_20260514_125023_summary.json
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles/bundle_20260514_125023_summary.txt
```

Live gate posture remains intentionally disabled:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
expected_closed_pre_live=true
live_risk_detected=false
adapter_looks_live_capable=false
full_live_switch_looks_open=false
```

The `edxeix_live` adapter exists but is skeleton-only/non-live:

```text
/home/cabnet/gov.cabnet.app_app/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php
```

Validated adapter facts:

```text
class_exists=true
instantiated=true
name=edxeix_live_skeleton
is_live_capable=false
submitted=false
blocked=true
reason=edxeix_live_adapter_skeleton_not_implemented
```

## Ops UI status

The V3 live operator console exists and was verified behind ops login:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php?queue_id=716
```

Expected badges:

```text
EXPECTED CLOSED PRE-LIVE GATE
NO LIVE RISK DETECTED
NO EDXEIX CALL
NO DB WRITES
```

## Next safest major step

Proceed with a **non-submitting live adapter contract test**. The goal is to define and verify the exact future EDXEIX request contract without making any network calls.

Scope for next patch:

- Add a read-only CLI harness that builds the would-be EDXEIX request envelope from a selected queue row.
- Include method, endpoint label, headers-without-secrets, timeout settings, payload hash, and response-normalization expectations.
- Keep adapter `is_live_capable=false`.
- Do not call EDXEIX.
- Do not write DB rows.
- Do not modify queue status.
- Add an ops read-only page if useful.
- Package as a small cPanel/manual-upload zip with changed/added files only.

Do not ask Andreas to confirm unless blocked by missing files. Inspect actual uploaded/server files first if available. Preserve existing filenames, includes, routes, mysqli style, and cPanel paths.
