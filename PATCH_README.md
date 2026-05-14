# Patch v3.0.46 — Ops Index V3 Entry

## What changed

Updates the main Operations Console index so it clearly points to the verified V3 monitoring pages.

## Files included

```text
public_html/gov.cabnet.app/ops/index.php
docs/V3_OPS_INDEX_ENTRY.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/index.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/index.php
```

Keep docs in the local GitHub Desktop repo.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/index.php

su -s /bin/bash cabnet -c "/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_storage_check.php"

tail -n 120 /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log | egrep "cron start|ERROR|Pulse summary|finish exit_code" || true
```

## Verification URL

```text
https://gov.cabnet.app/ops/index.php
```

## Expected result

The Ops Index loads and links to:

```text
V3 Control Center
Compact Monitor
Queue Focus
Pulse Focus
Readiness Focus
Storage Check
```

Safety posture remains:

```text
V0 untouched
V3 only
Live submit disabled
No EDXEIX calls
No AADE calls
No DB writes from this page
No SQL changes
```

## Commit title

```text
Add V3 entry links to Ops index
```

## Commit description

```text
Updates the main Operations Console index to provide a coherent V3 entry point linking the verified V3 Control Center, Compact Monitor, Queue Focus, Pulse Focus, Readiness Focus, and Storage Check pages.

This is a V3-only UI/navigation patch. It does not touch V0 laptop/manual production helper files, V0 dependencies, live-submit behavior, EDXEIX calls, AADE behavior, queue mutation logic, cron behavior, or SQL schema.
```
