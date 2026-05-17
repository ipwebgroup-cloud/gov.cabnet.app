# HANDOFF — gov.cabnet.app Bolt → EDXEIX bridge v3.2.33

Current state:
- V0 laptop/manual production remains the operational fallback and is untouched.
- Candidate 4 was marked `manual_submitted_v0` and archived in v3.2.32.
- Server-side retry prevention is active.
- v3.2.30 proved server POST reached EDXEIX but received HTTP 419/session expired.
- v3.2.33 adds a read-only create-form token diagnostic for `/dashboard/lease-agreement/create`.

Next safe step:
Run:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

Then inspect whether the diagnostic reaches the create form, finds `_token`, and sees required field names.

Do not retry POST until a later patch integrates a fresh form token and Andreas explicitly approves a new one-candidate live trace.
