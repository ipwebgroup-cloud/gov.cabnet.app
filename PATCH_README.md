# gov.cabnet.app patch — Ops UI Shell Phase 20 Top Navigation Dropdowns

## Upload path

Upload:

```text
public_html/gov.cabnet.app/ops/_shell.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected:

```text
No syntax errors detected
```

Open:

```text
https://gov.cabnet.app/ops/documentation-center.php
https://gov.cabnet.app/ops/home.php
https://gov.cabnet.app/ops/profile.php
```

Expected:

- top menu remains one line on desktop
- route groups open as dropdown menus on hover/focus
- sidebar remains available
- production pre-ride email tool remains unchanged
