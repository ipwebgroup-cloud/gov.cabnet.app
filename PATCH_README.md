# Patch README — EDXEIX Session Capture Prep

Patch archive: `gov_edxeix_session_capture_prep_patch_rooted.zip`

## What changed

This patch prepares the EDXEIX session and submit URL setup process without enabling live submission.

It adds:

```text
gov.cabnet.app_config_examples/edxeix_session.example.json
gov.cabnet.app_app/storage/runtime/edxeix_session.example
docs/EDXEIX_SESSION_CAPTURE_TEMPLATE.md
docs/EDXEIX_PRODUCTION_SECRETS_RULES.md
```

It updates:

```text
public_html/gov.cabnet.app/ops/help.php
gov.cabnet.app_config_examples/live_submit.example.php
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/help.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/help.php

gov.cabnet.app_config_examples/edxeix_session.example.json
→ /home/cabnet/gov.cabnet.app_config_examples/edxeix_session.example.json

gov.cabnet.app_config_examples/live_submit.example.php
→ /home/cabnet/gov.cabnet.app_config_examples/live_submit.example.php

gov.cabnet.app_app/storage/runtime/edxeix_session.example
→ /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.example
```

## SQL

No SQL required.

## Real server-only setup command later

Only when preparing the real EDXEIX session on the server:

```bash
cp /home/cabnet/gov.cabnet.app_config_examples/edxeix_session.example.json \
  /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
chown cabnet:cabnet /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
chmod 600 /home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

Then edit the real file on the server only.

## Verify

```text
https://gov.cabnet.app/ops/help.php
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
```

Expected current state until the real values are prepared:

```text
Session cookie/CSRF ready: no
Submit URL configured: no
No secrets displayed
No EDXEIX call performed
```

## Safety

This patch does not:

```text
call Bolt
call EDXEIX
write to the database
create queue jobs
submit forms
enable live submission
print cookies or CSRF tokens
```
