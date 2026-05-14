# V3 Next Phase Plan

Version: v3.0.70-v3-payload-consistency-proof-checkpoint

## Next safest phase

`v3.0.71-v3-pre-live-proof-bundle-export`

## Goal

Create a single read-only CLI/Ops proof bundle exporter that gathers the current V3 proof state into local artifacts.

The bundle should include:

- V3 storage check summary
- V3 automation readiness summary
- V3 pre-live switchboard summary
- V3 adapter row simulation summary
- V3 adapter payload consistency summary
- Latest selected queue row summary
- Final block reasons
- Safety statement

## Safety requirements

The proof bundle exporter must not:

- call Bolt
- call EDXEIX
- call AADE
- write DB rows
- change queue status
- write production submission tables
- touch V0
- enable live submit
- change cron schedules
- change SQL schema

It may write local artifact files only under:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/v3_pre_live_proof_bundles
```

## Why this phase comes before real adapter work

Before we ever make the future real adapter live-capable, we need a reproducible operator/audit bundle proving:

1. what row was selected,
2. what payload was built,
3. what artifacts were generated,
4. why live submit was blocked or not blocked,
5. that V0 and AADE were untouched.

