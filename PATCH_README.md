# Patch README — Pre-Ride Email Tool V3 Isolated

## What this patch does

Adds a new isolated V3 pre-ride email tool without touching the existing production tool.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
gov.cabnet.app_app/src/BoltMailV3/BoltPreRideEmailParserV3.php
gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php
gov.cabnet.app_app/src/BoltMailV3/EdxeixMappingLookupV3.php
tools/firefox-edxeix-autofill-helper-v3/manifest.json
tools/firefox-edxeix-autofill-helper-v3/gov-capture-v3.js
tools/firefox-edxeix-autofill-helper-v3/edxeix-fill-v3.js
docs/PRE_RIDE_EMAIL_TOOL_V3_ISOLATED.md
PATCH_README.md
```

## Files deliberately not included / not touched

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
gov.cabnet.app_app/src/BoltMail/BoltPreRideEmailParser.php
gov.cabnet.app_app/src/BoltMail/MaildirPreRideEmailLoader.php
gov.cabnet.app_app/src/BoltMail/EdxeixMappingLookup.php
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php

gov.cabnet.app_app/src/BoltMailV3/BoltPreRideEmailParserV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/BoltPreRideEmailParserV3.php

gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php

gov.cabnet.app_app/src/BoltMailV3/EdxeixMappingLookupV3.php
→ /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/EdxeixMappingLookupV3.php
```

Optional local Firefox helper only:

```text
tools/firefox-edxeix-autofill-helper-v3/
```

## SQL

No SQL required.

## Verification commands

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/BoltPreRideEmailParserV3.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php
php -l /home/cabnet/gov.cabnet.app_app/src/BoltMailV3/EdxeixMappingLookupV3.php
```

## Verification URLs

```text
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php?manual=1
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php?watch=1
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php?format=json
```

## Expected result

The original production page continues working unchanged at:

```text
https://gov.cabnet.app/ops/pre-ride-email-tool.php
```

The new V3 test page works independently at:

```text
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

