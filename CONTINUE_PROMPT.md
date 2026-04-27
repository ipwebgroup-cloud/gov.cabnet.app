Sophion, continue the gov.cabnet.app Bolt → EDXEIX bridge project.

Current state:
- v2.7 adds `/ops/edxeix-session-probe.php`.
- Default page load is local metadata only.
- `?probe=1` performs a read-only GET to the configured EDXEIX target and never POSTs.
- Live EDXEIX submit remains disabled.
- There is no eligible live candidate yet.

Continue safely:
- Do not enable live EDXEIX submission.
- Do not stage jobs from completed/historical/terminal rows.
- Next work may update the route index/nav links or design the final submit handler guarded behind explicit approval.
