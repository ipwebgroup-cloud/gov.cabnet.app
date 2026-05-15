# V3 Next Real-Mail Candidate Watch Navigation — 2026-05-15

## Purpose

Expose the read-only V3 Next Real-Mail Candidate Watch page in the operations navigation so Andreas can supervise the next real possible Bolt pre-ride email before it expires.

## Added route link

- `/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php`

## Navigation locations

- Pre-Ride top dropdown: `V3 Next Candidate Watch`
- Daily Operations sidebar: `Next Candidate Watch`

## Verified prior state

The v3.1.5 watcher was verified on live production with:

- `ok=true`
- `version=v3.1.5-v3-next-real-mail-candidate-watch`
- `future_possible=0`
- `operator_candidates=0`
- `live_risk=false`
- `final_blocks=[]`

## Safety posture

This navigation patch does not execute the watcher and does not mutate anything.

No Bolt calls, no EDXEIX calls, no AADE calls, no SQL changes, no DB writes, no queue mutations, and no live-submit enablement.

Live EDXEIX submission remains disabled. The production pre-ride tool remains untouched.
