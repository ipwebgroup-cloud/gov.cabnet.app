<?php
/**
 * V3 kill-switch approval alignment hotfix.
 *
 * Patches:
 *   /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php
 *
 * Purpose:
 * - Align kill-switch approval validation with final rehearsal approval validation.
 * - Use queue_id OR dedupe_key.
 * - Use SQL-side expiry check: expires_at IS NULL OR expires_at >= NOW().
 * - Accept approval_status approved/valid/active.
 * - Require closed_gate_rehearsal_only scope when the column exists.
 *
 * Safety:
 * - V3-only file patch.
 * - No Bolt call.
 * - No EDXEIX call.
 * - No AADE call.
 * - No DB writes.
 * - No queue status changes.
 * - No production submission tables.
 * - No V0 changes.
 */

declare(strict_types=1);

const PATCH_VERSION = 'v3.0.62-v3-kill-switch-approval-alignment';
const TARGET_FILE = '/home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_adapter_kill_switch_check.php';

function out(string $line = ''): void
{
    echo $line . PHP_EOL;
}

function usage(): void
{
    out('Usage: php fix_v3_kill_switch_approval_alignment.php [--check|--apply]');
}

$apply = in_array('--apply', $argv, true);
$check = in_array('--check', $argv, true) || !$apply;

out('V3 kill-switch approval alignment fix ' . PATCH_VERSION);
out('Mode: ' . ($apply ? 'apply' : 'dry_run_check_only'));
out('Target: ' . TARGET_FILE);
out('Safety: V3-only file patch. No Bolt, no EDXEIX, no AADE, no DB writes, no V0 changes.');
out();

if (in_array('--help', $argv, true) || in_array('-h', $argv, true)) {
    usage();
    exit(0);
}

if (!is_file(TARGET_FILE)) {
    out('ERROR: Target file not found.');
    exit(2);
}

$source = file_get_contents(TARGET_FILE);
if (!is_string($source) || $source === '') {
    out('ERROR: Could not read target file.');
    exit(2);
}

$replacement = <<<'PHP'
function v3ks_approval_check(mysqli $db, array $row): array
{
    $queueId = trim((string)($row['id'] ?? ''));
    $dedupeKey = trim((string)($row['dedupe_key'] ?? ''));

    $out = [
        'table_exists' => false,
        'valid' => false,
        'count' => 0,
        'latest' => null,
        'latest_valid' => null,
        'reason' => 'approval table missing',
    ];

    if (!v3ks_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return $out;
    }

    $out['table_exists'] = true;
    $out['reason'] = 'no valid approval found';

    $columns = [];
    $colResult = $db->query("SHOW COLUMNS FROM pre_ride_email_v3_live_submit_approvals");
    if ($colResult instanceof mysqli_result) {
        while ($col = $colResult->fetch_assoc()) {
            $name = (string)($col['Field'] ?? '');
            if ($name !== '') {
                $columns[$name] = true;
            }
        }
    }

    $where = [];
    $params = [];
    $types = '';

    if ($queueId !== '' && isset($columns['queue_id'])) {
        $where[] = 'queue_id = ?';
        $params[] = $queueId;
        $types .= 's';
    }

    if ($dedupeKey !== '' && isset($columns['dedupe_key'])) {
        $where[] = 'dedupe_key = ?';
        $params[] = $dedupeKey;
        $types .= 's';
    }

    if ($where === []) {
        $out['reason'] = 'approval table has no usable queue_id/dedupe_key match column';
        return $out;
    }

    $filters = [];
    if (isset($columns['approval_status'])) {
        $filters[] = "approval_status IN ('approved','valid','active')";
    }
    if (isset($columns['approval_scope'])) {
        $filters[] = "approval_scope = '" . $db->real_escape_string(V3_APPROVAL_SCOPE) . "'";
    }
    if (isset($columns['revoked_at'])) {
        $filters[] = '(revoked_at IS NULL OR revoked_at = \'\' OR revoked_at = \'0000-00-00 00:00:00\')';
    }
    if (isset($columns['expires_at'])) {
        $filters[] = '(expires_at IS NULL OR expires_at = \'\' OR expires_at = \'0000-00-00 00:00:00\' OR expires_at >= NOW())';
    }

    $order = isset($columns['approved_at']) ? 'approved_at DESC, id DESC' : 'id DESC';

    $sql = 'SELECT * FROM pre_ride_email_v3_live_submit_approvals WHERE ('
        . implode(' OR ', $where)
        . ')'
        . ($filters !== [] ? ' AND ' . implode(' AND ', $filters) : '')
        . ' ORDER BY ' . $order . ' LIMIT 5';

    $stmt = $db->prepare($sql);
    if (!$stmt instanceof mysqli_stmt) {
        $out['reason'] = 'approval select prepare failed: ' . $db->error;
        return $out;
    }

    if ($params !== []) {
        $bind = [$types];
        foreach ($params as $i => $param) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    $out['count'] = count($rows);
    $out['latest'] = $rows[0] ?? null;

    if ($rows !== []) {
        $out['valid'] = true;
        $out['latest_valid'] = $rows[0];
        $out['reason'] = 'valid closed-gate rehearsal approval found';
    }

    return $out;
}
PHP;

$pattern = '/function\s+v3ks_approval_check\s*\(\s*mysqli\s+\$db\s*,\s*array\s+\$row\s*\)\s*:\s*array\s*\{.*?\n\}/s';
$count = 0;
$patched = preg_replace($pattern, $replacement, $source, 1, $count);

if (!is_string($patched) || $count !== 1) {
    out('ERROR: Could not locate exactly one v3ks_approval_check() function to patch.');
    out('Matches found: ' . (string)$count);
    exit(3);
}

if ($patched === $source) {
    out('No changes required; target already appears patched.');
    exit(0);
}

out('Detected changes: approval checker function will be replaced.');
out('- Old logic: manual row scan / PHP-side validation.');
out('+ New logic: rehearsal-aligned SQL validation with queue_id OR dedupe_key, status/scope/revoked/expiry filters.');

if (!$apply) {
    out();
    out('Dry run only. Re-run with --apply to write changes.');
    exit(0);
}

$backup = TARGET_FILE . '.bak.' . date('Ymd_His');
if (!copy(TARGET_FILE, $backup)) {
    out('ERROR: Could not create backup: ' . $backup);
    exit(4);
}

if (file_put_contents(TARGET_FILE, $patched) === false) {
    out('ERROR: Could not write target file.');
    exit(5);
}

out();
out('Patched successfully.');
out('Backup: ' . $backup);
out('Next: php -l ' . TARGET_FILE);
