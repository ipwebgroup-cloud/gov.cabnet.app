# V3 Automation Next Steps

Current checkpoint target: `v3.0.71-v3-pre-live-proof-bundle-export`

## Current state

- V3 intake is proven.
- V3 queue and live readiness are proven.
- Operator approval is proven.
- Package export is proven.
- Pre-live switchboard is installed.
- Kill-switch check is installed.
- Adapter skeleton exists but is non-live-capable.
- Adapter row simulation is proven safe.
- Payload consistency harness is proven.
- Live submit remains disabled.

## Next safest steps

1. Run the pre-live proof bundle exporter.
2. Commit the proof bundle exporter patch.
3. Document the proof output in a checkpoint package.
4. Only after documentation, plan the first real adapter implementation phase.

## Live submit remains blocked

Do not enable live submit until Andreas explicitly approves a live-submit update and all gates pass for a real eligible future trip.
