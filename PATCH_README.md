# gov.cabnet.app patch — Ops UI Shell Phase 19 Documentation Center

## Upload paths

Upload:

```text
public_html/gov.cabnet.app/ops/documentation-center.php
```

to:

```text
/home/cabnet/public_html/gov.cabnet.app/ops/documentation-center.php
```

Optional repo documentation:

```text
docs/OPS_UI_SHELL_PHASE19_DOCUMENTATION_CENTER_2026_05_11.md
```

## SQL

None.

## Verify

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/documentation-center.php
```

Open:

```text
https://gov.cabnet.app/ops/documentation-center.php
```

Expected:

- login required
- page opens in the shared ops shell
- documentation cards display route availability and safe fingerprints
- production pre-ride tool remains unchanged

## Commit title

```text
Add ops documentation center
```

## Commit description

```text
Continues the unified EDXEIX-style /ops GUI by adding a read-only Documentation Center. The page indexes operator SOPs, user/profile pages, admin visibility pages, helper guidance, deployment notes, and continuity tools with safe file status details.

The production pre-ride email tool remains unchanged. No Bolt calls, EDXEIX calls, AADE calls, secret output, database writes, queue staging, or live submission behavior are added.
```
