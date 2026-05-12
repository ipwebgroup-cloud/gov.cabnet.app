# gov.cabnet.app — Phase 30 EDXEIX Submit Research Patch

## Files included

```text
public_html/gov.cabnet.app/ops/edxeix-submit-research.php
docs/OPS_UI_SHELL_PHASE30_EDXEIX_SUBMIT_RESEARCH_2026_05_12.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/edxeix-submit-research.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-research.php
```

Docs are repo-side only unless you also want them uploaded:

```text
docs/OPS_UI_SHELL_PHASE30_EDXEIX_SUBMIT_RESEARCH_2026_05_12.md
```

## SQL to run

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-research.php
```

Open:

```text
https://gov.cabnet.app/ops/edxeix-submit-research.php
```

## Expected result

- Login required.
- Page opens in the shared ops shell.
- Page documents the future server-side EDXEIX submit connector research plan.
- Page detects safe local helper manifests/source signals if available.
- No Bolt, EDXEIX, AADE, database write, queue staging, or live submit occurs.
- `/ops/pre-ride-email-tool.php` remains unchanged.

## Git commit title

```text
Add EDXEIX submit research page
```

## Git commit description

```text
Adds a read-only EDXEIX Submit Research page for the future mobile server-side submit workflow. The page documents the submit connector blueprint, required research facts, helper manifest inventory, safe helper source signals, and route status without calling EDXEIX or enabling live submission.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, secret output, database writes, queue staging, or live submission behavior are added.
```
