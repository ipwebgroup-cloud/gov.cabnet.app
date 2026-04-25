# Patch README — Bolt API Visibility Diagnostic

## What changed

Adds a safe, guarded Bolt API visibility diagnostic for observing whether the current Bolt Fleet orders endpoint exposes an active trip before completion.

The patch does **not** enable live EDXEIX submission and does **not** call EDXEIX.

## Files included

```text
gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php
public_html/gov.cabnet.app/ops/bolt-api-visibility.php
public_html/gov.cabnet.app/ops/bolt-api-visibility-run.php
docs/BOLT_API_VISIBILITY_DIAGNOSTIC.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Exact upload paths

Upload each file to the matching server path:

```text
gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php
→ /home/cabnet/gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php

public_html/gov.cabnet.app/ops/bolt-api-visibility.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/bolt-api-visibility.php

public_html/gov.cabnet.app/ops/bolt-api-visibility-run.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/bolt-api-visibility-run.php

docs/BOLT_API_VISIBILITY_DIAGNOSTIC.md
→ repository docs/BOLT_API_VISIBILITY_DIAGNOSTIC.md

HANDOFF.md
→ repository/server project root HANDOFF.md

CONTINUE_PROMPT.md
→ repository/server project root CONTINUE_PROMPT.md
```

## SQL to run

No SQL changes are required.

## Verification URLs

Open:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php
```

Run one safe snapshot:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=0&hours_back=24&sample_limit=20
```

Run one recorded Filippos/EMX6874 snapshot:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=1&hours_back=24&sample_limit=20&watch_driver_uuid=57256761-d21b-4940-a3ca-bdcec5ef6af1&watch_vehicle_plate=EMX6874&label=filippos-emx6874-probe
```

JSON endpoint:

```text
https://gov.cabnet.app/ops/bolt-api-visibility-run.php?record=0&hours_back=24&sample_limit=20
```

## Expected result

The page loads inside the guarded ops area and shows:

- `EDXEIX LIVE SUBMIT OFF`
- `BOLT DRY-RUN PROBE`
- `SANITIZED SUMMARY ONLY`
- current `orders_seen`
- sanitized order samples if the current Bolt endpoint exposes any orders
- today's private sanitized timeline when `record=1` is used

## Private artifact path

Recorded snapshots are appended to:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/bolt-api-visibility/YYYY-MM-DD.jsonl
```

## Git commit title

```text
Add Bolt API visibility diagnostic
```

## Git commit description

```text
Adds a guarded, read-only Bolt API visibility diagnostic for the gov.cabnet.app Bolt → EDXEIX bridge. The new ops page and JSON endpoint probe the existing Bolt order sync path in dry-run mode only, summarize sanitized visibility snapshots, optionally record a private JSONL timeline, and provide a repeatable workflow for confirming whether active Bolt trips are visible before completion. No EDXEIX live submission, EDXEIX HTTP transport, queue staging, SQL changes, secrets, raw payloads, cookies, or session data are introduced.
```
