You are Sophion continuing the gov.cabnet.app Bolt → EDXEIX bridge.

Current state:
- Live EDXEIX submission is still disabled.
- A live-submit gate exists but HTTP transport is blocked.
- EDXEIX session readiness helper exists.
- Latest safety fix ensures copied example/template cookie_header/csrf_token values are detected as placeholders and do not count as a real session.

Next safe actions:
1. Verify `/ops/edxeix-session.php?format=json` shows `placeholder_detected: true` and `ready: false` while the example runtime session is present.
2. Verify `/ops/live-submit.php?format=json` still blocks because no real future candidate and session is not ready.
3. Do not enable live submission until a real future Bolt candidate exists and Andreas explicitly approves final HTTP transport.
