# EDXEIX Target URL Probe Matrix v2.9

Adds `/ops/edxeix-target-matrix.php`.

## Purpose

Probe all known EDXEIX target URL candidates using GET-only requests and classify them as:
- lease form candidate
- EDXEIX form candidate
- dashboard
- login/session page
- public shell
- unconfirmed

## Safety

Default page load is local metadata only.

`probe=1&follow=1` performs GET-only requests and follows redirects.

No POST, no Bolt call, no job staging, no mapping update, no DB/file write, no raw HTML, no cookie/token values, and no live submission.
