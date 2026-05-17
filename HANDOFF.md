# gov.cabnet.app — HANDOFF v3.2.34

## Current state

The pre-ride candidate pipeline works through parsing, mapping, capture, readiness, rehearsal, one-shot transport trace, closure, and retry prevention.

Candidate 4 was a real ride. The server-side one-shot transport trace attempted one POST and EDXEIX returned HTTP 419 / session expired. Andreas then submitted that real ride manually via V0/laptop. Candidate 4 is now archived as `MANUALLY_SUBMITTED_VIA_V0` and server retry is blocked.

v3.2.33 proved that server-side GET to the authenticated EDXEIX create form redirects to the public/root page and cannot see the hidden form token.

v3.2.34 adds a safe browser create-form proof path. It verifies what the logged-in browser can see without exposing secrets.

## Safety posture

- V0 production remains untouched.
- No unattended EDXEIX submission.
- No EDXEIX POST in v3.2.34.
- No AADE/myDATA call.
- No queue job.
- No normalized booking write.
- No live config write.
- No raw cookies, raw CSRF, raw token, raw HTML, or field values are accepted or printed.

## Next safest step

Run the browser proof from the logged-in EDXEIX create form and paste the sanitized JSON into:

```text
https://gov.cabnet.app/ops/edxeix-browser-create-form-proof.php
```

If it returns `BROWSER_CREATE_FORM_PROOF_READY`, the next safe development step is a browser-assisted fill/submit design that still defaults to manual/operator control.
