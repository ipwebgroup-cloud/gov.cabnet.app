# gov.cabnet.app patch — v6.6.16 minimal pre-ride staff UI

## What changed

Adds a minimal staff UI layer to `/ops/pre-ride-email-tool.php`.

The page now presents only the current operational buttons prominently:

1. **Load latest email + check IDs**
2. **Save + open EDXEIX**
3. **Manual fallback**

The existing parser, DB lookup, and helper workflow remain untouched.

## Files included

```text
public_html/gov.cabnet.app/ops/assets/pre-ride-minimal-ui.css
public_html/gov.cabnet.app/ops/assets/pre-ride-minimal-ui.js
gov.cabnet.app_app/cli/install_pre_ride_minimal_ui.php
docs/PRE_RIDE_MINIMAL_STAFF_UI.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/assets/pre-ride-minimal-ui.css
→ /home/cabnet/public_html/gov.cabnet.app/ops/assets/pre-ride-minimal-ui.css

public_html/gov.cabnet.app/ops/assets/pre-ride-minimal-ui.js
→ /home/cabnet/public_html/gov.cabnet.app/ops/assets/pre-ride-minimal-ui.js

gov.cabnet.app_app/cli/install_pre_ride_minimal_ui.php
→ /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_minimal_ui.php
```

Docs go to the local repository root only unless desired on the server.

## Activate

```bash
php -l /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_minimal_ui.php
php /home/cabnet/gov.cabnet.app_app/cli/install_pre_ride_minimal_ui.php
```

## Verify

```bash
grep -n "pre-ride-minimal-ui" /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php?v=6616
```

Expected result: a large **Pre-Ride EDXEIX Assistant** panel appears above the existing tool with only the simplified workflow buttons.

## Rollback

The installer creates a backup like:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php.bak_minimal_ui_YYYYMMDD_HHMMSS
```

To roll back, copy that backup over the live file.

## Git commit title

```text
Add minimal staff UI for pre-ride EDXEIX workflow
```

## Git commit description

```text
Adds a UI-only minimal staff interface for the Bolt pre-ride email to EDXEIX workflow.

The patch adds CSS/JS assets and an installer that injects them into the existing pre-ride email tool. The simplified panel exposes only the current office workflow: load latest email and check IDs, save/open EDXEIX, and manual fallback.

No parser, DB lookup, EDXEIX submission, AADE, queue, or helper safety logic is changed.
```
