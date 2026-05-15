Continue the gov.cabnet.app Bolt → EDXEIX bridge from v3.0.98.

Current focus: legacy public-root utility cleanup is in read-only audit/readiness posture.

Important safety facts:

- Production pre-ride tool `/ops/pre-ride-email-tool.php` is untouched.
- Legacy public-root utility endpoints are untouched.
- No routes were moved or deleted.
- No redirects were added.
- Live EDXEIX submission remains disabled.
- No DB, Bolt, EDXEIX, or AADE calls are made by the read-only audit tools.

Latest added tool:

- CLI: `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_readiness_board.php`
- Ops: `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-readiness-board.php`

Next safe action:

Run syntax, auth redirect, and CLI JSON checks for v3.0.98. If clean, commit. Do not start compatibility-stub or route-retirement changes without explicit approval.
