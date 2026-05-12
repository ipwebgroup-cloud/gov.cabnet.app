# Phase 61 — Mobile Submit Evidence Center

## What changed

Adds:

```text
public_html/gov.cabnet.app/ops/mobile-submit-evidence-center.php
```

This is a read-only hub for the mobile/server-side EDXEIX submit evidence workflow.

## Upload path

```text
public_html/gov.cabnet.app/ops/mobile-submit-evidence-center.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-center.php
```

## SQL

None.

Uses the existing Phase 59 table if installed:

```text
mobile_submit_evidence_log
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-center.php
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-evidence-center.php
```

## Production safety

No Bolt calls, EDXEIX calls, AADE calls, database writes, workflow staging, live submission behavior, raw email output, or secret output are added.
