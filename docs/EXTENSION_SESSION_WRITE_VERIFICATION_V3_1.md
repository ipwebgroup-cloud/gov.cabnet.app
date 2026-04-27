# Extension Session Write Verification v3.1

Adds `/ops/extension-session-write-verification.php`.

## Purpose

Verify whether the Firefox extension actually updated `edxeix_session.json` recently.

## What it shows safely

- server time
- session file modified time
- age in seconds/minutes/hours/days
- freshness status
- safe metadata such as source_url, detected_form_action, fixed_submit_url_used, extension_version
- JSON key names only
- cookie/token-like presence as YES/NO only

## Safety

The page does not:
- call Bolt
- call EDXEIX
- POST to EDXEIX
- read/write database
- write files
- stage jobs
- update mappings
- print cookies
- print token values
- print raw session JSON
- enable live submission
