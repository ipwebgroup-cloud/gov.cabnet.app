# HANDOFF — gov.cabnet.app Bolt → EDXEIX Bridge

Current patch: v3.2.35 — EDXEIX create-form token diagnostic classification fix.

## Current state

- Production V0 laptop/manual EDXEIX workflow remains operational and untouched.
- Candidate 4 was manually submitted through V0/laptop and archived as `manual_submitted_v0`.
- Server-side retry for candidate 4 is blocked by closure/retry-prevention.
- v3.2.33 proved the saved server session can now reach `/dashboard/lease-agreement/create` and sees a matching hidden token, but classified it as not ready due to false-positive text signals and wrong form selection.
- v3.2.35 fixes diagnostic form selection and expected field aliases.

## Next safest step

Validate:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

Expected with valid saved session:

```text
EDXEIX_CREATE_FORM_TOKEN_READY
```

Do not perform another server-side EDXEIX POST until a later patch maps the exact form field names and only for a new future candidate.
