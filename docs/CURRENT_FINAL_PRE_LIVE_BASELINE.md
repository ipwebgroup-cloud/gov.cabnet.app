# gov.cabnet.app — Current Final Pre-Live Baseline

_Last refreshed: 2026-04-25_

This document records the current safe pre-live state of the Bolt → EDXEIX bridge.

## Summary

The app is prepared for the first real future Bolt test, but it cannot submit to EDXEIX yet.

```text
EDXEIX session prerequisites: ready
EDXEIX submit URL: configured
Firefox extension capture: working
Manual Cookie/CSRF form: removed
Clear saved session button: installed
Real future Bolt candidate: waiting
Live HTTP transport: intentionally blocked
Live EDXEIX submission: not enabled
```

## Confirmed EDXEIX session state

From `/ops/edxeix-session.php?format=json` and SSH verification:

```text
cookie_raw_present: true
csrf_raw_present: true
cookie_present: true
csrf_present: true
cookie_placeholder_detected: false
csrf_placeholder_detected: false
placeholder_detected: false
cookie_length: 578
csrf_length: 40
source: firefox_extension_capture_fixed_url_no_phrase
ready: true
```

The page never prints secret values.

## Confirmed submit URL

```text
https://edxeix.yme.gov.gr/dashboard/lease-agreement
```

The EDXEIX `/create` page displays the form. The fixed `/dashboard/lease-agreement` URL is the POST submit endpoint.

## Firefox extension workflow

The normal operator session refresh method is the private Firefox extension in:

```text
tools/firefox-edxeix-session-capture/
```

Workflow:

```text
1. Log in to EDXEIX.
2. Open https://edxeix.yme.gov.gr/dashboard/lease-agreement/create.
3. Click the CABnet EDXEIX Capture extension.
4. Click Capture from EDXEIX tab.
5. Click Save to gov.cabnet.app.
6. Verify /ops/edxeix-session.php and /ops/live-submit.php.
```

The extension captures:

```text
hidden _token CSRF value
EDXEIX cookies via Firefox cookies API
fixed submit URL
```

It posts to:

```text
https://gov.cabnet.app/ops/edxeix-session-capture.php
```

The endpoint never prints secrets, never calls EDXEIX, never calls Bolt, and never enables live submission.

## EDXEIX Session page state

URL:

```text
https://gov.cabnet.app/ops/edxeix-session.php
```

The page is now mostly diagnostic/read-only for operators.

Manual fields removed:

```text
manual Cookie header field
manual CSRF token field
manual submit URL field
manual confirmation phrase
manual save button
paste/extract helper
```

Remaining operator action:

```text
Clear Saved EDXEIX Session
```

The clear button uses a browser confirmation prompt and clears only the saved server-side Cookie/CSRF runtime session. It keeps the submit URL configured and keeps live flags disabled.

## Live Submit Gate state

URL:

```text
https://gov.cabnet.app/ops/live-submit.php
```

Expected current state:

```text
EDXEIX URL configured: yes
EDXEIX session ready: yes
Real future candidates: 0
Live-eligible rows: 0
Live HTTP execution: no
```

Expected blockers:

```text
live_submit_config_disabled
http_submit_config_disabled
no_real_future_candidate
no_selected_real_future_candidate
http_transport_not_enabled_in_this_patch
```

## What remains before the first real live submission

The first real test requires Filippos and a real future Bolt ride.

Checklist:

```text
1. Refresh EDXEIX session with Firefox extension.
2. Create/schedule one real future Bolt ride using Filippos and a mapped vehicle.
3. Ride must be sufficiently in the future, ideally 40–60 minutes.
4. Run Future Test Checklist.
5. Run Preflight JSON.
6. Confirm candidate is real Bolt, future, mapped, non-terminal, non-LAB/test, non-duplicate.
7. Only after Andreas explicitly approves, prepare final live HTTP transport patch.
```

## Non-negotiable blockers

The app must never live-submit:

```text
LAB/local/test bookings
historical bookings
finished bookings
cancelled bookings
terminal-status bookings
past bookings
expired bookings
invalid/unmapped bookings
duplicate already-successful payloads
```

## Current production posture

Safe for:

```text
operator review
readiness checks
session refresh
mapping review/editor
future test checklist
preflight preview
local queue visibility
```

Not yet enabled for:

```text
live EDXEIX HTTP POST
automatic EDXEIX submission
background submission worker live transport
```
