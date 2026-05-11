# gov.cabnet.app — Ops UI Shell Phase 14 Operator Guides

## Upload paths

Upload:

- `public_html/gov.cabnet.app/ops/_shell.php`
- `public_html/gov.cabnet.app/ops/workflow-guide.php`
- `public_html/gov.cabnet.app/ops/safety-checklist.php`

To:

- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/workflow-guide.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/safety-checklist.php`

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/workflow-guide.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/safety-checklist.php
```

## URLs

- `https://gov.cabnet.app/ops/workflow-guide.php`
- `https://gov.cabnet.app/ops/safety-checklist.php`

## Notes

No SQL is required. The production pre-ride email tool is not modified.
