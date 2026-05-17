# EDXEIX Browser Create-Form Proof — v3.2.34

## Purpose

v3.2.33 proved that the server cannot currently load the authenticated EDXEIX create form with the saved session file. The server GET to `/dashboard/lease-agreement/create` redirects to the public/root page and does not expose the lease-agreement form or hidden `_token`.

v3.2.34 adds a safe browser-side proof workflow:

1. Open the real EDXEIX create form in the already logged-in browser.
2. Run the provided JavaScript snippet in the browser console.
3. The snippet copies sanitized JSON proof to clipboard.
4. Paste that JSON into `/ops/edxeix-browser-create-form-proof.php`.
5. The server validates field names and token hash presence without receiving raw cookies, raw token values, raw HTML, or customer field values.

## Safety

This patch performs:

- No EDXEIX POST.
- No EDXEIX server-side HTTP request from the proof validator.
- No AADE/myDATA call.
- No queue job.
- No `normalized_bookings` write.
- No `live_submit.php` config write.
- No V0 production change.
- No raw cookie, CSRF, token, HTML body, or field values are stored or printed.

## New files

```text
gov.cabnet.app_app/lib/edxeix_browser_form_proof_lib.php
gov.cabnet.app_app/cli/edxeix_browser_form_proof_validate.php
public_html/gov.cabnet.app/ops/edxeix-browser-create-form-proof.php
docs/EDXEIX_BROWSER_CREATE_FORM_PROOF_SNIPPET_v3.2.34.js
docs/EDXEIX_BROWSER_CREATE_FORM_PROOF_v3.2.34.md
```

## Web URL

```text
https://gov.cabnet.app/ops/edxeix-browser-create-form-proof.php
```

## Browser steps

Open this page in the logged-in EDXEIX browser:

```text
https://edxeix.yme.gov.gr/dashboard/lease-agreement/create
```

Open the browser console, paste the snippet from:

```text
/home/cabnet/docs/EDXEIX_BROWSER_CREATE_FORM_PROOF_SNIPPET_v3.2.34.js
```

Then paste the copied sanitized JSON into:

```text
https://gov.cabnet.app/ops/edxeix-browser-create-form-proof.php
```

## CLI validation

Save the sanitized JSON as a temporary local/server file if needed, then run:

```bash
php /home/cabnet/gov.cabnet.app_app/cli/edxeix_browser_form_proof_validate.php --file=/path/to/proof.json --json
```

Or pipe it:

```bash
cat /path/to/proof.json | php /home/cabnet/gov.cabnet.app_app/cli/edxeix_browser_form_proof_validate.php --json
```

## Expected result

Ready proof:

```text
BROWSER_CREATE_FORM_PROOF_READY
```

Blocked proof examples:

```text
browser_create_form_not_present
browser_form_token_not_present
proof_not_from_edxeix_yme_gov_gr
expected_fields_missing:...
```

## Why this matters

The server-side HTTP session path currently fails at EDXEIX session/form-token validation. This browser proof verifies what the logged-in browser can actually see, without exposing secrets. The next safe design decision is whether to continue attempting server-side session integration or move the final one-shot action into a browser-assisted helper running inside the logged-in EDXEIX session.
