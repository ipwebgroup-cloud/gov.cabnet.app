# gov.cabnet.app Patch — Phase 57 Mobile Submit Trial Run

## Upload

Upload:

- `public_html/gov.cabnet.app/ops/mobile-submit-trial-run.php`

To:

- `/home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-trial-run.php`

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-trial-run.php
```

Open:

```text
https://gov.cabnet.app/ops/mobile-submit-trial-run.php
```

Expected:

- Login required.
- Shared ops shell loads.
- Latest/pasted email can be evaluated.
- Final dry-run result displays.
- No live submit controls exist.
- Production pre-ride tool remains unchanged.
