# gov.cabnet.app Handoff — 2026-05-17 EDXEIX Pre-Ride Candidate v3.2.26

Current posture:

- Production V0 remains unaffected.
- EDXEIX live submission remains disabled.
- AADE/myDATA production receipt issuing remains untouched.
- Future guard is configured at 30 minutes in both server config files.
- v3.2.22 added a separate `bolt_pre_ride_email` diagnostic candidate path.
- v3.2.24 proved the latest Maildir email contains expected HTML labels but parser output was empty.
- v3.2.25 added HTML cleanup but validation showed fallback label hits were still zero because the multi-label match guard rejected normal positive match counts.
- v3.2.26 fixes that diagnostics-only fallback parser guard.

Next action:

1. Upload v3.2.26.
2. Run syntax checks.
3. Run the latest-mail pre-ride diagnostic with `--debug-source=1`.
4. Confirm fallback label hits are positive and parsed fields populate.
5. Do not enable EDXEIX transport until a real future mapped candidate is confirmed.
