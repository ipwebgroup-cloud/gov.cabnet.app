# EDXEIX Firefox Extension Verification Buttons

This patch updates the private CABnet EDXEIX Session Capture Firefox extension to make post-save verification explicit and reliable.

## Change

The previous popup contained a normal link to the session page. In some Firefox popup contexts the operator may not notice any visible action after clicking it.

Version `0.1.2` replaces that with explicit extension-powered buttons:

- `Open EDXEIX Session page`
- `Open Live Submit Gate`

These use the Firefox tabs API to open normal tabs:

- `https://gov.cabnet.app/ops/edxeix-session.php`
- `https://gov.cabnet.app/ops/live-submit.php`

## Safety

The verification buttons only open read-only/diagnostic pages. They do not submit to EDXEIX, call Bolt, enable live flags, or write values.

The extension still captures only when the operator clicks the button and still saves only to the guarded server endpoint.

## Expected workflow

1. Open the EDXEIX `/dashboard/lease-agreement/create` page.
2. Click the extension.
3. Click `Capture from EDXEIX tab`.
4. Click `Save to gov.cabnet.app`.
5. Click `Open EDXEIX Session page`.
6. Verify session and submit URL show ready.
7. Click `Open Live Submit Gate`.
8. Verify live HTTP execution remains blocked.
