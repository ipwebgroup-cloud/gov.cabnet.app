# gov.cabnet.app — V3 Queue Helper Handoff Patch

## Upload paths

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue.php
```

Local Firefox helper file:

```text
tools/firefox-edxeix-autofill-helper-v3/manifest.json
→ reload this helper locally in Firefox from the repo/tools folder
```

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-queue.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-queue.php
```

Select a queue row. If it is still future-safe, use:

```text
Save selected row to V3 helper
Save + open EDXEIX company form
```

## Safety

No production `pre-ride-email-tool.php` change. No DB writes from dashboard. No EDXEIX submit. No AADE.
