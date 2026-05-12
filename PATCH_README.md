# gov.cabnet.app Patch — Phase 56 Mobile Submit Scenarios

## What changed

Adds a new read-only page:

```text
/ops/mobile-submit-scenarios.php
```

This page generates TEST-ONLY synthetic Bolt pre-ride emails and runs them through the parser, mapping resolver, preflight gate, disabled connector preview, and payload validator.

## Files included

```text
public_html/gov.cabnet.app/ops/mobile-submit-scenarios.php
docs/OPS_UI_SHELL_PHASE56_MOBILE_SUBMIT_SCENARIOS_2026_05_12.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/mobile-submit-scenarios.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-scenarios.php
```

## SQL to run

None.

## Verification command

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-scenarios.php
```

Expected:

```text
No syntax errors detected
```

## Verification URL

```text
https://gov.cabnet.app/ops/mobile-submit-scenarios.php
```

Expected:

- login required
- shared ops shell loads
- scenarios can be generated and run
- WHITEBLUE scenario should resolve to lessor 1756, driver 4382, vehicle 4327, starting point 612164
- no live submit controls exist
- production pre-ride tool remains unchanged

## Production safety

This patch does not call Bolt, EDXEIX, or AADE. It does not write database rows, stage jobs, enable live submission, or modify `/ops/pre-ride-email-tool.php`.

Synthetic scenario emails are TEST ONLY and must never be submitted to EDXEIX.
