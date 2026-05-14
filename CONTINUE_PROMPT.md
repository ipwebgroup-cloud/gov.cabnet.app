Continue the gov.cabnet.app Bolt → EDXEIX V3 automation work.

Current immediate patch:
- v3.0.62-v3-kill-switch-approval-alignment

Goal:
- Fix the V3 live adapter kill-switch checker approval validation so it matches the final rehearsal approval validation.

Known state:
- Row 427 reached live_submit_ready.
- Row 427 approval was inserted with phrase:
  I APPROVE V3 ROW FOR CLOSED-GATE REHEARSAL ONLY
- Final rehearsal accepted the approval and blocked only on master gate controls.
- Kill-switch checker still reported approval invalid.
- The patch adds a V3-only fixer script:
  /home/cabnet/gov.cabnet.app_app/cli/fix_v3_kill_switch_approval_alignment.php

Safety:
- Do not touch V0.
- Do not enable live submit.
- Do not call EDXEIX.
- Do not call AADE.
- Do not change queue statuses.
- Do not change SQL schema.
