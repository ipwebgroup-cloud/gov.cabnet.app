# HANDOFF — gov.cabnet.app V3 Current State

V3 forwarded-email test proved intake/parser/mapping/future guard/starting-point guard/dry-run readiness.

Rows 41 and 56 reached `submit_dry_run_ready` after inserting lessor 3814 start option 6467495 into `pre_ride_email_v3_starting_point_options`.

Remaining blocker found:

```text
pre_ride_email_v3_live_submit_readiness.php
ERROR: Unknown column 'lessor_id' in 'WHERE'
```

Cause: live-readiness worker still queries `pre_ride_email_v3_starting_point_options` using old alias columns `lessor_id` / `starting_point_id` instead of real columns `edxeix_lessor_id` / `edxeix_starting_point_id`.

Patch v3.0.47 adds a V3-only maintenance script to patch that file.

Live submit remains disabled.
V0 laptop/manual production helper remains untouched.
