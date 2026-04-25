# gov.cabnet.app — Bolt → EDXEIX Bridge Handoff

## Current baseline

The project is in safe production-prep state.

- EDXEIX submit URL is configured server-side.
- EDXEIX Cookie/CSRF session can be refreshed using the private Firefox extension.
- `/ops/edxeix-session.php` is a diagnostic/readiness page and no longer shows manual Cookie/CSRF input fields.
- `/ops/edxeix-session.php` now includes **Clear Saved EDXEIX Session**, protected by a browser confirmation prompt.
- Clearing the saved session only clears the server-side runtime Cookie/CSRF file; it does not log out of EDXEIX and does not remove the submit URL.
- Live submission remains blocked.
- HTTP transport remains intentionally unimplemented/blocked in the current preparatory patch.
- A real future Bolt ride with Filippos and a mapped vehicle is still required before the final live-submit transport patch can be considered.

## Important safety posture

Do not enable live EDXEIX submission unless Andreas explicitly asks for the final approved live-submit update.

Historical, finished, cancelled, terminal, LAB/test, invalid, or past Bolt rows must never be submitted to EDXEIX.

Real secrets/config/session files remain server-only and must not be committed.

## Key pages

```text
https://gov.cabnet.app/ops/
https://gov.cabnet.app/ops/readiness.php
https://gov.cabnet.app/ops/future-test.php
https://gov.cabnet.app/ops/mappings.php
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/live-submit.php
```

## Firefox extension

Local extension source:

```text
tools/firefox-edxeix-session-capture/
```

Normal refresh workflow:

1. Log in to EDXEIX.
2. Open `https://edxeix.yme.gov.gr/dashboard/lease-agreement/create`.
3. Click the CABnet EDXEIX Capture Firefox extension.
4. Click **Capture from EDXEIX tab**.
5. Click **Save to gov.cabnet.app**.
6. Confirm `/ops/edxeix-session.php` shows `Session cookie/CSRF ready: yes`.

## Latest patch

Added **Clear Saved EDXEIX Session** to `/ops/edxeix-session.php` and a warning when the saved session age is over 180 minutes.
