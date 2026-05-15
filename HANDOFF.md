# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge

## Current patch

v3.1.13 restores `opsui_badge()` in the shared ops shell so `/ops/handoff-center.php` fully renders again.

## Verified issue

The Handoff Center rendered only its intro section because `handoff-center.php` calls `opsui_badge()`, while the current `_shell.php` no longer defined that helper.

## Current safe posture

- Production Pre-Ride Tool untouched.
- V0 workflow untouched.
- Live EDXEIX submit disabled.
- V3 live gate closed.
- No SQL changes.
- No DB writes.
- No queue mutations.
- No Bolt, EDXEIX, or AADE calls.

## Next step

Upload `_shell.php`, verify the Handoff Center renders package buttons again, then commit.
