# gov.cabnet.app — Phase 47 Safe Handoff Builder Permission Hotfix

## Purpose

Fixes the CLI Safe Handoff Package Builder when it is run as the `cabnet` user and encounters unreadable runtime artifact directories, for example:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/edxeix
```

## What changed

Updated:

```text
gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
```

The builder now:

- uses a defensive recursive directory scanner instead of `RecursiveDirectoryIterator` for project packaging;
- skips unreadable directories instead of failing the whole build;
- excludes runtime/private generated paths such as `storage/artifacts`, `storage/logs`, `storage/tmp`, `var`, and `handoff-packages`;
- continues to generate sanitized config examples instead of copying real config values.

## Upload path

```text
gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
→ /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
```

## SQL

None.

## Verification

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php

su -s /bin/bash cabnet -c 'php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json'

su -s /bin/bash cabnet -c 'php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest'
```

Expected:

```text
No syntax errors detected
ok=true from the builder JSON
Status: OK from the validator
```

## Safety

This patch does not call Bolt, EDXEIX, or AADE. It does not enable live submission. It does not modify the production pre-ride tool.
