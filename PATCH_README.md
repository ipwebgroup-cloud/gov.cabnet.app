# Patch: Disabled Live EDXEIX Submit Gate

## Purpose

Prepare the final live EDXEIX submission control path while keeping live HTTP submission blocked.

## Files included

```text
.gitignore
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
public_html/gov.cabnet.app/ops/live-submit.php
gov.cabnet.app_config_examples/live_submit.example.php
gov.cabnet.app_sql/2026_04_25_live_submission_audit.sql
docs/LIVE_EDXEIX_SUBMIT_GATE.md
docs/PRODUCTION_BY_FIRST_CHECKLIST.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
→ /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php

public_html/gov.cabnet.app/ops/live-submit.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/live-submit.php

gov.cabnet.app_config_examples/live_submit.example.php
→ /home/cabnet/gov.cabnet.app_config_examples/live_submit.example.php

gov.cabnet.app_sql/2026_04_25_live_submission_audit.sql
→ /home/cabnet/gov.cabnet.app_sql/2026_04_25_live_submission_audit.sql
```

## SQL to run

```bash
mysql cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_04_25_live_submission_audit.sql
```

## Optional server config copy

```bash
cp /home/cabnet/gov.cabnet.app_config_examples/live_submit.example.php /home/cabnet/gov.cabnet.app_config/live_submit.php
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_config/live_submit.php
chmod 640 /home/cabnet/gov.cabnet.app_config/live_submit.php
```

Do not commit `/home/cabnet/gov.cabnet.app_config/live_submit.php`.

## Verification

```text
https://gov.cabnet.app/ops/live-submit.php
https://gov.cabnet.app/ops/live-submit.php?format=json
```

Expected:

- config live disabled
- HTTP config disabled
- live HTTP transport blocked
- no EDXEIX HTTP request performed

## Safety

This patch does not call Bolt, does not call EDXEIX, and does not enable live submission.
