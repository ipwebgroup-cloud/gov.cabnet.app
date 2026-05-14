# V3 Automation Next Steps

Current state after v3.0.60:

- V3 readiness path is proven.
- Payload audit is proven.
- Local package export is proven.
- Operator approval workflow is proven.
- Closed-gate rehearsal is proven.
- Future adapter skeleton is installed.
- Adapter contract probe is proven.
- Live adapter kill-switch check is installed.
- Live submit remains disabled.
- V0 remains untouched.

## Next recommended phase

`v3.0.61-v3-real-adapter-design-notes`

Before writing any adapter code that could eventually talk to EDXEIX, document the exact implementation shape:

1. How the adapter will authenticate without exposing credentials.
2. Which existing EDXEIX helper/session mechanism it may reuse.
3. How it will submit only after all gates pass.
4. What success/failure evidence it records.
5. How it prevents duplicates.
6. How it rolls back to disabled immediately.

## Required live-submit conditions

Live submit must not open unless all of these are true:

- Real eligible future Bolt trip, not synthetic proof row.
- Row is currently `live_submit_ready`.
- Pickup remains sufficiently in the future.
- Row is not expired, blocked, cancelled, terminal, invalid, or historical.
- Driver, vehicle, lessor, and starting point are verified.
- Payload audit passes.
- Local package export passes.
- Operator approval is valid and unexpired.
- Adapter contract probe passes.
- Kill-switch check passes.
- Master config explicitly enables live mode.
- Adapter is explicitly set to `edxeix_live`.
- Hard enable is true.
- Required acknowledgement phrase is present.
- Andreas explicitly approves a live-submit update.

## Emergency stop

Keep config disabled as the default:

```php
'enabled' => false,
'mode' => 'disabled',
'adapter' => 'disabled',
'hard_enable_live_submit' => false,
```
