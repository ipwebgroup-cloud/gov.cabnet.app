# EDXEIX Session Auto-Extract Helper

This patch adds a fast paste helper to `/ops/edxeix-session.php`.

The helper reduces human error when preparing the EDXEIX session prerequisites. Instead of manually isolating the Cookie header, CSRF token, and submit URL, an authorized operator can paste:

1. The copied EDXEIX request headers from browser Developer Tools.
2. The copied EDXEIX form HTML/snippet containing the `<form ... action="...">` and hidden `_token` input.

Then the operator presses **Extract into fields**. The page fills the normal save fields automatically.

## What it extracts

- `Cookie:` request header value
- EDXEIX form `action` URL
- hidden `_token` CSRF value

## What it still requires

The operator must still type the confirmation phrase:

```text
SAVE EDXEIX SESSION SERVER SIDE
```

and press **Save Server-Side Values**.

## Safety behavior

The helper:

- does not call EDXEIX
- does not call Bolt
- does not submit anything live
- does not enable live submission
- does not enable HTTP submission
- does not print saved cookie or CSRF values back to the screen
- validates the EDXEIX host server-side
- rejects placeholder/example values server-side
- creates backups before overwriting server-only files

The raw pasted helper boxes are submitted only when the operator presses Save. The saved server-side files remain:

```text
/home/cabnet/gov.cabnet.app_config/live_submit.php
/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

## Current production-prep status

At the time of this patch, EDXEIX session and submit URL prerequisites have already been saved and verified. The helper is added to make future session refreshes faster and less error-prone.

Live EDXEIX HTTP transport remains intentionally blocked until the final approved transport patch.
