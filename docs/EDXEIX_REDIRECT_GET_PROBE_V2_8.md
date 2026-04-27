# EDXEIX Redirect-Follow GET Probe v2.8

Adds `/ops/edxeix-redirect-probe.php`.

## Purpose

Perform a GET-only EDXEIX route/session probe with optional redirect following.

## Safety

Default page load is local metadata only.

`probe=1` performs GET only.

`probe=1&follow=1` follows redirects but still uses GET only.

No POST, no Bolt call, no job staging, no mapping update, no DB/file write, no raw HTML, no cookie/token values, and no live submission.
