# gov.cabnet.app — EDXEIX Create Form Token Diagnostic v3.2.35

## Purpose

v3.2.35 corrects the create-form token diagnostic after live validation showed the saved browser session now reaches the authenticated EDXEIX create form, but v3.2.33 still classified it as not ready because:

- the body text contained generic login/CSRF words even on the authenticated form;
- the first `<form>` in the page was the logout form;
- EDXEIX uses `starting_point_id` instead of `starting_point`;
- EDXEIX uses `lessee[name]` / `lessee[type]` instead of flat `lessee`.

## Safety

This patch is read-only.

It does not perform EDXEIX POST.
It does not call AADE/myDATA.
It does not create queue jobs.
It does not write normalized_bookings.
It does not write live_submit.php.
It does not change production V0.
It does not print cookies, raw CSRF, raw form token, or raw HTML.

## Expected result after saving browser session

Run:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_create_form_token_diagnostic.php --json
```

Expected when session is valid:

```text
classification.code: EDXEIX_CREATE_FORM_TOKEN_READY
diagnostic.token_present: true
diagnostic.token_matches_session_csrf: true
diagnostic.form_summary.form_present: true
diagnostic.form_summary.selected_form_reason: highest_expected_field_score
diagnostic.form_summary.required_expected_fields_missing: []
```

The page may still contain generic login/CSRF text in navigation or page scripts; v3.2.35 no longer treats those text signals as blockers when the authenticated create form and token are present.

## Next step

Keep server POST disabled until a later patch explicitly maps the candidate payload to the exact EDXEIX form field names and confirms a fresh form token is used at the moment of POST.
