# v4.4.1 Raw Preflight Guard Alignment Patch

## What changed

Updates only the legacy/raw preflight JSON endpoint so the displayed future guard comes from the canonical server config file:

`/home/cabnet/gov.cabnet.app_config/config.php`

This fixes the raw endpoint showing `guard_minutes = 30` while the active mail-intake dashboards correctly show `FUTURE GUARD 2 MIN`.

## Files included

```text
public_html/gov.cabnet.app/bolt_edxeix_preflight.php
docs/BOLT_PREFLIGHT_GUARD_ALIGNMENT_V4_4_1.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload path

```text
public_html/gov.cabnet.app/bolt_edxeix_preflight.php
→ /home/cabnet/public_html/gov.cabnet.app/bolt_edxeix_preflight.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/bolt_edxeix_preflight.php
```

Then open:

```text
https://gov.cabnet.app/bolt_edxeix_preflight.php?limit=30
```

Expected raw JSON:

```json
"guard_minutes": 2
```

Rows should also show:

```json
"future_guard_minutes": 2
```

## Safety

This patch is read-only.

It does not:

- create `submission_jobs`
- create `submission_attempts`
- POST to EDXEIX
- call Bolt
- call EDXEIX
- enable live submit
