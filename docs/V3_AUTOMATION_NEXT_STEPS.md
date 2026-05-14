# V3 Automation Next Steps

Current phase after the forwarded-email proof:

```text
V3 readiness pipeline: proven
Historical proof dashboard: installed
Local live package export: proven
Operator approval visibility: installed
Closed-gate adapter diagnostics: added by v3.0.54
Live auto-submit: still disabled
V0 laptop/manual helper: untouched
```

## Next safe work sequence

1. Verify `v3.0.54` closed-gate diagnostics.
2. Add or polish a shared V3 navigation entry for the diagnostic page.
3. Add a closed-gate live adapter skeleton only if it always returns blocked while gate is closed.
4. Run another future forwarded-email test and confirm:
   - intake works;
   - row reaches `live_submit_ready`;
   - payload audit is ready;
   - package export works;
   - adapter diagnostic remains blocked by gate and missing approval.
5. Only later, after explicit approval, plan real live-submit adapter work.

## Do not do yet

- Do not enable live EDXEIX submit.
- Do not change the V0 helper or its dependencies.
- Do not submit historical, expired, cancelled, terminal, invalid, synthetic, or past rows.
- Do not convert proof rows into live submission candidates.
- Do not remove V0/manual fallback.
