# gov.cabnet.app — Phase 34 Company Mapping Control

## What changed

Adds a read-only GUI page:

```text
public_html/gov.cabnet.app/ops/company-mapping-control.php
```

The page provides company/lessor mapping governance and flags missing or incorrect lessor-specific starting point overrides.

## Files included

```text
public_html/gov.cabnet.app/ops/company-mapping-control.php
docs/OPS_UI_SHELL_PHASE34_COMPANY_MAPPING_CONTROL_2026_05_12.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/company-mapping-control.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/company-mapping-control.php
```

## SQL to run

None.

## Verification command

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/company-mapping-control.php
```

Expected:

```text
No syntax errors detected
```

## Verification URLs

```text
https://gov.cabnet.app/ops/company-mapping-control.php
https://gov.cabnet.app/ops/company-mapping-control.php?lessor=1756
```

## Expected result

- Login required.
- Page opens inside the shared ops shell if `_shell.php` exists.
- Company/lessor mapping health is displayed.
- WHITEBLUE / 1756 is checked against the verified starting point `612164`.
- Missing lessor-specific starting point overrides are flagged.
- Production pre-ride tool remains unchanged.

## Production safety

This patch does not modify:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

It does not call Bolt, EDXEIX, or AADE, does not write DB rows, does not stage jobs, and does not enable live submission.
