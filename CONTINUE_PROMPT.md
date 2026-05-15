You are Sophion assisting Andreas with the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- Latest patch: v3.2.3 — EDXEIX Payload Preview / Dry-Run Preflight.
- Production Pre-Ride Tool remains untouched: /home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php
- Live EDXEIX submit remains disabled.
- V3 live gate remains closed.
- v3.2.3 is read-only and performs no Bolt, EDXEIX, AADE, DB write, queue mutation, SQL change, route move, route delete, redirect, cron install, notification, log write, or live submit.

Recent proof milestone:
- A real-format pre-ride email was manually placed in the Bolt bridge mailbox path.
- The V3 queue ingested it and detected a complete future candidate.
- Status-line showed: action=REVIEW_COMPLETE_FUTURE_CANDIDATE, future=1, review=1, alerts=1, urgent=1, live_risk=no, complete=yes.
- Full report showed queue id 1457, missing_required_fields=[], parser_ok=true, mapping_ok=true, future_ok_flag=true, capture_readiness=ready_for_closed_gate_operator_review, live_submit_recommended_now=0.
- v3.2.2 added sanitized candidate evidence snapshot.

v3.2.3 added:
- EDXEIX payload preview / dry-run preflight CLI mode:
  /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_real_future_candidate_capture_readiness.php --edxeix-preview-json
- Aliases:
  --payload-preview-json
  --dry-run-preflight-json
- EDXEIX Payload Preview / Dry-Run Preflight section on:
  /ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php
- Preview shows normalized EDXEIX candidate fields in sanitized form, masks passenger phone, and keeps live submit blocked.

Next safest action:
- Verify v3.2.3 in production using HANDOFF.md / PATCH_README.md.
- If verified, commit with the provided title/description.
- Next development can design a controlled single-candidate live-submit gate only if Andreas explicitly asks for a live-submit update.
- Do not enable live EDXEIX submission unless Andreas explicitly asks for a separate live-submit update and all gates pass.

Current latest patch: v3.2.4 — Expired Candidate Safety Regression Audit. Use the uploaded/live verification output as source of truth. Next safest step after verification is to decide whether to build a controlled live-submit gate simulator/approval checklist, still without enabling live EDXEIX submission unless Andreas explicitly requests it.


Latest installed target after this package: v3.2.5 — Controlled Live-Submit Readiness Checklist. Use `--live-readiness-json` to summarize go/no-go state. Keep live submit disabled unless Andreas explicitly requests a live-submit update in a future message.


Current latest patch: v3.2.6 — Single-Row Controlled Live-Submit Design Draft. Continue with read-only preflight/design work unless Andreas explicitly requests a live-submit update. Live submit remains disabled.


Current latest patch: v3.2.7 — Controlled Live-Submit Runbook / Authorization Packet. Continue with read-only preflight/runbook work unless Andreas explicitly requests a live-submit update. Live submit remains disabled.


Current latest patch target: v3.2.8 Real-Format Demo Mail Fixture Preview. Keep live submit blocked and do not add a Maildir writer unless Andreas explicitly requests it.


Latest planned patch: v3.2.9 — Controlled Maildir Fixture Writer Design Draft. Continue by verifying the new `--maildir-writer-design-json` output. Do not enable Maildir writes or live submit unless Andreas explicitly requests a separate writer/live-submit patch.
