# Bolt Evidence Report Export v1.4

Adds a read-only Markdown export page for the gov.cabnet.app Bolt → EDXEIX bridge diagnostics.

## URL

```text
/ops/evidence-report.php
/ops/evidence-report.php?format=md
/ops/evidence-report.php?format=json
```

## Purpose

The page converts existing sanitized Bolt API Visibility Diagnostic timeline snapshots into a copy/paste-ready Markdown report.

This speeds development because Andreas can send one structured text report instead of multiple screenshots.

## Safety

The page:

- does not call Bolt
- does not call EDXEIX
- does not stage jobs
- does not update mappings
- does not write database rows or files
- reads sanitized JSONL visibility snapshots only
- keeps live EDXEIX submission disabled

## Expected use

1. Use `/ops/dev-accelerator.php` during a real future Bolt ride.
2. Capture accepted/assigned, pickup/waiting, trip started, and completed stages.
3. Open `/ops/evidence-report.php`.
4. Copy the Markdown report and paste it into the development chat.
5. Review `/bolt_edxeix_preflight.php?limit=30` only after evidence confirms visibility/readiness.

## Private source file

The report reads the same private sanitized snapshot file used by the Evidence Bundle:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/bolt-api-visibility/YYYY-MM-DD.jsonl
```

No raw Bolt payloads or secrets are read or printed.
