# EDXEIX Session / Submit URL Readiness

Adds `/ops/edxeix-session.php`, a guarded read-only helper for preparing the final live EDXEIX submission phase.

## Purpose

This page checks whether the server has the pieces needed for a future live-submit patch:

- server-only `/home/cabnet/gov.cabnet.app_config/live_submit.php`
- configured EDXEIX submit/action URL
- runtime EDXEIX session file
- cookie header presence
- CSRF token presence
- session timestamp/age metadata

## Safety

The page is read-only.

It does not:

- call Bolt
- call EDXEIX
- write to the database
- create jobs
- submit forms
- print cookies
- print CSRF tokens
- expose secrets

It only shows whether sensitive values are present and uses length-only indicators for cookie/CSRF fields.

## URL

```text
https://gov.cabnet.app/ops/edxeix-session.php
https://gov.cabnet.app/ops/edxeix-session.php?format=json
```

## Expected current state

Until the EDXEIX session and submit URL are configured, the page should show:

```text
Session cookie/CSRF ready: no
Submit URL configured: no
```

After server-side values are added, it should show:

```text
Session cookie/CSRF ready: yes
Submit URL configured: yes
```

Live HTTP transport still remains blocked by `/ops/live-submit.php` until a separate explicit final transport patch is approved.
