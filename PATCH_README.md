# gov.cabnet.app patch — V3 helper fill capture

## Purpose

Adds a V3-only callback endpoint and updates the isolated V3 Firefox helper so fill-only browser progress can be recorded in V3 queue events.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php
tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js
tools/firefox-edxeix-autofill-helper-v3/manifest.json
docs/PRE_RIDE_EMAIL_TOOL_V3_HELPER_FILL_CAPTURE.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php
```

Local Firefox helper files:

```text
tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js
tools/firefox-edxeix-autofill-helper-v3/manifest.json
→ replace in local repo/tools/firefox-edxeix-autofill-helper-v3/ and reload the temporary Firefox extension
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php
```

Local JS check from repo root:

```bash
node --check tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js
```

## Safety

- Production `/ops/pre-ride-email-tool.php` is not included and not changed.
- No EDXEIX submit.
- No AADE call.
- No production queue writes.
- Callback writes only V3 queue events after matching queue ID + dedupe key.
