# Bolt Dev Accelerator

## Purpose

The Bolt Dev Accelerator adds a safe operator/development cockpit at:

```text
/ops/dev-accelerator.php
```

It is designed to speed up the next real future Bolt ride test without enabling live EDXEIX submission.

## Safety contract

The page:

- Does not submit to EDXEIX.
- Does not stage jobs.
- Does not edit mappings.
- Does not print raw Bolt payloads.
- Does not call Bolt on normal page load.
- Only calls Bolt when the operator clicks a dry-run probe/capture button.
- Uses the existing `gov_bolt_visibility_build_snapshot()` diagnostic path for optional probes.
- Records sanitized private JSONL snapshots only when `record=1` is used.

## What it speeds up

The previous workflow required jumping between multiple pages while a real Bolt ride was evolving. This page consolidates the critical test actions:

1. Readiness passport.
2. Fast accepted/assigned snapshot.
3. Fast pickup/waiting snapshot.
4. Fast trip-started snapshot.
5. Fast completed snapshot.
6. Auto-watch link to the existing Bolt visibility diagnostic.
7. Copy/paste verification URLs.
8. JSON output for quick status sharing.

## URLS

```text
https://gov.cabnet.app/ops/dev-accelerator.php
https://gov.cabnet.app/ops/dev-accelerator.php?format=json
```

## Recommended test path

1. Open `/ops/dev-accelerator.php`.
2. Confirm the readiness passport is clean.
3. Create one real Bolt ride 40–60 minutes in the future.
4. Prefer Filippos Giannakopoulos with EMX6874 for the first real test.
5. Click the capture buttons as the trip evolves:
   - Accepted / assigned
   - Pickup / waiting
   - Trip started
   - Completed
6. Review `/ops/bolt-api-visibility.php` and `/ops/future-test.php`.
7. Only review preflight JSON; do not submit live.

## Known mapping reminders

```text
Filippos Giannakopoulos
Bolt UUID: 57256761-d21b-4940-a3ca-bdcec5ef6af1
EDXEIX driver ID: 17585

EMX6874
EDXEIX vehicle ID: 13799

EHA2545
EDXEIX vehicle ID: 5949
```

Leave Georgios Zachariou unmapped until his exact EDXEIX driver ID is independently confirmed.

## Verification

Run syntax check after upload:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/dev-accelerator.php
```

Open:

```text
https://gov.cabnet.app/ops/dev-accelerator.php
```

Expected result:

- Page loads without exposing secrets.
- It shows readiness status and fast capture buttons.
- Default page load does not call Bolt.
- Capture buttons run dry-run Bolt visibility probes only.
- Live EDXEIX submission remains disabled.
