# EDXEIX Session / Form GET Probe v2.7

Adds `/ops/edxeix-session-probe.php`.

## Purpose

Verify the saved EDXEIX session/form layer before any live submit work.

Default page load is local metadata only. The optional GET probe runs only when `probe=1` is passed or the operator clicks the button.

## Safety

- No Bolt call
- No EDXEIX POST
- Optional EDXEIX GET only
- No job staging
- No mapping update
- No database write
- No file write
- No raw cookies, CSRF values, session secrets, or raw HTML printed
- Live submission remains disabled
