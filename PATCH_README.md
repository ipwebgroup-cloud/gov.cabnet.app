# gov.cabnet.app patch — Phase 32 EDXEIX Submit Dry-Run Builder

## Files included

```text
public_html/gov.cabnet.app/ops/edxeix-submit-dry-run.php
docs/OPS_UI_SHELL_PHASE32_EDXEIX_DRY_RUN_2026_05_12.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/edxeix-submit-dry-run.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-dry-run.php
```

## SQL

None. This page reads `ops_edxeix_submit_captures` if Phase 31 SQL has been installed.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/edxeix-submit-dry-run.php
```

## Production safety

This patch does not modify `/ops/pre-ride-email-tool.php` and does not enable live EDXEIX submission.
