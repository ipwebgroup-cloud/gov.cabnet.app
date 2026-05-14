# HANDOFF — gov.cabnet.app V3 Automation

Current patch: `v3.0.65-v3-pre-live-switchboard-web-direct-db-fix`

## Current proven status

- V3 intake proven
- V3 parser/mapping proven
- submit_dry_run_ready proven
- live_submit_ready proven
- payload audit proven
- package export proven
- operator approval proven
- final rehearsal blocks only by master gate when approval is valid and row is future-safe
- kill-switch approval validation aligned
- future adapter skeleton installed but not live-capable
- live submit remains disabled
- V0 remains untouched

## Latest issue fixed

The pre-live switchboard CLI worked, but the Ops page could not decode CLI JSON because the web PHP context had no allowed local command runner.

Patch `v3.0.65` replaces the Ops page with a direct read-only DB/config renderer.

## Safety boundary

Do not enable live submit unless Andreas explicitly asks. Do not touch V0. No EDXEIX calls, AADE calls, production submission table writes, queue mutation, cron changes, or SQL changes are included in this patch.
