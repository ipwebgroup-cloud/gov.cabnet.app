# HANDOFF — gov.cabnet.app V3 Automation

Current checkpoint: v3.0.61-v3-kill-switch-table-exists-fix

## Verified context

The V3 readiness and closed-gate approval path has been proven:

- Forwarded/server mailbox intake works.
- V3 parser, mapping, future guard, starting-point guard, dry-run readiness, and live-readiness path reached `live_submit_ready`.
- Payload audit passed.
- Local live package export wrote artifacts.
- Operator approval workflow inserted a valid closed-gate approval for row 418.
- Final rehearsal for row 418 was blocked only by master-gate controls.
- Closed-gate diagnostics confirmed selected row approval was valid.
- Future real adapter skeleton exists and adapter contract probe confirms it remains non-live-capable.

## Latest fix

v3.0.60 kill-switch checker failed on the live server with:

```text
SQL syntax error near '?'
```

Cause: `SHOW TABLES LIKE ?` was not accepted reliably by live MariaDB prepared statements.

v3.0.61 replaces that table existence check with a prepared `INFORMATION_SCHEMA.TABLES` query.

## Safety state

- V0 laptop/manual helper untouched.
- Live EDXEIX submit disabled.
- No AADE changes.
- No cron changes.
- No SQL schema changes.
- No production submission table writes.

## Next

Verify v3.0.61, then continue toward V3 automation with the live adapter kill-switch as the formal pre-live switchboard. The expected result is still `OK: no` because the live-submit master gate is intentionally closed.
