# V3 Automation Next Steps

Current safe state after v3.0.69:

- V3 intake/proof path is proven.
- Operator approval workflow is proven.
- Package export is proven.
- Pre-live switchboard is proven.
- Kill-switch check is proven.
- Adapter row simulation is proven.
- Adapter payload consistency harness is added.
- Future real adapter skeleton remains non-live-capable.
- Master gate remains closed.
- Live submit remains disabled.
- V0 remains untouched.

## Next recommended step

`v3.0.70-v3-historical-proof-index`

Create a read-only index of historical proof rows and their associated artifacts/events so future expired rows remain useful as evidence without needing manual SQL inspection.

Alternative next step:

`v3.0.70-v3-real-adapter-nonlive-client-scaffold`

Prepare a non-live HTTP client skeleton that cannot send requests and only builds request metadata/hashes. Keep `isLiveCapable() = false` and `submitted = false`.

## Do not do yet

Do not enable live submit yet. Do not change live-submit config to `enabled=true`, `mode=live`, or `adapter=edxeix_live`. Do not change `hard_enable_live_submit` to true.
