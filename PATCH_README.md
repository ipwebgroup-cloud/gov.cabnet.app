# gov.cabnet.app — V3 Diagnostic Preview Payload Patch

## What changed

Updates the isolated V3 page so blocked rides show a diagnostic payload preview while keeping the active helper payload disabled.

## Files included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
docs/PRE_RIDE_EMAIL_TOOL_V3_PREVIEW_PAYLOAD.md
PATCH_README.md
```

## Production file not included

```text
public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

Docs remain in the local GitHub Desktop repo unless you want to upload them separately.

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

Open:

```text
https://gov.cabnet.app/ops/pre-ride-email-toolv3.php
```

## Expected result

Blocked rides show:

```text
PREVIEW ONLY — NOT SAVED TO HELPER
```

and the active helper payload remains empty.

## Git commit title

```text
Add V3 diagnostic payload preview
```

## Git commit description

```text
Adds a preview-only diagnostic JSON section to the isolated V3 pre-ride email tool. Blocked rides now expose the exact parsed/mapped payload for troubleshooting while keeping the active helper payload disabled. The production pre-ride-email-tool.php route and production dependencies are not changed.
```
