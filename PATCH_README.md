# gov.cabnet.app — Ops UI Shell Phase 33: EDXEIX Submit Preflight Gate

## What changed

Adds a reusable read-only EDXEIX submit preflight gate class and a shared-shell page:

- `gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPreflightGate.php`
- `public_html/gov.cabnet.app/ops/edxeix-submit-preflight-gate.php`

The page parses a Bolt pre-ride email, resolves EDXEIX IDs, reads the latest sanitized submit capture metadata, and evaluates technical/live-submit blockers for the future mobile/server-side EDXEIX workflow.

## Production safety

This patch does not modify:

- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

It does not call Bolt, EDXEIX, or AADE. It does not write workflow data, stage jobs, or enable live submit.

## Upload paths

```text
gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPreflightGate.php
→ /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPreflightGate.php

public_html/gov.cabnet.app/ops/edxeix-submit-preflight-gate.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-preflight-gate.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Edxeix/EdxeixSubmitPreflightGate.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-preflight-gate.php
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-preflight-gate.php
```

Expected: login required, read-only page opens, gate result displays after parsing email, live submit remains blocked.
