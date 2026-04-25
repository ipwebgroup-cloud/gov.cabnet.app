# Patch: EDXEIX Session Auto-Extract Helper

## What changed

Updates `/ops/edxeix-session.php` with a fast paste helper that can extract:

- Cookie request header
- EDXEIX form action / submit URL
- hidden `_token` CSRF token

from pasted EDXEIX Developer Tools text.

The operator can paste the request headers and form HTML/snippet, press **Extract into fields**, review that fields were populated, type the safety phrase, and save server-side.

## Files included

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
docs/EDXEIX_SESSION_AUTO_EXTRACT_HELPER.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session.php
```

Commit docs at repository root.

## SQL

No SQL required.

## Verification

Open:

```text
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
```

Expected:

- Fast Paste + Auto-Extract Helper is visible.
- Manual fields still exist.
- Existing EDXEIX session readiness remains ready if already saved.
- Live flags remain disabled.
- No EDXEIX HTTP request is performed.

## Safety

This patch does not call Bolt, does not call EDXEIX, does not write to database, does not enable live submission, and does not print saved cookie/CSRF values.
