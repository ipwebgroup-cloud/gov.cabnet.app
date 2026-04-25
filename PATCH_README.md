# Patch: EDXEIX Session Guarded Web Form

## What changed

Updated `/ops/edxeix-session.php` to add a guarded web form for saving EDXEIX submit URL, cookie header, and CSRF token to server-only files.

## Files included

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
docs/EDXEIX_SESSION_WEB_FORM.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session.php
```

## SQL

No SQL required.

## Verification

Open:

```text
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
```

Expected:

- form is visible,
- no secret values are displayed,
- GET makes no writes,
- POST saves server-only files only after exact confirmation phrase,
- live submit flags remain disabled.

## Safety

This patch does not call Bolt, call EDXEIX, write to the database, create jobs, expose secrets, or enable live submission.
