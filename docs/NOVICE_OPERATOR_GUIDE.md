# Novice Operator Guide — gov.cabnet.app Bolt → EDXEIX Bridge

## Purpose

This guide explains the guarded operations workflow in plain language.

The current system is **preflight / dry-run only**. Live EDXEIX submission is disabled and must remain disabled until Andreas explicitly approves a separate live-submit patch.

## Start here

Open:

```text
https://gov.cabnet.app/ops/index.php
```

The page shows a 1–6 guided workflow:

1. Check System
2. Check Mappings
3. Wait for Bolt Ride
4. Review Preflight
5. Dry-Run Only
6. Stop Before Live

## Current operating state

- Ops access guard is active.
- LAB/test rows have been cleaned up.
- Local submission jobs are currently expected to be zero.
- Live EDXEIX attempts are expected to be zero.
- The system is waiting for a real future Bolt ride.

## Known safe first-test mapping

Driver:

```text
Filippos Giannakopoulos → EDXEIX driver ID 17585
```

Vehicles:

```text
EMX6874 → EDXEIX vehicle ID 13799
EHA2545 → EDXEIX vehicle ID 5949
```

Do not map Georgios Zachariou until his exact EDXEIX driver ID is independently confirmed.

## Real future test sequence

1. Open `/ops/readiness.php` and confirm the readiness state is clean.
2. Open `/ops/future-test.php` and confirm it says ready to create a real future test ride.
3. Confirm mappings in `/ops/mappings.php`.
4. When Filippos is present, create one real Bolt ride 40–60 minutes in the future.
5. Run `/bolt_sync_orders.php`.
6. Reopen `/ops/future-test.php`.
7. If a real candidate appears, open `/bolt_edxeix_preflight.php?limit=30`.
8. Review the payload.
9. Optionally stage and record local dry-run only.
10. Confirm live EDXEIX attempts remain zero.
11. Stop before live submission.

## Glossary

- **Mapping**: The link between Bolt driver/vehicle and EDXEIX driver/vehicle ID.
- **Preflight**: A preview of the EDXEIX payload. It does not submit live.
- **Future guard**: The ride must be at least the configured number of minutes in the future.
- **Terminal status**: Finished, cancelled, expired, failed, or rejected. These must never be submitted.
- **LAB row**: A local dry-run test row. It must never be submitted live.
- **Dry run**: Local validation only. No EDXEIX request is sent.
- **Live submission**: A real EDXEIX submission. Disabled in the current system.

## Safety reminder

No page in this guided GUI enables live EDXEIX submission.
