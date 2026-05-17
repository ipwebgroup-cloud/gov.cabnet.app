# gov.cabnet.app — v3.2.34 Browser Create-Form Proof Patch

## Summary

Adds a no-secret browser create-form proof workflow after v3.2.33 proved the server-side session cannot load the authenticated EDXEIX create form.

## Safety

- No EDXEIX POST.
- No AADE/myDATA call.
- No queue job.
- No normalized booking write.
- No live config write.
- No V0 production change.
- No raw cookie, raw CSRF, raw token, raw HTML, or field values are accepted or printed.

## Files

```text
docs/EDXEIX_BROWSER_CREATE_FORM_PROOF_v3.2.34.md
docs/EDXEIX_BROWSER_CREATE_FORM_PROOF_SNIPPET_v3.2.34.js
gov.cabnet.app_app/lib/edxeix_browser_form_proof_lib.php
gov.cabnet.app_app/cli/edxeix_browser_form_proof_validate.php
public_html/gov.cabnet.app/ops/edxeix-browser-create-form-proof.php
public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php
CONTINUE_PROMPT.md
HANDOFF.md
README.md
SCOPE.md
PROJECT_FILE_MANIFEST.md
PATCH_README.md
```

## Upload paths

```text
/home/cabnet/docs/EDXEIX_BROWSER_CREATE_FORM_PROOF_v3.2.34.md
/home/cabnet/docs/EDXEIX_BROWSER_CREATE_FORM_PROOF_SNIPPET_v3.2.34.js
/home/cabnet/gov.cabnet.app_app/lib/edxeix_browser_form_proof_lib.php
/home/cabnet/gov.cabnet.app_app/cli/edxeix_browser_form_proof_validate.php
/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-browser-create-form-proof.php
/home/cabnet/public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php
/home/cabnet/CONTINUE_PROMPT.md
/home/cabnet/HANDOFF.md
/home/cabnet/README.md
/home/cabnet/SCOPE.md
/home/cabnet/PROJECT_FILE_MANIFEST.md
/home/cabnet/PATCH_README.md
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/lib/edxeix_browser_form_proof_lib.php
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_browser_form_proof_validate.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-browser-create-form-proof.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-create-form-token-diagnostic.php
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-browser-create-form-proof.php
```

## Git commit title

```text
Add EDXEIX browser create-form proof diagnostic
```

## Git commit description

```text
Adds v3.2.34 no-secret browser create-form proof tooling after v3.2.33 showed the server-side session redirects away from the authenticated EDXEIX create form. The browser proof validates form/token presence and field names from the logged-in browser without sending cookies, raw CSRF tokens, raw form tokens, raw HTML, or form values.

No EDXEIX POST, AADE call, queue job, normalized booking write, live config write, cron, or V0 production change.
```
