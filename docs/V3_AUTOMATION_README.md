# V3 Automation README

## Current phase

V3 is in a closed-gate automation proof phase.

Validated so far:

- Maildir intake can parse Bolt-style pre-ride emails.
- Queue rows can advance to `live_submit_ready` when parser, mapping, future guard, starting-point guard, dry-run readiness, and live-readiness checks pass.
- Operator approval can be recorded for closed-gate rehearsal only.
- Local live package artifacts can be exported.
- Adapter simulation remains non-live.
- Payload consistency can compare DB payload and artifact payload hashes.
- Pre-live proof bundles can be exported.
- Live gate drift guard confirms the gate is still disabled.

## Live submission posture

Live EDXEIX submission is **not enabled**.

The EDXEIX adapter remains a skeleton and must not become live-capable without explicit approval.

Expected gate posture:

```text
enabled=false
mode=disabled
adapter=disabled
hard_enable_live_submit=false
```

## New operator console

This patch adds:

```text
public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

The console is a read-only visibility layer for operators.

It shows the active queue state and whether a selected row is ready for closed-gate proof, while still confirming that live submission is blocked.

## Manual verification

After upload, run:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
curl -I https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php
```

Expected `curl -I` behavior on the protected Ops area:

```text
HTTP/1.1 302 Found
Location: /ops/login.php?next=...
```

After login, open:

```text
https://gov.cabnet.app/ops/pre-ride-email-v3-live-operator-console.php?queue_id=716
```

Expected page posture:

```text
EXPECTED CLOSED PRE-LIVE GATE
NO LIVE RISK DETECTED
NO EDXEIX CALL
NO DB WRITES
```
