# V3 Live-Readiness Starting-Point Alias Fix

Patch: `v3.0.47-live-readiness-start-options-alias-fix`

## Purpose

The V3 forwarded-email test successfully reached `submit_dry_run_ready`, but the live-readiness worker failed while checking `pre_ride_email_v3_starting_point_options`:

```text
ERROR: Unknown column 'lessor_id' in 'WHERE'
```

The table uses the real column names:

```text
edxeix_lessor_id
edxeix_starting_point_id
```

not the old aliases:

```text
lessor_id
starting_point_id
```

## Safety

This is a V3-only maintenance patch.

It does not:

- touch V0 laptop/manual production helper files
- enable live submit
- call EDXEIX
- call AADE
- modify queue logic
- modify cron schedules
- modify SQL schema

## Test evidence before patch

Rows `41` and `56` reached:

```text
submit_dry_run_ready
```

The remaining blocker was only the live-readiness option lookup alias bug.

## Expected after patch

Rows should promote from:

```text
submit_dry_run_ready
```

to:

```text
live_submit_ready
```

The live-submit master gate remains closed:

```text
enabled = no
mode = disabled
adapter = disabled
hard_enable_live_submit = no
```
