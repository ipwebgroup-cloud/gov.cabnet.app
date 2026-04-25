# EDXEIX Session Clear Button

This patch adds a fast **Clear Saved EDXEIX Session** action to `/ops/edxeix-session.php`.

## Purpose

The saved EDXEIX Cookie/CSRF session is stored server-side in:

```text
/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json
```

Operators can now clear that saved runtime session from the readiness page without using SSH or File Manager.

## Safety behavior

The clear action:

- backs up the current session file before clearing it,
- replaces the session file with an empty/non-ready session state,
- keeps the EDXEIX submit URL configured,
- keeps `live_submit_enabled = false`,
- keeps `http_submit_enabled = false`,
- never calls Bolt,
- never calls EDXEIX,
- never writes to the database,
- never prints Cookie or CSRF values.

The button uses a browser confirmation prompt for speed instead of a typed confirmation phrase.

## Expected state after clearing

```text
Session cookie/CSRF ready: no
Submit URL configured: yes
Live flag: disabled
HTTP flag: disabled
```

To refresh the session again, use the CABnet EDXEIX Capture Firefox extension from the logged-in EDXEIX `/dashboard/lease-agreement/create` page.

## Extra UX improvement

If a saved EDXEIX session is older than 180 minutes, the page now shows a refresh recommendation warning.
