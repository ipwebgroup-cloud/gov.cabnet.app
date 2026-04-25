# Patch: Firefox EDXEIX Session Capture Extension

## What changed

Added a guarded server endpoint:

```text
public_html/gov.cabnet.app/ops/edxeix-session-capture.php
```

Added a private Firefox extension:

```text
tools/firefox-edxeix-session-capture/
```

The extension captures the active EDXEIX form action URL, hidden `_token`, and cookies, then sends them to the server endpoint for server-only storage.

## Safety

This patch does not call Bolt, does not call EDXEIX, does not submit live forms, does not write to the database, does not create jobs, and does not enable live submission.

The server endpoint forces:

```text
live_submit_enabled = false
http_submit_enabled = false
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/edxeix-session-capture.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session-capture.php
```

Commit the extension folder:

```text
tools/firefox-edxeix-session-capture/
```

Commit docs:

```text
docs/EDXEIX_FIREFOX_SESSION_CAPTURE_EXTENSION.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## SQL

No SQL required.

## Verification

Open:

```text
https://gov.cabnet.app/ops/edxeix-session-capture.php
```

Expected GET output:

```text
read_only: true
calls_edxeix: false
writes_database: false
prints_secrets: false
```

Then load the Firefox extension temporarily and test from the logged-in EDXEIX create form.
