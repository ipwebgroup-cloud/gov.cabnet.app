# gov.cabnet.app — Dry-Run Future Booking Harness Patch

## What changed

This patch adds a safe local-only future booking simulator for the Bolt → EDXEIX bridge. It lets the internal preflight, queue staging, worker dry-run audit, and readiness workflow be tested without a real future Bolt ride.

## Files included

```text
gov.cabnet.app_app/src/TestBookingFactory.php
public_html/gov.cabnet.app/ops/test-booking.php
gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
docs/DRY_RUN_TEST_BOOKING_HARNESS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/src/TestBookingFactory.php
→ /home/cabnet/gov.cabnet.app_app/src/TestBookingFactory.php

public_html/gov.cabnet.app/ops/test-booking.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/test-booking.php

gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
```

For GitHub commit, also include:

```text
docs/DRY_RUN_TEST_BOOKING_HARNESS.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL to run

Run once in phpMyAdmin or MySQL CLI:

```sql
source /home/cabnet/gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql;
```

## Verification URLs

```text
https://gov.cabnet.app/ops/test-booking.php
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30
https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30&allow_lab=1
https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30&create=1&allow_lab=1
https://gov.cabnet.app/bolt_submission_worker.php?limit=30&allow_lab=1
https://gov.cabnet.app/bolt_submission_worker.php?limit=30&record=1&allow_lab=1
https://gov.cabnet.app/ops/readiness.php
```

## Expected result

- `/ops/test-booking.php` previews one future LAB/local booking using an existing mapped driver and vehicle.
- Creating the row inserts one synthetic record into `normalized_bookings` only.
- Normal staging without `allow_lab=1` blocks the LAB row.
- Staging with `allow_lab=1` can create a local `submission_jobs` record only.
- The worker records local dry-run audit attempts only.
- No EDXEIX HTTP request is performed.

## Git commit title

```text
Add dry-run future booking simulation harness
```

## Git commit description

```text
Adds a local-only future booking test harness for the Bolt → EDXEIX bridge. The harness creates synthetic LAB/local normalized bookings using existing mapped driver and vehicle records, adds optional never-submit-live safety flags for normalized bookings, documents the dry-run verification flow, and keeps live EDXEIX submission disabled.
```
