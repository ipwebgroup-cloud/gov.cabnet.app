# gov.cabnet.app Patch — Bolt Evidence Report Export v1.4

## What changed

Adds a read-only Evidence Report Export page:

```text
/ops/evidence-report.php
```

The page turns sanitized Bolt visibility timeline entries into a copy/paste-ready Markdown report.

## Files included

```text
public_html/gov.cabnet.app/ops/evidence-report.php
docs/BOLT_EVIDENCE_REPORT_EXPORT.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/evidence-report.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/evidence-report.php
```

Optional repo/docs files:

```text
docs/BOLT_EVIDENCE_REPORT_EXPORT.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/evidence-report.php
```

Open:

```text
https://gov.cabnet.app/ops/evidence-report.php
https://gov.cabnet.app/ops/evidence-report.php?format=md
https://gov.cabnet.app/ops/evidence-report.php?format=json
```

## Expected result

- Page loads cleanly.
- Markdown output loads cleanly.
- JSON output loads cleanly.
- No Bolt call is made.
- No EDXEIX call is made.
- No jobs are staged.
- No mappings are changed.
- Live submit remains disabled.

## Git commit title

```text
Add Bolt evidence report export
```

## Git commit description

```text
Adds a read-only Markdown/JSON evidence report exporter for sanitized Bolt visibility snapshots. The page helps summarize real future ride test evidence for development review while keeping Bolt calls, EDXEIX calls, job staging, mapping updates, and live submission disabled.
```
