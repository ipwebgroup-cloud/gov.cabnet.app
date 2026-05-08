# gov.cabnet.app — v6.1.6 Live Production Commit Patch

## Included changes

- Adds Bolt mail production tick wrapper.
- Allows late real Bolt mail to create/link normalized bookings for AADE receipt recovery.
- Fixes AADE lineComments max-length issue.
- Fixes AADE incomeClassification namespace.
- Fixes AADE numeric AA requirement.
- Adds official receipt office-copy support.
- Allows AADE receipt recovery without requiring EDXEIX vehicle mapping when Bolt vehicle plate exists.
- Keeps EDXEIX vehicle mapping required for EDXEIX live submit.
- Does not include real config, logs, sessions, cookies, PDFs, backups, or one-shot recovery scripts.

## Commit title

Validate AADE late Bolt mail receipt recovery

## Commit description

Adds and validates late Bolt mail recovery for AADE/myDATA receipt issuance. Real parsed Bolt mail can now create a normalized booking even after pickup time for receipt purposes, while EDXEIX live submission remains protected.

Fixes AADE XML schema issues for lineComments length, incomeClassification namespace, and numeric AA values. Adds configurable office copy recipients for official AADE receipt emails. Allows AADE receipt recovery without requiring EDXEIX vehicle mapping when the Bolt vehicle plate is present. Confirms official PDF generation and driver receipt email delivery with no EDXEIX jobs or attempts created.
