# Patch: Firefox extension verification buttons

## Summary

Updates the private CABnet EDXEIX Session Capture Firefox extension to version `0.1.2`.

The extension now has explicit buttons to open:

- `https://gov.cabnet.app/ops/edxeix-session.php`
- `https://gov.cabnet.app/ops/live-submit.php`

This replaces relying on a normal popup link that may not appear to do anything in Firefox.

## Files included

- `tools/firefox-edxeix-session-capture/manifest.json`
- `tools/firefox-edxeix-session-capture/popup.html`
- `tools/firefox-edxeix-session-capture/popup.css`
- `tools/firefox-edxeix-session-capture/popup.js`
- `tools/firefox-edxeix-session-capture/README.md`
- `docs/EDXEIX_FIREFOX_EXTENSION_VERIFY_BUTTONS.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`
- `PATCH_README.md`

## Upload / install

No server runtime file changed in this patch.

Replace the local extension files under:

`tools/firefox-edxeix-session-capture/`

Then in Firefox:

`about:debugging → This Firefox → Reload`

or remove and load the temporary add-on again using `manifest.json`.

## SQL

No SQL required.

## Safety

No Bolt request, EDXEIX request, database write, live HTTP transport, secret output, or live submission behavior is introduced.
