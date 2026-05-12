# Phase 60 — Mobile Submit Evidence Review

## Files included

```text
public_html/gov.cabnet.app/ops/mobile-submit-evidence-review.php
docs/OPS_UI_SHELL_PHASE60_MOBILE_SUBMIT_EVIDENCE_REVIEW_2026_05_12.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/mobile-submit-evidence-review.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-review.php
```

## SQL

None.

Uses the Phase 59 table if present:

```text
mobile_submit_evidence_log
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-evidence-review.php
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-evidence-review.php
```

## Safety

This patch is read-only and does not modify `/ops/pre-ride-email-tool.php`.
