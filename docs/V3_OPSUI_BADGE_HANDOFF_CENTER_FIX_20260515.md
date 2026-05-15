# V3.1.13 — Restore `opsui_badge()` for Handoff Center

## Purpose

This patch restores the shared `opsui_badge()` helper in `/ops/_shell.php` so `/ops/handoff-center.php` can render its badge/status controls and package builder sections again.

## Verified live issue

The Handoff Center rendered only the intro area and stopped before the package buttons. The page calls `opsui_badge()`, but the shared shell no longer defined the helper.

## Safety posture

- No Bolt calls.
- No EDXEIX calls.
- No AADE calls.
- No database writes.
- No queue mutations.
- No route moves, deletes, or redirects.
- Live EDXEIX submission remains disabled.
- Production Pre-Ride Tool remains untouched.

## Changed file

- `public_html/gov.cabnet.app/ops/_shell.php`

## Verification

Expected live checks:

```bash
php -l /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php

php -d display_errors=1 -r '
$_SERVER["REQUEST_METHOD"]="GET";
$_SERVER["REQUEST_URI"]="/ops/handoff-center.php";
$_SERVER["SCRIPT_FILENAME"]="/home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php";
ob_start();
require "/home/cabnet/public_html/gov.cabnet.app/ops/handoff-center.php";
$out = ob_get_clean();
echo "has_git_safe_button=" . (strpos($out, "Build Git-Safe Continuity ZIP") !== false ? "true" : "false") . PHP_EOL;
echo "has_copy_prompt=" . (strpos($out, "Copy/paste prompt") !== false ? "true" : "false") . PHP_EOL;
echo "has_safe_file_check=" . (strpos($out, "Safe file presence check") !== false ? "true" : "false") . PHP_EOL;
'

curl -I --max-time 10 https://gov.cabnet.app/ops/handoff-center.php

grep -n "v3.1.13\|function opsui_badge\|Restores opsui_badge" /home/cabnet/public_html/gov.cabnet.app/ops/_shell.php
```

Expected output:

```text
No syntax errors
has_git_safe_button=true
has_copy_prompt=true
has_safe_file_check=true
HTTP 302 to /ops/login.php when unauthenticated
v3.1.13 marker present
function opsui_badge present
```
