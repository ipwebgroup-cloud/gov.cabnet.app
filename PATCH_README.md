# gov.cabnet.app — V3 Manual Save Capture Patch

## Upload paths

Server:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php
```

Local Firefox helper:

```text
tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js
tools/firefox-edxeix-autofill-helper-v3/manifest.json
→ replace in local repo/tools/firefox-edxeix-autofill-helper-v3/
```

Then reload the V3 temporary Firefox extension.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-helper-callback.php
```

Local optional check:

```bash
node --check tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js
```

Node is not required on the server.

## Safety

No EDXEIX submit is added. No AADE call. No production submission tables are touched. Production pre-ride-email-tool.php is untouched.
