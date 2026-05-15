# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

## Current checkpoint

v3.1.6 navigation-only patch prepared for the read-only V3 Next Real-Mail Candidate Watch.

## Latest verified V3 watch result

- v3.1.5 watcher syntax clean
- Ops page auth-protected with 302 to login
- `ok=true`
- `future_possible=0`
- `operator_candidates=0`
- `live_risk=false`
- `final_blocks=[]`

## v3.1.6 change

Adds the watcher link to:

- Pre-Ride top dropdown
- Daily Operations sidebar

## Safety posture

- Production Pre-Ride Tool untouched
- V0 workflow untouched
- No queue mutations
- No DB writes
- No SQL changes
- No Bolt calls
- No EDXEIX calls
- No AADE calls
- Live EDXEIX submit disabled
- V3 live gate closed
