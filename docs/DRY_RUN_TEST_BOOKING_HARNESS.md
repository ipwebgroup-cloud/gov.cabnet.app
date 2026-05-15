# Dry-Run Future Booking Test Harness

This patch adds a local-only test harness for the `gov.cabnet.app` Bolt → EDXEIX bridge.

## Purpose

The project is blocked from a full future-trip test until a real Bolt ride exists at least 40–60 minutes in the future. This harness safely creates one synthetic future booking inside `normalized_bookings` so the internal workflow can be tested without live EDXEIX submission.

## Safety posture

The harness:

- does not call Bolt;
- does not call EDXEIX;
- does not submit forms;
- creates only a synthetic LAB/local row;
- uses `source_system = lab_local_test`;
- uses order references beginning with `LAB-LOCAL-FUTURE`;
- optionally marks the row with `is_test_booking = 1` and `never_submit_live = 1` when the SQL migration has been applied.

Existing staging logic blocks LAB rows unless `allow_lab=1` is explicitly provided. The `allow_lab=1` mode is only for local dry-run queue and worker validation.

## Files

```text
gov.cabnet.app_app/src/TestBookingFactory.php
public_html/gov.cabnet.app/ops/test-booking.php
gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
docs/DRY_RUN_TEST_BOOKING_HARNESS.md
```

## Upload paths

```text
/home/cabnet/gov.cabnet.app_app/src/TestBookingFactory.php
/home/cabnet/public_html/gov.cabnet.app/ops/test-booking.php
/home/cabnet/gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql
```

The docs file is for GitHub/project documentation and does not need to be uploaded to the live server unless you keep docs on-server.

## SQL

Run this migration once:

```sql
source /home/cabnet/gov.cabnet.app_sql/2026_04_25_test_booking_flags.sql;
```

Or paste the SQL into phpMyAdmin for the `cabnet_gov` database.

The SQL is additive. It does not delete data.

## Test sequence

1. Open:

```text
https://gov.cabnet.app/ops/test-booking.php
```

2. Confirm the page finds one mapped driver and one mapped vehicle.

3. Type:

```text
CREATE LOCAL DRY RUN BOOKING
```

4. Submit the form.

5. Check preflight:

```text
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
```

6. Confirm normal staging blocks LAB rows:

```text
legacy guarded stage endpoint, review from /ops/public-utility-relocation-plan.php
```

7. Preview LAB staging locally:

```text
legacy guarded stage endpoint, review from /ops/public-utility-relocation-plan.php&allow_lab=1
```

8. Stage a local dry-run job only:

```text
legacy guarded stage endpoint, review from /ops/public-utility-relocation-plan.php&create=1&allow_lab=1
```

9. Preview the dry-run worker:

```text
legacy guarded worker preview endpoint, review from /ops/public-utility-relocation-plan.php
```

10. Record a local audit attempt only:

```text
legacy guarded worker record endpoint, review from /ops/public-utility-relocation-plan.php
```

11. Review readiness:

```text
https://gov.cabnet.app/ops/readiness.php
```

## Expected result

The synthetic row should become a mapping-ready, future-guard-passing LAB candidate. Normal staging without `allow_lab=1` should block it. Staging with `allow_lab=1` should create or preview a local `submission_jobs` row only. The worker should record local dry-run attempts only and must not call EDXEIX.

## Git commit title

```text
Add dry-run future booking simulation harness
```

## Git commit description

```text
Adds a local-only future booking test harness for the Bolt → EDXEIX bridge. The harness creates synthetic LAB/local normalized bookings using existing mapped driver and vehicle records, adds optional never-submit-live safety flags for normalized bookings, documents the dry-run verification flow, and keeps live EDXEIX submission disabled.
```

> Legacy public-root utility endpoints are retained for compatibility but should not be used from operator docs; review them from `/ops/public-utility-relocation-plan.php`.
