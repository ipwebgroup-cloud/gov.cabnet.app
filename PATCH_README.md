# Patch README — EDXEIX Placeholder Session Detection

Patch archive: `gov_edxeix_placeholder_session_detection_patch_rooted.zip`

## What changed

This patch prevents copied example/template EDXEIX session values from being counted as production-ready.

Updated files:

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
docs/EDXEIX_PLACEHOLDER_SESSION_DETECTION.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session.php

gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
→ /home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php
```

## SQL

No SQL required.

## Verify

Open:

```text
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
https://gov.cabnet.app/ops/live-submit.php?format=json
```

Expected while the runtime session file still contains example/template values:

```text
placeholder_detected: true
ready: false
Session cookie/CSRF ready: no
```

Expected only after real server-side values are added:

```text
placeholder_detected: false
ready: true
```

Do not paste real cookie or CSRF values into GitHub, ChatGPT, screenshots, or public files.

## Safety

This patch does not call Bolt, call EDXEIX, write to the database, create jobs, expose secrets, or enable live submission.
