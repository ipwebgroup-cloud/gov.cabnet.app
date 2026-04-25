# CABnet EDXEIX Session Capture Firefox Extension

Private helper for authorized CABnet operators.

## What changed in v0.1.1

- The EDXEIX submit URL is fixed automatically:
  `https://edxeix.yme.gov.gr/dashboard/lease-agreement`
- The safety confirmation phrase was removed from the extension workflow.
- The operator now only clicks:
  1. Capture from EDXEIX tab
  2. Save to gov.cabnet.app

## Operator workflow

1. Log in to EDXEIX.
2. Open `https://edxeix.yme.gov.gr/dashboard/lease-agreement/create` by clicking `+ Ανάρτηση σύμβασης`.
3. Click the Firefox extension icon.
4. Click **1. Capture from EDXEIX tab**.
5. Confirm that CSRF token and cookies are detected by length/count only.
6. Click **2. Save to gov.cabnet.app**.
7. Verify `https://gov.cabnet.app/ops/edxeix-session.php`.

## Safety

- The extension does not submit any EDXEIX form.
- The extension does not enable live EDXEIX submission.
- Cookie/CSRF values are not displayed in the popup.
- The server endpoint forces `live_submit_enabled = false` and `http_submit_enabled = false`.
- The server endpoint uses the fixed submit URL and validates it server-side.
