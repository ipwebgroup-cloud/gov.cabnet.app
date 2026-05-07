# v4.7 Production Hardening / Launch Control Panel Patch

## What changed

Adds a read-only launch control panel for the Bolt mail bridge production hardening phase.

New file:

```text
public_html/gov.cabnet.app/ops/launch-readiness.php
```

Documentation:

```text
docs/BOLT_PRODUCTION_HARDENING_V4_7.md
HANDOFF.md
CONTINUE_PROMPT.md
```

## Upload path

```text
public_html/gov.cabnet.app/ops/launch-readiness.php
→ /home/cabnet/public_html/gov.cabnet.app/ops/launch-readiness.php
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/launch-readiness.php
```

Open:

```text
https://gov.cabnet.app/ops/launch-readiness.php?key=INTERNAL_API_KEY
```

JSON:

```text
https://gov.cabnet.app/ops/launch-readiness.php?key=INTERNAL_API_KEY&format=json
```

## Safety

This patch is read-only. It does not import mail, send driver emails, create bookings, create evidence, create jobs/attempts, call Bolt, call EDXEIX, or submit live.
