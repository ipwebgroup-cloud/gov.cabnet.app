# Patch: EDXEIX Session Extension-Only UI

## What changed

Updated `/ops/edxeix-session.php` so the visible manual Cookie/CSRF form is removed from the operator workflow.

The page now presents the Firefox extension as the normal session-refresh method and remains a diagnostic/read-only readiness page for operators.

## Files included

- `public_html/gov.cabnet.app/ops/edxeix-session.php`
- `docs/EDXEIX_SESSION_EXTENSION_ONLY_UI.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload path

Upload:

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session.php
```

## SQL

No SQL required.

## Verify

Open:

```text
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
```

Expected:

- No manual Cookie/CSRF input form is visible.
- The page explains the Firefox extension workflow.
- Session cookie/CSRF ready remains yes if already captured.
- Submit URL configured remains yes.
- Live flags remain disabled.
- No EDXEIX call is performed.
