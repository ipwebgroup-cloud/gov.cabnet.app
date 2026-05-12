# Ops UI Shell Phase 47 — Safe Handoff Builder Permission Hotfix

## Summary

The CLI package build succeeded when run as root, but failed when run as the `cabnet` user because the builder attempted to enter an unreadable runtime artifact directory:

```text
/home/cabnet/gov.cabnet.app_app/storage/artifacts/edxeix
```

The package builder should not fail on unreadable runtime artifacts, and those runtime artifacts should not be included in the handoff package by default.

## Updated file

```text
gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
```

## New behavior

- Project directory walking is now defensive and permission-aware.
- Unreadable directories are skipped instead of throwing a fatal error.
- Runtime/private paths are excluded:
  - `storage/artifacts/`
  - `storage/logs/`
  - `storage/tmp/`
  - `storage/temp/`
  - `var/`
  - `handoff-packages/`
- Real server-only config values are still not copied.
- Sanitized config placeholders are still generated.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/src/Support/SafeHandoffPackageBuilder.php
su -s /bin/bash cabnet -c 'php /home/cabnet/gov.cabnet.app_app/cli/build_safe_handoff_package.php --json'
su -s /bin/bash cabnet -c 'php /home/cabnet/gov.cabnet.app_app/cli/validate_safe_handoff_package.php --latest'
```

## Safety statement

No production workflow behavior changes. No Bolt calls. No EDXEIX calls. No AADE calls. No live submission behavior is added. The production pre-ride tool is unchanged.
