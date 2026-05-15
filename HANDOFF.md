# HANDOFF — gov.cabnet.app v3.0.92 legacy public utility usage audit

Current state:

- Production pre-ride tool remains untouched.
- V3 live gate remains closed.
- Live EDXEIX submission remains disabled.
- Legacy public-root utilities remain in place for compatibility.
- v3.0.92 adds a read-only usage audit for those legacy utilities.

New files:

- `/home/cabnet/gov.cabnet.app_app/cli/legacy_public_utility_usage_audit.php`
- `/home/cabnet/public_html/gov.cabnet.app/ops/legacy-public-utility-usage-audit.php`

Changed file:

- `/home/cabnet/public_html/gov.cabnet.app/ops/_shell.php` adds Developer Archive link.

No SQL changes. No routes moved or deleted. No legacy utilities executed.
