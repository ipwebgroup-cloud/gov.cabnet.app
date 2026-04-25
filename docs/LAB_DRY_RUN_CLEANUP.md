# LAB Dry-Run Cleanup Tool

This patch adds a small operations utility:

```text
/ops/cleanup-lab.php
```

The tool is designed to remove local LAB/test dry-run records after a test cycle.

## Safety behavior

- Preview/read-only by default.
- Does not call Bolt.
- Does not call EDXEIX.
- Does not post EDXEIX forms.
- Requires a POST request and exact confirmation phrase before deleting anything.
- Only targets LAB/local/test/never-submit-live normalized bookings and their linked local queue/attempt rows.
- Refuses cleanup if a linked attempt row is not clearly marked as dry-run/no-live.

## Confirmation phrase

```text
DELETE LOCAL LAB DRY RUN DATA
```

## Delete order

1. Linked dry-run attempts from `submission_attempts`.
2. Linked local jobs from `submission_jobs`.
3. LAB/test rows from `normalized_bookings`.

## Verification flow

Before cleanup:

```text
/ops/readiness.php
/ops/jobs.php
/ops/cleanup-lab.php
```

After cleanup:

```text
/ops/readiness.php
/ops/jobs.php
/bolt_readiness_audit.php
```

Expected result after cleanup:

- LAB normalized rows: `0`
- Staged LAB jobs: `0`
- Local submission jobs: `0`
- Live attempts indicated: `0`

Mapping coverage may still remain partial until all Bolt drivers/vehicles are mapped.
