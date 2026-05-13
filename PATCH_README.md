# gov.cabnet.app — V3 Live Readiness Page Fix

## What changed

Fixes the V3 Live-Submit Readiness dashboard display query so it reads verified starting-point options using the actual table columns.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php
docs/PRE_RIDE_EMAIL_TOOL_V3_LIVE_READINESS_PAGE_FIX.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-readiness.php
```

Expected:

```text
No Unknown column lessor_id error.
Verified start options shows 2 for lessor 2307.
```

## Safety

- Production pre-ride-email-tool.php untouched.
- No DB writes.
- No EDXEIX calls.
- No AADE calls.
- No production submission_jobs/submission_attempts access.
