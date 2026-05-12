# gov.cabnet.app Patch — Phase 41 Safe Handoff Package Builder

## What changed

Adds an admin-only Safe Handoff ZIP builder to:

```text
/ops/handoff-center.php
```

Backed by:

```text
gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
```

The utility downloads a private ZIP containing:

```text
public_html/gov.cabnet.app/...
gov.cabnet.app_app/...
gov.cabnet.app_sql/...
docs/...
tools/firefox*/... when present
DATABASE_EXPORT.sql
gov.cabnet.app_config_examples/... sanitized placeholders
PACKAGE_MANIFEST.md
```

## Safety

The builder excludes real config values and obvious logs/sessions/cache/mail/temp/backups/archive files.

The database export may contain operational/customer data. Treat the downloaded ZIP as private operational material. Do not commit `DATABASE_EXPORT.sql` unless intentionally sanitized.

No Bolt, EDXEIX, or AADE calls are made.

## Upload paths

```text
gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
→ /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php

public_html/gov.cabnet.app/ops/handoff-center.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
```

## SQL to run

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
```

Expected:

```text
No syntax errors detected
```

## Verification URL

```text
https://gov.cabnet.app/ops/handoff-center.php
```

Expected:

- Login required.
- Admin user sees Build / Download Safe Handoff ZIP.
- Download starts when clicked.
- ZIP contains sanitized config placeholders, not real config values.
- Production pre-ride tool remains unchanged.
