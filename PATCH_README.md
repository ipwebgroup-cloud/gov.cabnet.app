# gov.cabnet.app v6.7.1 — Harden EDXEIX Mail Preflight Bridge CLI Arguments

## What changed

Hardens `edxeix_mail_preflight_bridge.php` so malformed or placeholder CLI arguments are rejected.

The script now requires:

- numeric `--intake-id` when supplied;
- exact long options only;
- `--create` only with one explicit numeric `--intake-id`.

This prevents accidental bulk create behavior and catches mistakes such as:

```bash
--intake-id=ID --create--json
```

## Files included

```text
gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php
docs/EDXEIX_MAIL_PREFLIGHT_BRIDGE.md
PATCH_README.md
```

## Upload paths

Upload:

```text
/home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php
```

Local repo docs:

```text
docs/EDXEIX_MAIL_PREFLIGHT_BRIDGE.md
PATCH_README.md
```

## SQL

None.

## Verification commands

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --limit=20 --json

/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/edxeix_mail_preflight_bridge.php --intake-id=ID --create--json || true

mysql cabnet_gov -e "
SELECT COUNT(*) AS submission_jobs FROM submission_jobs;
SELECT COUNT(*) AS submission_attempts FROM submission_attempts;
"
```

## Expected result

Normal preview:

```text
ok: true
version: v6.7.1
queues_unchanged: true
```

Malformed command:

```text
ok: false
invalid_intake_id_must_be_positive_integer
unknown_or_malformed_option:--create--json
```

Queues:

```text
submission_jobs = 0
submission_attempts = 0
```

## Git commit title

```text
Harden EDXEIX mail preflight bridge CLI arguments
```

## Git commit description

```text
Hardens the EDXEIX mail preflight bridge so malformed or placeholder CLI arguments are rejected.

Changes:
- Rejects non-numeric --intake-id values such as ID.
- Rejects malformed long options such as --create--json.
- Requires --create to include one explicit numeric --intake-id.
- Removes accidental bulk create behavior.
- Keeps the script limited to one reviewed intake row per create command.

Safety posture remains unchanged:
- Does not call EDXEIX.
- Does not issue AADE receipts.
- Does not create submission_jobs.
- Does not create submission_attempts.
- Does not expose cookies, CSRF tokens, API keys, or private config values.

No SQL changes and no live-submit activation.
```
