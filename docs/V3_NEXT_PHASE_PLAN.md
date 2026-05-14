# V3 Next Phase Plan

## Next recommended patch

`v3.0.69-v3-adapter-payload-consistency-harness`

## Purpose

Create a read-only comparison harness that checks whether the same final EDXEIX field package is produced consistently by:

1. package export,
2. adapter row simulation,
3. final rehearsal preparation.

## Required behavior

The harness should:

- select a V3 row
- build the EDXEIX field package
- compute SHA-256 hashes
- compare required field keys
- show missing/different fields
- show package artifact count
- show final block reasons
- remain read-only

## Safety boundary

The patch must not:

- call Bolt
- call EDXEIX
- call AADE
- write DB rows
- change queue status
- write production submission tables
- touch V0
- enable live submit
- change SQL
- change cron schedules
