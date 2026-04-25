# EDXEIX Placeholder Session Detection

This patch prevents copied example/template EDXEIX session values from being counted as production-ready.

## Why

The server-side runtime file can exist and contain valid JSON while still being only a template. Example values such as `PASTE_EDXEIX_COOKIE_HEADER_HERE_SERVER_ONLY_DO_NOT_COMMIT` must not satisfy the live-submit session gate.

## What changed

- `/ops/edxeix-session.php` now detects placeholder/example values in `cookie_header` and `csrf_token`.
- `gov.cabnet.app_app/lib/edxeix_live_submit_gate.php` now applies the same placeholder detection when the live-submit gate checks session readiness.
- The page reports placeholder state without printing secrets.

## Placeholder patterns blocked

The detection rejects obvious template or dummy markers including:

```text
PASTE
REPLACE
EXAMPLE
DUMMY
DEMO
TODO
YOUR_
_HERE
SERVER_ONLY
DO_NOT_COMMIT
COOKIE_HEADER
CSRF_TOKEN
PLACEHOLDER
YYYY-MM-DD
```

It also treats very short values and cookie values without an equals sign as suspicious.

## Expected current state after copying the template

```text
Session file exists: yes
Session file readable: yes
JSON valid: yes
Cookie header present: yes
CSRF token present: yes
Placeholder/example values: detected
Session cookie/CSRF ready: no
```

This is correct until the real EDXEIX browser session values are manually entered server-side.

## Safety

The patch remains read-only and does not call EDXEIX, call Bolt, write to the database, create queue jobs, or enable live submission.
