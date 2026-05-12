# gov.cabnet.app Patch — Phase 62 Mobile Submit QA Dashboard

## What changed

Added a new read-only `/ops/mobile-submit-qa-dashboard.php` route that summarizes the future mobile/server-side EDXEIX submit readiness state.

It checks:

1. Mapping readiness.
2. WHITEBLUE / lessor `1756` starting point override readiness for EDXEIX starting point `612164`.
3. Sanitized EDXEIX submit capture readiness.
4. Dry-run support readiness.
5. Mobile submit evidence readiness.
6. Live-submit blocked state.

The production pre-ride tool is not modified.

## Files included

```text
public_html/gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
docs/MOBILE_SUBMIT_QA_DASHBOARD.md
PATCH_README.md
```

## Exact upload paths

```text
public_html/gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-qa-dashboard.php

docs/MOBILE_SUBMIT_QA_DASHBOARD.md
→ /home/cabnet/public_html/gov.cabnet.app/docs/MOBILE_SUBMIT_QA_DASHBOARD.md
```

If your local GitHub Desktop repo keeps `docs/` only at repository root, keep the docs file at repo root `docs/MOBILE_SUBMIT_QA_DASHBOARD.md`; it does not need to be uploaded to public webroot unless you want it available on-server.

## SQL to run

No new SQL is included in this patch.

Confirm whether Phase 59 SQL has already been applied:

```bash
mysql -u cabnet_gov -p cabnet_gov -e "SHOW TABLES LIKE 'mobile_submit_evidence_log';"
```

Run this only if the table is missing:

```bash
mysql -u cabnet_gov -p cabnet_gov < /home/cabnet/gov.cabnet.app_sql/2026_05_12_mobile_submit_evidence_log.sql
```

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
```

## Verification URL

```text
https://gov.cabnet.app/ops/mobile-submit-qa-dashboard.php
```

## Expected result

- Page loads behind `/ops/login.php`.
- The page is read-only.
- No Bolt, EDXEIX, AADE, or external API calls occur.
- No database writes occur.
- Production tool `/ops/pre-ride-email-tool.php` remains untouched.
- Gate matrix clearly shows what is ready and what still needs review.
- Live server-side EDXEIX submit remains blocked.

## Git commit title

```text
Add mobile submit QA dashboard
```

## Git commit description

```text
Adds a read-only Phase 62 mobile submit QA dashboard for the future server-side EDXEIX submit workflow. The dashboard summarizes mapping readiness, WHITEBLUE starting point override status, submit capture readiness, dry-run support, evidence log readiness, and the live-submit blocked state. No production pre-ride workflow changes, no external calls, and no live submit behavior are introduced.
```
