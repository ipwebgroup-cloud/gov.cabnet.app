# EDXEIX Submit Readiness Probe v2.6

Adds `/ops/edxeix-submit-readiness.php`.

## Purpose

Verify local EDXEIX submission preparation without submitting:

- core EDXEIX config presence
- saved session/cookie file metadata presence
- payload builder availability
- recent normalized booking payload preview build
- eligibility/blocker status

## Safety

No Bolt call. No EDXEIX POST. No job staging. No mapping update. No database write. Live submission remains disabled.
