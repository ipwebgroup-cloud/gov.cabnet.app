# Patch: Add Clear Saved EDXEIX Session Button

## What changed

Updated `/ops/edxeix-session.php` with a fast **Clear Saved EDXEIX Session** action.

The action clears only the saved server-side EDXEIX Cookie/CSRF runtime session. It does not log out of EDXEIX, does not remove the configured submit URL, and does not enable live submission.

## Files included

```text
public_html/gov.cabnet.app/ops/edxeix-session.php
docs/EDXEIX_SESSION_CLEAR_BUTTON.md
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

Expected before clearing:

```text
Session cookie/CSRF ready: yes
Submit URL configured: yes
```

After pressing **Clear Saved EDXEIX Session** and accepting the browser confirmation prompt:

```text
Session cookie/CSRF ready: no
Submit URL configured: yes
Live flag: disabled
HTTP flag: disabled
```

Then use the Firefox extension to refresh the saved session again.

## Safety

No Bolt request, EDXEIX request, database write, live HTTP transport, secret output, or live submission behavior is introduced.
