# gov.cabnet.app patch — Ops UI Shell Phase 13 Mobile Review

## What changed

Adds a mobile-friendly read-only Bolt pre-ride email review page:

- `/ops/pre-ride-mobile-review.php`

Updates the shared shell navigation and CSS so staff can reach Mobile Review from the uniform `/ops` GUI.

## Production safety

This patch does **not** modify:

- `/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php`

This patch does **not** call Bolt, EDXEIX, AADE, stage jobs, write workflow data, or enable live EDXEIX submission.

Mobile review is for checking only. EDXEIX form fill/save remains desktop/laptop Firefox only.

## Files included

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/pre-ride-mobile-review.php
docs/OPS_UI_SHELL_PHASE13_MOBILE_REVIEW_2026_05_11.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
→ /home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css

public_html/gov.cabnet.app/ops/_shell.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

public_html/gov.cabnet.app/ops/pre-ride-mobile-review.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-mobile-review.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-mobile-review.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-mobile-review.php
```

## Expected result

- login required
- mobile review page opens in the shared shell
- staff can parse pasted email or load latest server email
- parsed fields and EDXEIX IDs are shown read-only
- page warns that actual EDXEIX save remains desktop/laptop only
- production pre-ride email tool remains unchanged
