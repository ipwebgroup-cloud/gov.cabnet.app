# V3 Automation Next Steps

Current checkpoint: `v3.0.72-v3-proof-bundle-runner-and-ops-hotfix`

## Proven so far

- Forwarded Gmail pre-ride emails can enter the V3 queue.
- Future-safe rows can progress to `live_submit_ready`.
- Expired/past rows are safely blocked by the V3 expiry guard.
- Operator-verified starting-point options are enforced.
- Closed-gate operator approval workflow exists and expires safely.
- Future EDXEIX adapter skeleton exists and remains non-live-capable.
- Adapter row simulation confirms `submitted=false`.
- Payload consistency harness confirms DB-built payload, package artifact, and adapter hash match.
- Pre-live proof bundle exporter collects the proof state into local private artifacts.

## Next safest phase

`v3.0.73-v3-proof-bundle-dashboard-polish`

Recommended next work:

1. Verify the v3.0.72 CLI and Ops page on the live server.
2. Confirm the proof bundle writes a safe local artifact and reports bundle-safe when the runner is healthy.
3. Polish the Ops proof bundle page so the latest bundle summary is easier to read.
4. Keep live submit disabled until Andreas explicitly requests a live-submit update.

## Still blocked intentionally

- Master live-submit gate remains disabled.
- Adapter mode remains disabled unless explicitly changed later.
- Hard enable remains false.
- Real EDXEIX live adapter implementation is not enabled.
- No automatic live submission is allowed yet.
