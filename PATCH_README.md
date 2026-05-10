# gov.cabnet.app v6.6.3 Patch — EDXEIX Autofill Helper for Pre-Ride Email Tool

## What changed

Adds an ASAP browser-side autofill helper to the existing Bolt Pre-Ride Email Tool.

After an operator pastes and parses the Bolt pre-ride email, the page now creates an **EDXEIX autofill script**. The operator copies that script, opens the logged-in EDXEIX rental contract form, pastes the script into the browser Console, and the script attempts to fill/select the visible form controls.

This is intentionally a manual operator helper, not live API submission.

## Safety

This patch remains manual and guarded:

- No DB access.
- No DB writes.
- No network calls from gov.cabnet.app.
- No Bolt API calls.
- No EDXEIX API calls.
- No AADE calls.
- No queue jobs.
- No submission attempts.
- No email body storage.
- The helper does **not** press save/submit.
- The operator must verify every populated EDXEIX field before saving/submitting inside EDXEIX.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
docs/BOLT_PRE_RIDE_EMAIL_UTILITY.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

Upload this file to production:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

Docs and handoff files go into the local GitHub Desktop repo root/docs:

```text
docs/BOLT_PRE_RIDE_EMAIL_UTILITY.md
→ local GitHub repo docs/BOLT_PRE_RIDE_EMAIL_UTILITY.md

HANDOFF.md
→ local GitHub repo HANDOFF.md

CONTINUE_PROMPT.md
→ local GitHub repo CONTINUE_PROMPT.md

PATCH_README.md
→ local GitHub repo PATCH_README.md
```

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

Expected result:

- Page opens normally.
- Pasted pre-ride email parses into the editable form.
- Section **4. EDXEIX autofill helper** appears after a successful parse.
- **Copy EDXEIX autofill script** copies a JavaScript snippet.
- When pasted into the EDXEIX page Console, it attempts to select/fill visible matching controls and displays a completion alert.
- It does not save or submit the EDXEIX form.

## Operator workflow

1. Open `https://gov.cabnet.app/ops/pre-ride-email-tool.php`.
2. Paste the full real Bolt pre-ride email.
3. Press **Parse email**.
4. Review and correct the editable operator form.
5. Click **Copy EDXEIX autofill script**.
6. Open the EDXEIX rental contract form tab.
7. Press **F12**, open **Console**, paste the script, press **Enter**.
8. Verify every field on EDXEIX.
9. Manually save/submit only after human verification.

## Git commit title

```text
Add EDXEIX autofill helper to pre-ride email tool
```

## Git commit description

```text
Adds a browser-side EDXEIX autofill helper to the manual Bolt pre-ride email utility.

After parsing a pre-ride email, the ops page now generates a copyable console script that can be run inside the logged-in EDXEIX page to populate/select matching visible fields. The helper remains manual and guarded: it performs no server writes, creates no jobs or attempts, makes no EDXEIX/AADE calls from gov.cabnet.app, and does not save or submit the government form.
```
