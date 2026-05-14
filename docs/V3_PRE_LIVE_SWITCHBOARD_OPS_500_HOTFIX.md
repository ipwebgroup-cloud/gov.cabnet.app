# V3 Pre-Live Switchboard Ops 500 Hotfix

This patch replaces the V3 pre-live switchboard Ops page with a more defensive read-only renderer.

The CLI was already verified working. The browser route returned HTTP 500, so this hotfix guards the web page against unavailable command runners, non-JSON CLI output, unexpectedly large raw JSON, and runtime exceptions.

Safety boundary:
- No Bolt call.
- No EDXEIX call.
- No AADE call.
- No database writes.
- No queue status changes.
- No production submission table writes.
- No V0 changes.
- No live-submit enablement.
