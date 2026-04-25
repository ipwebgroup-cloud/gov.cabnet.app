# Patch: Firefox EDXEIX Capture Fixed URL + No Phrase

## What changed

- Updated the Firefox extension to use the fixed EDXEIX submit URL automatically.
- Removed the `SAVE EDXEIX SESSION SERVER SIDE` confirmation phrase from the extension workflow.
- Updated the server capture endpoint so the extension only needs to send cookie/header and CSRF token.
- Server-side live submission flags remain forced disabled.

## Files included

```text
public_html/gov.cabnet.app/ops/edxeix-session-capture.php
tools/firefox-edxeix-session-capture/manifest.json
tools/firefox-edxeix-session-capture/popup.html
tools/firefox-edxeix-session-capture/popup.css
tools/firefox-edxeix-session-capture/popup.js
tools/firefox-edxeix-session-capture/README.md
docs/EDXEIX_FIREFOX_EXTENSION_FIXED_URL_NO_PHRASE.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/edxeix-session-capture.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session-capture.php
```

## Firefox extension

Reload temporary add-on from:

```text
tools/firefox-edxeix-session-capture/manifest.json
```

## SQL

No SQL required.

## Safety

No Bolt call, no EDXEIX call, no database write, no live submission, and no secret output are introduced.
