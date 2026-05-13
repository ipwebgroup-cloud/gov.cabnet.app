# gov.cabnet.app — V3 Submit Preflight Dry-Run Patch

## Files included

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
docs/PRE_RIDE_EMAIL_TOOL_V3_SUBMIT_PREFLIGHT.md
PATCH_README.md
```

## Upload path

```text
gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
→ /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
```

Docs stay in the local GitHub repo.

## SQL

None.

## Verify

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php
php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_submit_preflight.php --limit=20
```

## Safety

- SELECT only.
- No DB writes.
- No EDXEIX calls.
- No AADE calls.
- No production queue access.
- Production pre-ride-email-tool.php is not included and not touched.
