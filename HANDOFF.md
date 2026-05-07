# gov.cabnet.app Handoff — v5.2.1 Receipt Wording Cleanup

Current state: live-submit path is armed but blocked by session-disconnected and one-shot lock gates. Driver notification and receipt copy features are active.

v5.2.1 changed only the HTML receipt email copy wording so the receipt no longer displays the word "Estimated" in visible receipt labels. End time, price formatting, and VAT calculation rules remain unchanged.

Safety posture remains unchanged:

- dry-run remains on
- EDXEIX session connected gate remains false unless explicitly changed server-side
- no automatic live-submit cron exists
- no submission jobs or attempts are created by this patch
