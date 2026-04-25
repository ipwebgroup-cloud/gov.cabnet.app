# Bolt API Visibility Diagnostic

## Purpose

This patch adds a guarded diagnostic page for the gov.cabnet.app Bolt → EDXEIX bridge.

The diagnostic answers one question:

> At which ride stage does the current Bolt Fleet orders endpoint expose a real Bolt trip?

The 2026-04-25 live test showed the trip was not visible while accepted, picked up/waiting, or started, and became visible only after completion. This diagnostic gives Andreas a repeatable way to capture that evidence with sanitized snapshots.

## Safety posture

This diagnostic is safe by design:

- It does **not** enable live EDXEIX submission.
- It does **not** call EDXEIX.
- It does **not** stage EDXEIX jobs.
- It calls the existing Bolt order sync through `gov_bolt_sync_orders($hoursBack, true)` only.
- It records only sanitized summaries when `record=1` is used.
- It does not print or store raw Bolt payloads, API tokens, cookies, CSRF values, emails, phone numbers, passenger names, or session data.

## New URLs

HTML operator page:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php
```

JSON snapshot endpoint:

```text
https://gov.cabnet.app/ops/bolt-api-visibility-run.php
```

Suggested Filippos/EMX6874 probe:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=1&hours_back=24&sample_limit=20&watch_driver_uuid=57256761-d21b-4940-a3ca-bdcec5ef6af1&watch_vehicle_plate=EMX6874&label=filippos-emx6874-probe
```

Suggested auto-refresh probe during the active ride:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=1&hours_back=24&sample_limit=20&watch_driver_uuid=57256761-d21b-4940-a3ca-bdcec5ef6af1&watch_vehicle_plate=EMX6874&label=filippos-emx6874-probe&refresh=20
```

## Private artifact output

When `record=1`, sanitized timeline rows are appended to:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/bolt-api-visibility/YYYY-MM-DD.jsonl
```

This file is intentionally outside public webroot.

## Recommended live test sequence

Use this only when Filippos and a mapped vehicle are available.

1. Create/schedule the Bolt ride 40–60 minutes in the future where possible.
2. Run and record one snapshot after Filippos accepts the ride.
3. Run another snapshot after pickup/waiting.
4. Run another snapshot after the trip starts.
5. Run the final snapshot after completion.
6. Compare `orders_seen`, status counts, and watch matches across the timeline.

## Expected diagnostic result based on the 2026-04-25 test

During accepted/picked-up/started stages, the current endpoint may show:

```json
"orders_seen": 0
```

After completion, it may show:

```json
"orders_seen": 1
```

If this pattern repeats, the project should not depend on that Bolt endpoint for live pre-departure EDXEIX submission unless Bolt exposes a different endpoint, webhook, or scheduled-job feed.

## Files added

```text
gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php
public_html/gov.cabnet.app/ops/bolt-api-visibility.php
public_html/gov.cabnet.app/ops/bolt-api-visibility-run.php
docs/BOLT_API_VISIBILITY_DIAGNOSTIC.md
```

## SQL

No SQL changes are required.
