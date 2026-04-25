# Live EDXEIX Submit Production Readiness

This document describes the current production-oriented live-submit gate for the gov.cabnet.app Bolt → EDXEIX bridge.

## Current status

The live-submit gate exists at:

```text
/ops/live-submit.php
```

It is guarded by the ops access guard and remains intentionally blocked. This patch does not perform any EDXEIX HTTP request.

Current intended state:

```text
live_submit_enabled: false
http_submit_enabled: false
live_http_transport_enabled_in_this_patch: false
calls_edxeix: false
```

## What the gate does now

The page analyzes recent normalized bookings and explains:

- whether a selected booking is technically valid
- why live submission is blocked
- which production requirements are still waiting
- whether EDXEIX session/config appears ready
- whether duplicate-protection blockers exist
- which booking would be reviewed later

It also exposes sanitized JSON at:

```text
/ops/live-submit.php?format=json
```

## What the gate does not do yet

It does not:

- call Bolt
- call EDXEIX
- submit a live EDXEIX form
- create queue jobs
- write to the database on GET
- enable live transport

## Current known blockers before first live submit

Before live submission can be added, all of these must be resolved:

1. A real future Bolt candidate must exist.
2. The candidate must use Filippos Giannakopoulos mapped to EDXEIX driver ID `17585`.
3. The candidate must use a mapped vehicle:
   - `EMX6874 → 13799`, or
   - `EHA2545 → 5949`.
4. The candidate must start safely in the future.
5. The candidate must not be finished, cancelled, expired, terminal, or LAB/local.
6. The EDXEIX server-side session/cookie/CSRF must be confirmed ready.
7. The exact EDXEIX submit URL/form action must be configured server-side.
8. Duplicate successful submission checks must be clear.
9. Andreas must explicitly approve the final live HTTP transport patch.

## Future live run sequence

The future controlled live run should be:

1. Create/schedule a real Bolt ride 40–60 minutes in the future.
2. Sync Bolt orders.
3. Open `/ops/future-test.php`.
4. Open `/bolt_edxeix_preflight.php?limit=30`.
5. Verify payload data manually.
6. Stage and record a dry-run attempt first.
7. Confirm live attempts remain `0`.
8. Configure the exact server-only live config for one booking/order.
9. Apply the final HTTP transport patch.
10. Submit once.
11. Confirm response/audit.
12. Disable live config again.

## Safety rule

No historical, terminal, cancelled, LAB, test, expired, invalid, duplicate, or insufficiently future booking may ever be submitted live.
