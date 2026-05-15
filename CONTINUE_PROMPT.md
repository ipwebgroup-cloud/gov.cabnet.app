Continue the gov.cabnet.app Bolt → EDXEIX bridge from the v3.1.4 V3 Real-Mail Expiry Audit Alignment state.

Critical posture:
- Do not enable live EDXEIX submission.
- Keep V3 read-only/dry-run/closed-gate.
- Production Pre-Ride Tool and V0 workflow must remain untouched.
- No database writes or queue mutations unless Andreas explicitly requests them.

Current task:
- Verify v3.1.4 expiry audit alignment after upload.
- Confirm possible-real count differences are explained by possible_real_mail_non_expired_guard_rows and/or mapping correction rows.
- Continue observing for a real future pre-ride email before any live-readiness discussion.
