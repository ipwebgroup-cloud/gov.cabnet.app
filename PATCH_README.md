# Patch: LAB/Test Safety Output Clarification

## What changed

This patch clarifies the output of the Bolt → EDXEIX dry-run/preflight flow so LAB/test bookings no longer appear live-safe just because their payload is technically valid.

## Files included

```text
public_html/gov.cabnet.app/bolt_edxeix_preflight.php
public_html/gov.cabnet.app/bolt_stage_edxeix_jobs.php
public_html/gov.cabnet.app/bolt_submission_worker.php
gov.cabnet.app_sql/2026_04_25_mark_local_dry_run_attempts.sql
docs/LAB_TEST_SAFETY_OUTPUT.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload paths

```text
public_html/gov.cabnet.app/bolt_edxeix_preflight.php
→ /home/cabnet/public_html/gov.cabnet.app/bolt_edxeix_preflight.php

public_html/gov.cabnet.app/bolt_stage_edxeix_jobs.php
→ /home/cabnet/public_html/gov.cabnet.app/bolt_stage_edxeix_jobs.php

public_html/gov.cabnet.app/bolt_submission_worker.php
→ /home/cabnet/public_html/gov.cabnet.app/bolt_submission_worker.php

gov.cabnet.app_sql/2026_04_25_mark_local_dry_run_attempts.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_04_25_mark_local_dry_run_attempts.sql

docs/LAB_TEST_SAFETY_OUTPUT.md
→ docs/LAB_TEST_SAFETY_OUTPUT.md in GitHub

HANDOFF.md
→ HANDOFF.md in GitHub

CONTINUE_PROMPT.md
→ CONTINUE_PROMPT.md in GitHub
```

## Optional SQL

Run this only if you want the existing local LAB attempt row to be classified as dry-run in readiness:

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_04_25_mark_local_dry_run_attempts.sql
```

The SQL updates only attempts linked to LAB/test/never-live normalized bookings.

## Verify

```text
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30
https://gov.cabnet.app/bolt_stage_edxeix_jobs.php?limit=30&allow_lab=1
https://gov.cabnet.app/bolt_submission_worker.php?limit=30&allow_lab=1
https://gov.cabnet.app/bolt_submission_worker.php?limit=30&record=1&allow_lab=1
https://gov.cabnet.app/ops/jobs.php
https://gov.cabnet.app/ops/readiness.php
```

## Expected result

LAB/test rows should show:

```text
technical_payload_valid: true
dry_run_allowed or dry_run_stage_allowed: true when allow_lab=1
live_submission_allowed: false
submission_safe: false
```

The worker should still state that no EDXEIX submission was performed.
