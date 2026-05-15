# Patch README — V3 Git-Safe + DB Audit package option

## What changed

Updated `/ops/handoff-center.php` to add a second button inside the Git-Safe Continuity ZIP section:

- `Build Git-Safe Continuity ZIP` — DB-free, commit-review starting point.
- `Build Git-Safe + DB Audit ZIP` — includes `DATABASE_EXPORT.sql` for private live-site/database audit while keeping the runtime/session/proof-artifact scrubber active.

## Files included

- `public_html/gov.cabnet.app/ops/handoff-center.php`
- `docs/V3_GIT_SAFE_DB_AUDIT_OPTION_20260515.md`
- `PATCH_README.md`
- `HANDOFF.md`
- `CONTINUE_PROMPT.md`

## Upload path

Upload only:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
```

## SQL

None.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php

curl -I --max-time 10 https://gov.cabnet.app/ops/handoff-center.php

grep -n "v3.0.78\|build_git_safe_continuity_zip_with_db\|GIT_SAFE_WITH_DB_AUDIT_NOTICE\|Git-Safe + DB Audit ZIP" \
  /home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php
```

Expected:

- PHP syntax passes.
- Unauthenticated request redirects to `/ops/login.php`.
- Grep finds the new v3.0.78 markers and DB audit action.

## Package verification after generating DB audit ZIP

```bash
unzip -l /path/to/gov_cabnet_git_safe_with_db_audit_*.zip | grep -Ei "DATABASE_EXPORT|GIT_SAFE_WITH_DB_AUDIT_NOTICE|storage/runtime|edxeix_session|cookie_header|csrf|xsrf|laravel_session|storage/artifacts|\.bak|\.pre_"
```

Expected:

- `DATABASE_EXPORT.sql` is present.
- `GIT_SAFE_WITH_DB_AUDIT_NOTICE.md` is present.
- No runtime/session/cookie/proof-artifact/backup entries are present.

## Commit title

Add Git-safe DB audit package option

## Commit description

Adds a DB-audit package option to the Handoff Center Git-Safe section.

The new button builds a package with `DATABASE_EXPORT.sql` included for private live-site/database audit while preserving the runtime/session/cookie/proof-artifact scrubber introduced in v3.0.77.

The DB-free Git-Safe Continuity ZIP remains available for local repo continuity review before committing. The DB audit package is private operational material and must not be committed to GitHub.

No SQL changes are required. Live EDXEIX submission remains disabled.
