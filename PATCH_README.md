# v3.0.38 — Ops Shell Unify V3 Dashboard

## Purpose

Polish the V3 Pre-Ride Automation dashboard so it matches the existing gov.cabnet.app Ops Home visual language.

This is a UI-only, read-only patch.

## What changed

- Restyled `/ops/pre-ride-email-v3-dashboard.php` to match the established Ops shell shown on `/ops/home.php`.
- Updated `_ops-nav.php` with the canonical top navigation and deep-blue sidebar structure.
- Added `docs/OPS_UI_STYLE_NOTES.md`.
- Updated `docs/OPS_SITEMAP_V3.md` with the v3.0.38 UI coherence direction.

## Files included

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
docs/OPS_UI_STYLE_NOTES.md
docs/OPS_SITEMAP_V3.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/_ops-nav.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php

public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php

docs/OPS_UI_STYLE_NOTES.md
→ repo root: docs/OPS_UI_STYLE_NOTES.md

docs/OPS_SITEMAP_V3.md
→ repo root: docs/OPS_SITEMAP_V3.md
```

The docs files are intended for the local GitHub Desktop repo unless you intentionally want them uploaded to the server.

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_ops-nav.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php

php -d display_errors=1 /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php > /tmp/v3_dashboard_render_test.html
head -n 5 /tmp/v3_dashboard_render_test.html
```

Unauthenticated curl should still redirect to login:

```bash
curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
```

Expected unauthenticated result:

```text
HTTP/1.1 302 Found
Location: /ops/login.php?next=%2Fops%2Fpre-ride-email-v3-dashboard.php
```

## Verification URL

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-dashboard.php
```

## Expected result

After login, the V3 dashboard should visually match the existing Ops Home shell:

```text
white top bar
deep-blue left sidebar
light gray content background
white cards
blue headings
simple tab row
consistent green/amber/red safety badges
```

Live-submit posture should remain closed:

```text
Live submit disabled
Gate closed
Adapter disabled
OK for future live submit: no
```

## Risk notes

This patch does not change:

```text
SQL schema
cron behavior
V3 intake
V3 queue mutation
starting-point guard logic
expiry guard logic
live readiness logic
live-submit gate logic
EDXEIX adapter behavior
```

## Git commit title

```text
Unify V3 dashboard with Ops shell
```

## Git commit description

```text
Restyles the V3 Pre-Ride Automation Control Center to match the established gov.cabnet.app Ops Home shell and palette.

Adds canonical Ops UI style notes and updates the V3 sitemap with the UI coherence direction.

This is a read-only UI patch only. No SQL, cron, queue mutation, mapping, live-submit gate, EDXEIX adapter, or submission behavior is changed.
```
