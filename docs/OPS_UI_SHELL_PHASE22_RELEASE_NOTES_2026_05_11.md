# Ops UI Shell Phase 22 — Release Notes / Change Register

Adds `/ops/release-notes.php`, a read-only shared-shell page that centralizes the GUI rollout record and safe route availability snapshot.

Safety:
- Does not modify `/ops/pre-ride-email-tool.php`.
- Does not call Bolt, EDXEIX, or AADE.
- Does not read/display secrets.
- Does not write database rows.
- Does not stage jobs or enable live submission.

Verification:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/release-notes.php
```

Open:

```text
https://gov.cabnet.app/ops/release-notes.php
```
