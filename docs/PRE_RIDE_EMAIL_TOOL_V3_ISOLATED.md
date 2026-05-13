# gov.cabnet.app — Pre-Ride Email Tool V3 Isolated

## Purpose

This package adds a separate V3 route for automation testing without changing the production tool.

Production route remains:

```text
/ops/pre-ride-email-tool.php
```

New isolated V3 route:

```text
/ops/pre-ride-email-toolv3.php
```

## Isolation rules

- Do not modify `/ops/pre-ride-email-tool.php`.
- Do not modify production `Bridge\BoltMail` parser/loader/lookup classes.
- V3 uses `Bridge\BoltMailV3` classes only.
- V3 performs no DB writes.
- V3 performs no server-side EDXEIX call.
- V3 performs no AADE call.
- V3 creates no queue jobs or submission attempts.

## V3 files

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
gov.cabnet.app_app/src/BoltMailV3/BoltPreRideEmailParserV3.php
gov.cabnet.app_app/src/BoltMailV3/MaildirPreRideEmailLoaderV3.php
gov.cabnet.app_app/src/BoltMailV3/EdxeixMappingLookupV3.php
tools/firefox-edxeix-autofill-helper-v3/
```

## Modes

```text
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php?manual=1
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php?watch=1
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php?format=json
```

## Optional V3 Firefox helper

The helper under `tools/firefox-edxeix-autofill-helper-v3/` is independent from the production helper.

It uses separate browser storage keys and a separate extension ID:

```text
gov-cabnet-edxeix-autofill-helper-v3@cabnet.app
```

It is fill-only. It has no POST / Save reviewed form button.

## Safety gate

V3 only enables the helper payload when:

1. Parser required fields are present.
2. Exact EDXEIX IDs are resolved from read-only DB lookup.
3. Pickup time is at least 20 minutes in the future.

