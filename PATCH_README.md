# gov.cabnet.app — Phase 29 Mobile Submit Dev Patch

## What changed

Adds `/ops/mobile-submit-dev.php`, a mobile-first dev route for the future server-side EDXEIX submit workflow.

## Files included

```text
public_html/gov.cabnet.app/ops/mobile-submit-dev.php
docs/OPS_UI_SHELL_PHASE29_MOBILE_SUBMIT_DEV_2026_05_12.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/mobile-submit-dev.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-dev.php
```

## SQL to run

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-dev.php
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-dev.php
```

## Expected result

- Login required.
- Page opens inside the shared ops shell when `_shell.php` is available.
- Operator can paste or load latest Bolt pre-ride email.
- Parsed ride details and EDXEIX mapping IDs appear.
- Future/past/too-soon safety appears.
- Submit gate remains disabled with the connector-disabled blocker.
- Production pre-ride email tool remains unchanged.

## Safety

This patch does not call Bolt, EDXEIX, or AADE. It does not write workflow data, stage jobs, or enable live submission.
