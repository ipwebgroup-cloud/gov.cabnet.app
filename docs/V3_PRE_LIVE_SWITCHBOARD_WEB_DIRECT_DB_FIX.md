# V3 Pre-Live Switchboard Web Direct DB Fix

Version: `v3.0.65-v3-pre-live-switchboard-web-direct-db-fix`

## Purpose

The previous Ops page hotfix prevented HTTP 500, but the web PHP context did not allow a local command runner, so the page could not execute the CLI and decode JSON.

This patch replaces the Ops page with a direct read-only DB/config renderer.

## Safety

The page does not:

- call Bolt
- call EDXEIX
- call AADE
- write DB rows
- change queue status
- write production submission tables
- touch V0
- enable live submit
- change SQL schema
- change cron schedules

## Checks shown

- master gate state
- selected queue row
- payload completeness
- starting-point verification
- approval validity
- adapter state
- local package artifacts
- final live-submit block reasons

## Expected current result

`OK` remains effectively `no` / blocked because live submit is disabled and adapter is not `edxeix_live`.
