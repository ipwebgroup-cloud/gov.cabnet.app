# gov.cabnet.app V3 helper starting-point retry patch

## Upload / replace locally

Replace these files in the local GitHub Desktop repo and reload the temporary Firefox extension:

```text
tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js
tools/firefox-edxeix-autofill-helper-v3/manifest.json
```

## Server files

None.

## Production safety

This patch does not include or modify:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## What changed

The V3 helper now retries the EDXEIX starting-point dropdown more aggressively and can detect it by multiple field names or by the Greek label `Σημείο έναρξης`.

It remains fill-only and does not submit to EDXEIX.
