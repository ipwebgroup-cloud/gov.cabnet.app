# gov.cabnet.app v4.8 Credential Rotation + Final Dry-run Handoff Patch

## What changed

Adds a credential-rotation status page and no-secret acknowledgement CLI marker. Updates launch readiness to display the credential rotation gate.

## Files included

```text
public_html/gov.cabnet.app/ops/credential-rotation.php
public_html/gov.cabnet.app/ops/launch-readiness.php
gov.cabnet.app_app/cli/mark_credential_rotation.php
docs/BOLT_CREDENTIAL_ROTATION_V4_8.md
HANDOFF.md
CONTINUE_PROMPT.md
PATCH_README.md
```

## Upload paths

```text
public_html/gov.cabnet.app/ops/credential-rotation.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/credential-rotation.php

public_html/gov.cabnet.app/ops/launch-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/launch-readiness.php

gov.cabnet.app_app/cli/mark_credential_rotation.php
→ /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php
```

Docs are for repo/package continuity.

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/credential-rotation.php
php -l /home/cabnet/public_html/gov.cabnet.app/ops/launch-readiness.php
php -l /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php
```

Open:

```text
https://gov.cabnet.app/ops/credential-rotation.php?key=INTERNAL_API_KEY
https://gov.cabnet.app/ops/launch-readiness.php?key=INTERNAL_API_KEY
```

After manual credential rotation:

```bash
/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php --ops-key --bolt --edxeix --mailbox --by=Andreas
```

## Safety

This patch does not enable live EDXEIX submission. The new ops page is read-only. The CLI creates only a no-secret acknowledgement marker.
