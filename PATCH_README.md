# gov.cabnet.app Patch — Bolt API Visibility Diagnostic v1.1

## Purpose

Enhance the installed Bolt API Visibility Diagnostic after server screenshots confirmed the page works but showed:

```text
orders_seen: 1
sanitized_samples: 0
watch matches: no
```

This patch keeps the diagnostic read-only and adds a local normalized bookings summary so Andreas can see what the dry-run sync imported without exposing raw Bolt payloads.

## Safety

- EDXEIX live submission remains disabled.
- No EDXEIX HTTP submission is added.
- No queue jobs are staged.
- No SQL migration is required.
- No raw Bolt payloads, passenger details, API keys, cookies, CSRF values, or session data are printed.

## Files included

```text
public_html/gov.cabnet.app/ops/bolt-api-visibility.php
gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php
docs/BOLT_API_VISIBILITY_DIAGNOSTIC.md
PATCH_README.md
HANDOFF.md
CONTINUE_PROMPT.md
```

`public_html/gov.cabnet.app/ops/bolt-api-visibility-run.php` is unchanged from v1.0 and does not need re-upload unless missing.

## Upload paths

```text
public_html/gov.cabnet.app/ops/bolt-api-visibility.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/bolt-api-visibility.php

gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php
→ /home/cabnet/gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php
```

## SQL

```text
No SQL changes required.
```

## Verification

Open:

```text
https://gov.cabnet.app/ops/bolt-api-visibility.php?run=1&record=0&hours_back=24&sample_limit=20
```

Expected additions:

- Current snapshot shows `Local recent rows`.
- Page includes `Dry-run sync explanation`.
- Page includes `Recent local normalized Bolt bookings`.
- JSON endpoint includes `diagnostic_version: 1.1.0`.
