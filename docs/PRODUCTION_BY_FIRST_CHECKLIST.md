# Production by the 1st — Remaining Checklist

The app is close to production readiness for the Bolt → EDXEIX bridge, but live submission still requires one real future Bolt test and a separate execution patch.

## Already complete

- Bolt API sync works.
- Reference sync works.
- Order sync works.
- Normalized bookings exist.
- EDXEIX payload preview/preflight exists.
- Local queue staging exists.
- Dry-run worker exists.
- LAB test harness validated.
- LAB cleanup validated.
- Access guard installed.
- Mapping dashboard/editor installed.
- Mapping JSON sanitized.
- Guided novice operations console added.
- Safe `/ops/index.php` route restored as landing page.
- Live-submit gate scaffold added and disabled.

## Current known mappings

- Filippos Giannakopoulos → EDXEIX driver `17585`
- EMX6874 → EDXEIX vehicle `13799`
- EHA2545 → EDXEIX vehicle `5949`

Leave Georgios Zachariou unmapped for now.

## Before the first live EDXEIX submit

Required:

1. Filippos is present/available.
2. A real Bolt ride is created 40–60 minutes in the future.
3. Ride uses Filippos plus EMX6874 or EHA2545.
4. `/bolt_sync_orders.php` imports the ride.
5. `/ops/future-test.php` shows a real future candidate.
6. `/bolt_edxeix_preflight.php?limit=30` shows a valid payload.
7. `/ops/live-submit.php` shows technical payload valid.
8. EDXEIX session/cookie/CSRF status is confirmed.
9. Exact EDXEIX submit URL is confirmed.
10. Andreas explicitly approves the final live HTTP execution patch.

## What is still intentionally blocked

- Automatic live submission.
- Live EDXEIX HTTP POST.
- Live worker execution.
- Any LAB/test row live submission.
- Any unmapped or terminal/past/cancelled trip submission.

## Recommended next safe patch after real future candidate exists

Add the actual EDXEIX HTTP submit transport behind the existing live gate.

This should be one-shot, manually triggered, fully audited, and immediately disabled after the first successful controlled submission.
