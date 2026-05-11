# gov.cabnet.app Ops UI Shell Phase 2 Patch — 2026-05-11

## Upload paths

Upload these files:

```text
public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
public_html/gov.cabnet.app/ops/_shell.php
public_html/gov.cabnet.app/ops/home.php
public_html/gov.cabnet.app/ops/pre-ride-email-toolv2.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/assets/css/gov-ops-shell.css
/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
/home/cabnet/public_html/gov.cabnet.app/ops/home.php
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv2.php
```

## Important production note

This patch does not include or modify:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Syntax checks

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/home.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv2.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/home.php
https://gov.cabnet.app/ops/pre-ride-email-toolv2.php
https://gov.cabnet.app/ops/profile.php
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

Expected result:

- Ops Home uses the shared GUI shell and user/profile section.
- Pre-Ride Tool V2 opens as a safe wrapper.
- Production Pre-Ride Tool remains unchanged and usable.
- Login protection remains active.
