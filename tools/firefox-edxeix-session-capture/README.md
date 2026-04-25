# CABnet EDXEIX Session Capture Firefox Extension

Private Firefox WebExtension for authorized CABnet operators.

## Purpose

Capture the current EDXEIX lease-agreement create form session prerequisites with fewer manual steps:

- hidden `_token` CSRF value
- EDXEIX cookies via the Firefox cookies API
- fixed submit URL: `https://edxeix.yme.gov.gr/dashboard/lease-agreement`

The extension posts these values to:

`https://gov.cabnet.app/ops/edxeix-session-capture.php`

The server saves them into server-only runtime/config files and keeps live submission disabled.

## Install for testing

1. Open Firefox.
2. Go to `about:debugging`.
3. Click **This Firefox**.
4. Click **Load Temporary Add-on...**.
5. Select `manifest.json` from this folder.

If the files are updated, click **Reload** on the temporary extension.

## Operator workflow

1. Log in to EDXEIX.
2. Open `https://edxeix.yme.gov.gr/dashboard/lease-agreement/create`.
3. Click the CABnet EDXEIX Capture extension.
4. Click **Capture from EDXEIX tab**.
5. Click **Save to gov.cabnet.app**.
6. Click **Open EDXEIX Session page** to verify.
7. Optionally click **Open Live Submit Gate**.

## Safety

- No EDXEIX submission is performed.
- No Bolt request is performed.
- The extension does not display cookie or CSRF values.
- Server-side live flags remain disabled.
- Final live HTTP transport remains blocked unless separately approved and patched.

## Version

0.1.2
