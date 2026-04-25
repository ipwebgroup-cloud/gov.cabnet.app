# Patch: Fix EDXEIX placeholder session detection

## Upload paths

- `public_html/gov.cabnet.app/ops/edxeix-session.php` -> `/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-session.php`
- `gov.cabnet.app_app/lib/edxeix_live_submit_gate.php` -> `/home/cabnet/gov.cabnet.app_app/lib/edxeix_live_submit_gate.php`

## SQL

No SQL required.

## Verify

Open:

- `https://gov.cabnet.app/ops/edxeix-session.php`
- `https://gov.cabnet.app/ops/edxeix-session.php?format=json`
- `https://gov.cabnet.app/ops/live-submit.php?format=json`

Expected while template values are present:

- `placeholder_detected: true`
- `ready: false`
- live-submit still reports the EDXEIX session as not ready

## Safety

No Bolt call, no EDXEIX call, no DB write, no queue creation, no live submission.
