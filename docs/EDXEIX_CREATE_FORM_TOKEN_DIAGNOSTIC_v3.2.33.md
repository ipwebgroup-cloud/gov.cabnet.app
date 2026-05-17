# gov.cabnet.app — EDXEIX Create Form Token Diagnostic v3.2.33

## Purpose

v3.2.33 is a read-only diagnostic patch after the supervised v3.2.30 server POST returned HTTP 419 / session expired.

It fetches the EDXEIX **create form** page:

```text
/dashboard/lease-agreement/create
```

instead of only checking the list/submit endpoint:

```text
/dashboard/lease-agreement
```

The goal is to determine whether the server-side saved EDXEIX session can load the real form and expose a fresh hidden `_token` suitable for a future, explicitly approved transport integration patch.

## Safety contract

This patch does not:

- POST to EDXEIX.
- Call AADE/myDATA.
- Create queue jobs.
- Write normalized bookings.
- Write live config.
- Install cron.
- Change V0 production.
- Print raw cookies, CSRF tokens, form token values, or raw HTML response bodies.

## Added tools

CLI:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

Ops page:

```text
https://gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php
```

Existing transport trace page is also updated to use the improved create-form diagnostic:

```text
https://gov.cabnet.app/ops/pre-ride-one-shot-transport-trace.php
```

## Expected classifications

Ready:

```text
EDXEIX_CREATE_FORM_TOKEN_READY
```

Not ready:

```text
EDXEIX_CREATE_FORM_TOKEN_NOT_READY
```

## Useful diagnostic fields

- `create_url`
- `final_url`
- `status`
- `redirect_count`
- `token_present`
- `token_hash_16`
- `session_csrf_hash_16`
- `token_matches_session_csrf`
- `form_summary.form_present`
- `form_summary.form_method`
- `form_summary.form_action_safe`
- `form_summary.required_expected_fields_missing`
- `steps[].status`
- `steps[].location`
- `steps[].body_fingerprint.signals`

## Interpretation

If the diagnostic returns a login/session/redirect page or no token, the saved server-side EDXEIX session cannot yet be used for automatic POST.

If it returns a valid create form with `_token`, the next safe patch can integrate that freshly fetched form token into the supervised one-shot POST flow.

## Production V0

V0 laptop production is untouched by this patch.
