# gov.cabnet.app Bolt Mail Bridge v4.4 Patch

## What this patch does

This patch improves the safe production monitor layer for Bolt mail intake and auto dry-run evidence.

It adds:

- clearer Mail Status metrics for dry-run evidence
- evidence detail view by `evidence_id`
- stored payload/mapping/safety snapshots on the evidence page
- direct links between local mail bookings and evidence records
- synthetic-only cleanup controls requiring `DELETE_SYNTHETIC_ONLY`
- raw preflight JSON guard display alignment to `edxeix.future_start_guard_minutes`
- 2-minute fallback guard alignment in shared config loading

## Safety

This patch does not enable live submit.

It does not:

- create `submission_jobs`
- create `submission_attempts`
- POST to EDXEIX
- change credentials
- include config secrets
- include session files
- include logs
- include SQL dumps

Expected config remains:

```text
app.dry_run=true
edxeix.live_submit_enabled=false
edxeix.future_start_guard_minutes=2
```

## Files included

```text
public_html/gov.cabnet.app/ops/mail-status.php
public_html/gov.cabnet.app/ops/mail-auto-dry-run.php
public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php
public_html/gov.cabnet.app/bolt_edxeix_preflight.php
gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php
gov.cabnet.app_app/lib/bolt_sync_lib.php
docs/BOLT_MAIL_PRODUCTION_MONITOR_V4_4.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

Server files:

```text
public_html/gov.cabnet.app/ops/mail-status.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php

public_html/gov.cabnet.app/ops/mail-auto-dry-run.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mail-auto-dry-run.php

public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php

public_html/gov.cabnet.app/bolt_edxeix_preflight.php
→ /home/cabnet/public_html/gov.cabnet.app/bolt_edxeix_preflight.php

gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php
→ /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php

gov.cabnet.app_app/lib/bolt_sync_lib.php
→ /home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php
```

Repository/package documentation files:

```text
docs/BOLT_MAIL_PRODUCTION_MONITOR_V4_4.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

Keep these documentation files at the repository/package root. They are included for continuity and Git history; they are not required for the live runtime path.

## SQL

No SQL migration is required.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Mail/BoltMailDryRunEvidenceService.php
php -l /home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-status.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-dry-run-evidence.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mail-auto-dry-run.php
php -l /home/cabnet/public_html/gov.cabnet.app/bolt_edxeix_preflight.php
```

```bash
php -r '$c=require "/home/cabnet/gov.cabnet.app_config/config.php"; echo "app_timezone=".($c["app"]["timezone"] ?? "MISSING").PHP_EOL; echo "dry_run=".(!empty($c["app"]["dry_run"]) ? "true" : "false").PHP_EOL; echo "future_start_guard_minutes=".($c["edxeix"]["future_start_guard_minutes"] ?? "MISSING").PHP_EOL; echo "live_submit_enabled=".(!empty($c["edxeix"]["live_submit_enabled"]) ? "true" : "false").PHP_EOL;'
```

Expected:

```text
app_timezone=Europe/Athens
dry_run=true
future_start_guard_minutes=2
live_submit_enabled=false
```

## Verification URLs

```text
https://gov.cabnet.app/ops/mail-status.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/mail-auto-dry-run.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/mail-dry-run-evidence.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
```

## Expected result

- Dashboards show dry-run evidence and direct evidence links.
- Raw preflight JSON shows `guard_minutes=2` when config is set to 2.
- Local dry-run workflow remains active and safe.
- No submission jobs are created.
- No submission attempts are created.
- No EDXEIX POST occurs.
