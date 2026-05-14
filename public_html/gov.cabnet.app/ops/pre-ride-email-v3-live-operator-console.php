<?php

declare(strict_types=1);

/**
 * V3 Live Operator Console
 * Version: v3.0.76-v3-live-operator-console
 *
 * Read-only Ops dashboard for the V3 Bolt pre-ride email automation.
 * Safety guarantees in this file:
 * - No shell_exec/exec/proc_open/system/passthru.
 * - No Bolt API calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No DB writes.
 * - No queue status changes.
 * - No production submission table writes.
 * - No V0 changes.
 */

const V3OC_VERSION = 'v3.0.76-v3-live-operator-console';
const V3OC_APP_ROOT = '/home/cabnet/gov.cabnet.app_app';
const V3OC_CONFIG_ROOT = '/home/cabnet/gov.cabnet.app_config';
const V3OC_APPROVAL_SCOPE = 'closed_gate_rehearsal_only';
const V3OC_SAFETY = 'Read-only console. No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.';

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

date_default_timezone_set('Europe/Athens');

/** @return string */
function v3oc_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @return string */
function v3oc_yn($value): string
{
    return !empty($value) ? 'yes' : 'no';
}

/** @return string */
function v3oc_badge(string $text, string $type = 'neutral'): string
{
    $classes = [
        'good' => 'badge good',
        'bad' => 'badge bad',
        'warn' => 'badge warn',
        'neutral' => 'badge neutral',
        'dark' => 'badge dark',
        'blue' => 'badge blue',
    ];
    return '<span class="' . v3oc_h($classes[$type] ?? $classes['neutral']) . '">' . v3oc_h($text) . '</span>';
}

/** @return array<string,mixed> */
function v3oc_base_report(): array
{
    return [
        'ok' => false,
        'version' => V3OC_VERSION,
        'mode' => 'read_only_live_operator_console',
        'started_at' => date('c'),
        'safety' => V3OC_SAFETY,
        'app_root' => V3OC_APP_ROOT,
        'events' => [],
        'final_blocks' => [],
    ];
}

/** @param array<string,mixed> $report */
function v3oc_event(array &$report, string $level, string $message): void
{
    $report['events'][] = ['level' => $level, 'message' => $message];
}

function v3oc_db(array &$report): ?mysqli
{
    $bootstrap = V3OC_APP_ROOT . '/src/bootstrap.php';
    if (!is_file($bootstrap) || !is_readable($bootstrap)) {
        v3oc_event($report, 'error', 'Bootstrap not readable: ' . $bootstrap);
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !is_object($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            v3oc_event($report, 'error', 'Bootstrap did not return a usable database object.');
            return null;
        }
        $conn = $ctx['db']->connection();
        if (!$conn instanceof mysqli) {
            v3oc_event($report, 'error', 'Database connection is not mysqli.');
            return null;
        }
        $conn->set_charset('utf8mb4');
        return $conn;
    } catch (Throwable $e) {
        v3oc_event($report, 'error', 'Database bootstrap failed: ' . $e->getMessage());
        return null;
    }
}

/** @param array<int,mixed> $params @return array<int,array<string,mixed>> */
function v3oc_fetch_all(mysqli $db, string $sql, array $params = [], string $types = ''): array
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('prepare failed: ' . $db->error);
    }

    if ($params !== []) {
        if ($types === '') {
            foreach ($params as $p) {
                $types .= is_int($p) ? 'i' : (is_float($p) ? 'd' : 's');
            }
        }
        $bind = [$types];
        foreach ($params as $i => $p) {
            $bind[] = &$params[$i];
        }
        call_user_func_array([$stmt, 'bind_param'], $bind);
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('execute failed: ' . $stmt->error);
    }
    $res = $stmt->get_result();
    if (!$res) {
        return [];
    }
    return $res->fetch_all(MYSQLI_ASSOC);
}

/** @param array<int,mixed> $params @return array<string,mixed>|null */
function v3oc_fetch_one(mysqli $db, string $sql, array $params = [], string $types = ''): ?array
{
    $rows = v3oc_fetch_all($db, $sql, $params, $types);
    return $rows[0] ?? null;
}

function v3oc_table_exists(mysqli $db, string $table): bool
{
    $row = v3oc_fetch_one(
        $db,
        'SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1',
        [$table]
    );
    return (int)($row['c'] ?? 0) > 0;
}

/** @return array<string,bool> */
function v3oc_table_columns(mysqli $db, string $table): array
{
    $rows = v3oc_fetch_all(
        $db,
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
    $out = [];
    foreach ($rows as $row) {
        $name = (string)($row['COLUMN_NAME'] ?? '');
        if ($name !== '') {
            $out[$name] = true;
        }
    }
    return $out;
}

/** @return array<string,mixed> */
function v3oc_load_gate(): array
{
    $path = V3OC_CONFIG_ROOT . '/pre_ride_email_v3_live_submit.php';
    $out = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'returned_array' => false,
        'enabled' => false,
        'mode' => '',
        'adapter' => '',
        'hard_enable_live_submit' => false,
        'acknowledgement_phrase_present' => false,
        'expected_closed_pre_live' => false,
        'live_risk_detected' => false,
        'full_live_switch_looks_open' => false,
        'blocks' => [],
        'partial_live_signals' => [],
        'error' => '',
    ];

    if (!$out['exists'] || !$out['readable']) {
        $out['blocks'][] = 'master_gate: config missing or unreadable';
        return $out;
    }

    try {
        $cfg = require $path;
        $out['loaded'] = true;
        $out['returned_array'] = is_array($cfg);
        if (!is_array($cfg)) {
            $out['blocks'][] = 'master_gate: config did not return an array';
            return $out;
        }

        $out['enabled'] = !empty($cfg['enabled']);
        $out['mode'] = trim((string)($cfg['mode'] ?? ''));
        $out['adapter'] = trim((string)($cfg['adapter'] ?? ''));
        $out['hard_enable_live_submit'] = !empty($cfg['hard_enable_live_submit']);

        $ack = '';
        foreach (['acknowledgement_phrase', 'required_acknowledgement_phrase', 'operator_acknowledgement_phrase', 'acknowledgement'] as $key) {
            if (isset($cfg[$key]) && trim((string)$cfg[$key]) !== '') {
                $ack = trim((string)$cfg[$key]);
                break;
            }
        }
        $out['acknowledgement_phrase_present'] = $ack !== '';
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        $out['blocks'][] = 'master_gate: config load failed: ' . $e->getMessage();
        return $out;
    }

    if (!$out['enabled']) {
        $out['blocks'][] = 'master_gate: enabled is false';
    } else {
        $out['partial_live_signals'][] = 'enabled=true';
    }
    if ($out['mode'] !== 'live') {
        $out['blocks'][] = 'master_gate: mode is not live';
    } else {
        $out['partial_live_signals'][] = 'mode=live';
    }
    if ($out['adapter'] !== 'edxeix_live') {
        $out['blocks'][] = 'master_gate: adapter is not edxeix_live';
    } else {
        $out['partial_live_signals'][] = 'adapter=edxeix_live';
    }
    if (!$out['hard_enable_live_submit']) {
        $out['blocks'][] = 'master_gate: hard_enable_live_submit is false';
    } else {
        $out['partial_live_signals'][] = 'hard_enable_live_submit=true';
    }
    if (!$out['acknowledgement_phrase_present']) {
        $out['blocks'][] = 'master_gate: acknowledgement phrase is not present';
    }

    $out['expected_closed_pre_live'] = !$out['enabled'] && $out['mode'] === 'disabled' && $out['adapter'] === 'disabled' && !$out['hard_enable_live_submit'];
    $out['full_live_switch_looks_open'] = $out['enabled'] && $out['mode'] === 'live' && $out['adapter'] === 'edxeix_live' && $out['hard_enable_live_submit'] && $out['acknowledgement_phrase_present'];
    $out['live_risk_detected'] = $out['full_live_switch_looks_open'] || ($out['partial_live_signals'] !== [] && !$out['expected_closed_pre_live']);

    return $out;
}

/** @return array<string,mixed> */
function v3oc_scan_adapter_file(string $path): array
{
    $scan = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => 0,
        'modified_at' => '',
        'contains_submit_method' => false,
        'contains_curl' => false,
        'contains_file_get_contents_http' => false,
        'contains_stream_context' => false,
        'contains_live_capable_true' => false,
        'contains_skeleton_not_implemented' => false,
        'looks_network_aware' => false,
    ];

    if (!$scan['exists'] || !$scan['readable']) {
        return $scan;
    }

    $scan['size'] = (int)filesize($path);
    $mtime = filemtime($path);
    $scan['modified_at'] = $mtime ? date('Y-m-d H:i:s', $mtime) : '';
    $body = (string)file_get_contents($path);
    $scan['contains_submit_method'] = strpos($body, 'function submit') !== false;
    $scan['contains_curl'] = stripos($body, 'curl_') !== false || stripos($body, 'curl_init') !== false;
    $scan['contains_file_get_contents_http'] = stripos($body, 'file_get_contents(') !== false && (stripos($body, 'http://') !== false || stripos($body, 'https://') !== false);
    $scan['contains_stream_context'] = stripos($body, 'stream_context_create') !== false;
    $scan['contains_live_capable_true'] = preg_match('/isLiveCapable\s*\([^)]*\)\s*:\s*bool\s*\{[^}]*return\s+true\s*;/is', $body) === 1;
    $scan['contains_skeleton_not_implemented'] = stripos($body, 'skeleton_not_implemented') !== false || stripos($body, 'not implemented') !== false;
    $scan['looks_network_aware'] = $scan['contains_curl'] || $scan['contains_file_get_contents_http'] || $scan['contains_stream_context'];
    return $scan;
}

/** @return array<string,mixed> */
function v3oc_adapter_files(): array
{
    $files = [
        'interface' => V3OC_APP_ROOT . '/src/BoltMailV3/LiveSubmitAdapterV3.php',
        'disabled' => V3OC_APP_ROOT . '/src/BoltMailV3/DisabledLiveSubmitAdapterV3.php',
        'dry_run' => V3OC_APP_ROOT . '/src/BoltMailV3/DryRunLiveSubmitAdapterV3.php',
        'edxeix_live' => V3OC_APP_ROOT . '/src/BoltMailV3/EdxeixLiveSubmitAdapterV3.php',
    ];
    $out = [];
    foreach ($files as $key => $path) {
        $out[$key] = v3oc_scan_adapter_file($path);
    }
    return $out;
}

/** @return array<string,mixed> */
function v3oc_queue_metrics(mysqli $db): array
{
    $out = ['table_exists' => v3oc_table_exists($db, 'pre_ride_email_v3_queue')];
    if (!$out['table_exists']) {
        return $out;
    }
    $row = v3oc_fetch_one($db, "
        SELECT
            COUNT(*) AS total,
            SUM(queue_status IN ('queued','submit_dry_run_ready','live_submit_ready')) AS active,
            SUM(queue_status = 'live_submit_ready') AS live_submit_ready,
            SUM(queue_status = 'submit_dry_run_ready') AS submit_dry_run_ready,
            SUM(queue_status = 'blocked') AS blocked,
            SUM(queue_status = 'submitted') AS submitted,
            SUM(queue_status IN ('queued','submit_dry_run_ready','live_submit_ready') AND pickup_datetime >= NOW()) AS future_active
        FROM pre_ride_email_v3_queue
    ");
    foreach (($row ?? []) as $k => $v) {
        $out[$k] = (int)$v;
    }
    return $out;
}

/** @return array<int,array<string,mixed>> */
function v3oc_queue_rows(mysqli $db, ?int $queueId, int $limit = 10): array
{
    if (!v3oc_table_exists($db, 'pre_ride_email_v3_queue')) {
        return [];
    }
    if ($queueId !== null && $queueId > 0) {
        return v3oc_fetch_all(
            $db,
            'SELECT *, TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until_now FROM pre_ride_email_v3_queue WHERE id = ? LIMIT 1',
            [$queueId],
            'i'
        );
    }

    $rows = v3oc_fetch_all(
        $db,
        "SELECT *, TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until_now
         FROM pre_ride_email_v3_queue
         WHERE queue_status IN ('live_submit_ready','submit_dry_run_ready','queued')
         ORDER BY FIELD(queue_status,'live_submit_ready','submit_dry_run_ready','queued'), pickup_datetime ASC, id DESC
         LIMIT ?",
        [$limit],
        'i'
    );
    if ($rows !== []) {
        return $rows;
    }
    return v3oc_fetch_all(
        $db,
        'SELECT *, TIMESTAMPDIFF(MINUTE, NOW(), pickup_datetime) AS minutes_until_now FROM pre_ride_email_v3_queue ORDER BY id DESC LIMIT ?',
        [$limit],
        'i'
    );
}

/** @return array{complete:bool,missing:array<int,string>,values:array<string,string>,edxeix_payload:array<string,string>,hash_sha256:string} */
function v3oc_payload_check(array $row): array
{
    $required = [
        'lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id', 'customer_name', 'customer_phone',
        'pickup_datetime', 'estimated_end_datetime', 'pickup_address', 'dropoff_address', 'price_amount', 'payload_json',
    ];
    $values = [];
    $missing = [];
    foreach ($required as $key) {
        $v = trim((string)($row[$key] ?? ''));
        $values[$key] = $v;
        if ($v === '') {
            $missing[] = $key;
        }
    }

    $payload = [
        'lessor' => (string)($row['lessor_id'] ?? ''),
        'driver' => (string)($row['driver_id'] ?? ''),
        'vehicle' => (string)($row['vehicle_id'] ?? ''),
        'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
        'lessee_name' => (string)($row['customer_name'] ?? ''),
        'lessee_phone' => (string)($row['customer_phone'] ?? ''),
        'boarding_point' => (string)($row['pickup_address'] ?? ''),
        'disembark_point' => (string)($row['dropoff_address'] ?? ''),
        'started_at' => (string)($row['pickup_datetime'] ?? ''),
        'ended_at' => (string)($row['estimated_end_datetime'] ?? ''),
        'price' => (string)($row['price_amount'] ?? ''),
        'price_text' => (string)($row['price_text'] ?? ''),
    ];
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    return [
        'complete' => $missing === [],
        'missing' => $missing,
        'values' => $values,
        'edxeix_payload' => $payload,
        'hash_sha256' => hash('sha256', $json),
    ];
}

/** @return array<string,mixed> */
function v3oc_starting_point(mysqli $db, array $row): array
{
    $out = ['ok' => false, 'label' => '', 'reason' => 'not checked'];
    if (!v3oc_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        $out['reason'] = 'starting point options table missing';
        return $out;
    }
    $lessor = trim((string)($row['lessor_id'] ?? ''));
    $start = trim((string)($row['starting_point_id'] ?? ''));
    if ($lessor === '' || $start === '') {
        $out['reason'] = 'lessor or starting point missing';
        return $out;
    }
    $opt = v3oc_fetch_one(
        $db,
        'SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1',
        [$lessor, $start]
    );
    if ($opt) {
        $out['ok'] = true;
        $out['label'] = (string)($opt['label'] ?? '');
        $out['reason'] = 'operator_verified';
        return $out;
    }
    $out['reason'] = 'no active operator-verified starting point found';
    return $out;
}

/** @return array<string,mixed> */
function v3oc_approval(mysqli $db, array $row): array
{
    $out = ['table_exists' => false, 'valid' => false, 'count' => 0, 'latest' => null, 'reason' => 'approval table missing'];
    if (!v3oc_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return $out;
    }
    $out['table_exists'] = true;
    $cols = v3oc_table_columns($db, 'pre_ride_email_v3_live_submit_approvals');
    $queueId = (int)($row['id'] ?? 0);
    $dedupeKey = trim((string)($row['dedupe_key'] ?? ''));

    $where = [];
    $whereParams = [];
    $whereTypes = '';
    if (isset($cols['queue_id']) && $queueId > 0) {
        $where[] = 'queue_id = ?';
        $whereParams[] = $queueId;
        $whereTypes .= 'i';
    }
    if (isset($cols['dedupe_key']) && $dedupeKey !== '') {
        $where[] = 'dedupe_key = ?';
        $whereParams[] = $dedupeKey;
        $whereTypes .= 's';
    }
    if ($where === []) {
        $out['reason'] = 'approval table has no usable queue_id/dedupe_key column';
        return $out;
    }

    $count = v3oc_fetch_one(
        $db,
        'SELECT COUNT(*) AS c FROM pre_ride_email_v3_live_submit_approvals WHERE (' . implode(' OR ', $where) . ')',
        $whereParams,
        $whereTypes
    );
    $out['count'] = (int)($count['c'] ?? 0);

    $filters = [];
    $params = $whereParams;
    $types = $whereTypes;
    if (isset($cols['approval_status'])) {
        $filters[] = "approval_status IN ('approved','valid','active')";
    }
    if (isset($cols['approval_scope'])) {
        $filters[] = 'approval_scope = ?';
        $params[] = V3OC_APPROVAL_SCOPE;
        $types .= 's';
    }
    if (isset($cols['revoked_at'])) {
        $filters[] = '(revoked_at IS NULL OR revoked_at = \'\')';
    }
    if (isset($cols['expires_at'])) {
        $filters[] = '(expires_at IS NULL OR expires_at >= NOW())';
    }

    $sql = 'SELECT * FROM pre_ride_email_v3_live_submit_approvals WHERE (' . implode(' OR ', $where) . ')';
    if ($filters !== []) {
        $sql .= ' AND ' . implode(' AND ', $filters);
    }
    $sql .= ' ORDER BY id DESC LIMIT 1';

    $latest = v3oc_fetch_one($db, $sql, $params, $types);
    if (!$latest) {
        $out['reason'] = 'no valid closed-gate rehearsal approval found';
        return $out;
    }

    $out['valid'] = true;
    $out['latest'] = $latest;
    $out['reason'] = 'valid closed-gate rehearsal approval found';
    return $out;
}

/** @return array<string,mixed> */
function v3oc_live_package_artifacts(?int $queueId = null): array
{
    $dir = V3OC_APP_ROOT . '/storage/artifacts/v3_live_submit_packages';
    $out = ['dir' => $dir, 'exists' => is_dir($dir), 'readable' => is_readable($dir), 'total_edxeix_fields' => 0, 'queue_count' => 0, 'latest' => []];
    if (!$out['exists'] || !$out['readable']) {
        return $out;
    }

    $files = [];
    foreach (scandir($dir) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $isFields = str_ends_with($file, '_edxeix_fields.json');
        if ($isFields) {
            $out['total_edxeix_fields']++;
        }
        if ($queueId !== null && $queueId > 0 && strpos($file, 'queue_' . $queueId . '_') !== 0) {
            continue;
        }
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            $files[] = ['name' => $file, 'path' => $path, 'mtime' => filemtime($path) ?: 0, 'size' => filesize($path) ?: 0];
        }
    }
    usort($files, static fn($a, $b) => (int)$b['mtime'] <=> (int)$a['mtime']);
    $out['queue_count'] = count($files);
    $out['latest'] = array_slice($files, 0, 8);
    return $out;
}

/** @return array<string,mixed> */
function v3oc_proof_bundles(): array
{
    $dir = V3OC_APP_ROOT . '/storage/artifacts/v3_pre_live_proof_bundles';
    $out = ['dir' => $dir, 'exists' => is_dir($dir), 'readable' => is_readable($dir), 'summary_json_count' => 0, 'latest' => []];
    if (!$out['exists'] || !$out['readable']) {
        return $out;
    }

    $files = [];
    foreach (scandir($dir) ?: [] as $file) {
        if (!str_ends_with($file, '_summary.json') && !str_ends_with($file, '_summary.txt')) {
            continue;
        }
        if (str_ends_with($file, '_summary.json')) {
            $out['summary_json_count']++;
        }
        $path = $dir . '/' . $file;
        if (is_file($path)) {
            $files[] = ['name' => $file, 'path' => $path, 'mtime' => filemtime($path) ?: 0, 'size' => filesize($path) ?: 0];
        }
    }
    usort($files, static fn($a, $b) => (int)$b['mtime'] <=> (int)$a['mtime']);
    $out['latest'] = array_slice($files, 0, 8);
    return $out;
}

/** @return array<string,mixed> */
function v3oc_row_status(mysqli $db, array $row): array
{
    $payload = v3oc_payload_check($row);
    $start = v3oc_starting_point($db, $row);
    $approval = v3oc_approval($db, $row);
    $pkg = v3oc_live_package_artifacts((int)($row['id'] ?? 0));
    $futureSafe = isset($row['minutes_until_now']) && (int)$row['minutes_until_now'] >= 0;
    $readyForClosedGateProof = (string)($row['queue_status'] ?? '') === 'live_submit_ready'
        && $futureSafe
        && !empty($payload['complete'])
        && !empty($start['ok'])
        && !empty($approval['valid']);

    return [
        'payload' => $payload,
        'starting_point' => $start,
        'approval' => $approval,
        'package_artifacts' => $pkg,
        'future_safe' => $futureSafe,
        'ready_for_closed_gate_proof' => $readyForClosedGateProof,
    ];
}

/** @return array<string,mixed> */
function v3oc_build_report(?int $requestedQueueId): array
{
    $report = v3oc_base_report();
    $db = v3oc_db($report);
    if (!$db) {
        $report['final_blocks'][] = 'system: database unavailable';
        $report['finished_at'] = date('c');
        return $report;
    }

    try {
        $report['database'] = ['connected' => true, 'name' => (string)($db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '')];
        $report['gate'] = v3oc_load_gate();
        $report['adapter_files'] = v3oc_adapter_files();
        $report['queue_metrics'] = v3oc_queue_metrics($db);
        $rows = v3oc_queue_rows($db, $requestedQueueId, 12);
        $report['rows'] = [];
        foreach ($rows as $row) {
            $rowStatus = v3oc_row_status($db, $row);
            $report['rows'][] = ['row' => $row, 'status' => $rowStatus];
        }
        $report['selected_queue_row'] = $report['rows'][0]['row'] ?? null;
        $report['selected_status'] = $report['rows'][0]['status'] ?? null;
        $selectedId = isset($report['selected_queue_row']['id']) ? (int)$report['selected_queue_row']['id'] : null;
        $report['live_package_artifacts'] = v3oc_live_package_artifacts($selectedId);
        $report['proof_bundles'] = v3oc_proof_bundles();

        $gate = $report['gate'];
        foreach ((array)($gate['blocks'] ?? []) as $block) {
            $report['final_blocks'][] = (string)$block;
        }
        if (!empty($gate['live_risk_detected'])) {
            $report['final_blocks'][] = 'safety: live gate drift or live-risk signal detected';
        }

        $selectedStatus = is_array($report['selected_status']) ? $report['selected_status'] : [];
        if ($selectedStatus !== []) {
            if (empty($selectedStatus['future_safe'])) {
                $report['final_blocks'][] = 'queue: selected row is not future-safe';
            }
            if (empty($selectedStatus['payload']['complete'])) {
                $report['final_blocks'][] = 'payload: selected row is incomplete';
            }
            if (empty($selectedStatus['starting_point']['ok'])) {
                $report['final_blocks'][] = 'starting_point: selected row is not verified';
            }
            if (empty($selectedStatus['approval']['valid'])) {
                $report['final_blocks'][] = 'approval: selected row has no valid closed-gate rehearsal approval';
            }
        } else {
            $report['final_blocks'][] = 'queue: no selected row';
        }

        $report['final_blocks'] = array_values(array_unique($report['final_blocks']));
        $report['console_safe'] = empty($gate['live_risk_detected']);
        $report['ok'] = !empty($report['console_safe']) && !empty($report['queue_metrics']['table_exists']);
        $report['finished_at'] = date('c');
        return $report;
    } catch (Throwable $e) {
        v3oc_event($report, 'error', $e->getMessage());
        $report['final_blocks'][] = 'system: ' . $e->getMessage();
        $report['finished_at'] = date('c');
        return $report;
    }
}

$requestedQueueId = null;
if (isset($_GET['queue_id']) && preg_match('/^\d+$/', (string)$_GET['queue_id'])) {
    $requestedQueueId = (int)$_GET['queue_id'];
}
$report = v3oc_build_report($requestedQueueId);

if (isset($_GET['json']) && (string)$_GET['json'] === '1') {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$gate = is_array($report['gate'] ?? null) ? $report['gate'] : [];
$metrics = is_array($report['queue_metrics'] ?? null) ? $report['queue_metrics'] : [];
$rows = is_array($report['rows'] ?? null) ? $report['rows'] : [];
$selectedRow = is_array($report['selected_queue_row'] ?? null) ? $report['selected_queue_row'] : [];
$selectedStatus = is_array($report['selected_status'] ?? null) ? $report['selected_status'] : [];
$liveArtifacts = is_array($report['live_package_artifacts'] ?? null) ? $report['live_package_artifacts'] : [];
$proofBundles = is_array($report['proof_bundles'] ?? null) ? $report['proof_bundles'] : [];
$adapterFiles = is_array($report['adapter_files'] ?? null) ? $report['adapter_files'] : [];
$blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];
$events = is_array($report['events'] ?? null) ? $report['events'] : [];

$gateClosed = !empty($gate['expected_closed_pre_live']);
$liveRisk = !empty($gate['live_risk_detected']);

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>V3 Live Operator Console</title>
<style>
:root{--bg:#eef2f7;--card:#fff;--nav:#2f365d;--ink:#071d49;--muted:#50658d;--line:#d8e0ee;--green:#2f9e55;--red:#b42318;--orange:#d97706;--blue:#5361b8;--dark:#15204a}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif;font-size:14px}.layout{display:grid;grid-template-columns:270px 1fr;min-height:100vh}.side{background:var(--nav);color:#fff;padding:22px 18px}.side h2{font-size:18px;margin:0 0 14px}.side p{line-height:1.4;margin:0 0 20px;color:#e5eaff}.side a{display:block;color:#fff;text-decoration:none;padding:10px;border-radius:8px;margin:4px 0}.side a.active,.side a:hover{background:rgba(255,255,255,.16)}.main{padding:24px}.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:16px;box-shadow:0 4px 14px rgba(0,0,0,.04)}.hero{border-left:5px solid <?= $liveRisk ? 'var(--red)' : 'var(--orange)' ?>}h1{font-size:30px;margin:8px 0 8px}h3{margin-top:0}.sub{color:var(--muted);line-height:1.45}.badge{display:inline-block;font-weight:700;border-radius:999px;padding:6px 10px;font-size:12px;margin:2px 4px 2px 0}.good{background:#dff6e6;color:#086b25}.bad{background:#ffe3e1;color:#a4161a}.warn{background:#fff1cc;color:#8a5100}.neutral{background:#edf2ff;color:#293b8f}.dark{background:#17244d;color:#fff}.blue{background:#e4e8ff;color:#263aa1}.btn{display:inline-block;border-radius:9px;padding:10px 13px;background:var(--blue);color:#fff;text-decoration:none;font-weight:700;margin:8px 8px 0 0}.btn.green{background:var(--green)}.btn.dark{background:var(--dark)}.btn.warn{background:var(--orange)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:12px}.metric .num{font-size:28px;font-weight:800}.metric .label{color:var(--muted);font-size:12px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);text-align:left;padding:10px;vertical-align:top}.table th{background:#f5f7fb;color:#43547d}.table td.smallcell{font-size:12px}.blocks{border-color:#ffc879;background:#fff8ed}.danger{border-color:#ffb4b4;background:#fff7f7}.okbox{border-color:#bce8c8;background:#f6fff8}.small{font-size:12px;color:var(--muted)}code,.mono{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;word-break:break-word}.list{margin:0;padding-left:18px}.nowrap{white-space:nowrap}.scroll{overflow:auto}.rowlink{white-space:nowrap}.muted{color:var(--muted)}@media(max-width:1000px){.layout{grid-template-columns:1fr}.side{position:relative}.grid,.grid2{grid-template-columns:1fr}.main{padding:14px}.table{font-size:13px}}
</style>
</head>
<body>
<div class="layout">
  <aside class="side">
    <h2>V3 Automation</h2>
    <p>Operator console. Read-only. Live submission remains blocked unless Andreas explicitly opens the separate live gate later.</p>
    <a class="active" href="/ops/pre-ride-email-v3-live-operator-console.php">Live Operator Console</a>
    <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php<?= isset($selectedRow['id']) ? '?queue_id=' . rawurlencode((string)$selectedRow['id']) : '' ?>">Pre-Live Switchboard</a>
    <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
    <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
    <a href="/ops/pre-ride-email-v3-operator-approval-workflow.php">Operator Approval</a>
    <a href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Gate Drift Guard</a>
    <a href="/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php">Kill-Switch Check</a>
  </aside>

  <main class="main">
    <section class="card hero">
      <div>
        <?= $gateClosed ? v3oc_badge('EXPECTED CLOSED PRE-LIVE GATE', 'good') : v3oc_badge('GATE NEEDS REVIEW', 'warn') ?>
        <?= $liveRisk ? v3oc_badge('LIVE RISK DETECTED', 'bad') : v3oc_badge('NO LIVE RISK DETECTED', 'good') ?>
        <?= v3oc_badge('NO EDXEIX CALL', 'dark') ?>
        <?= v3oc_badge('NO DB WRITES', 'dark') ?>
      </div>
      <h1>V3 Live Operator Console</h1>
      <div class="sub"><?= v3oc_h($report['safety'] ?? '') ?></div>
      <a class="btn green" href="/ops/pre-ride-email-v3-live-operator-console.php<?= isset($selectedRow['id']) ? '?queue_id=' . rawurlencode((string)$selectedRow['id']) : '' ?>">Refresh console</a>
      <a class="btn" href="/ops/pre-ride-email-v3-live-operator-console.php?json=1<?= isset($selectedRow['id']) ? '&queue_id=' . rawurlencode((string)$selectedRow['id']) : '' ?>">JSON view</a>
      <a class="btn dark" href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Open drift guard</a>
    </section>

    <?php if ($events !== []): ?>
      <section class="card danger">
        <h3>Events</h3>
        <ul class="list"><?php foreach ($events as $event): ?><li><?= v3oc_h(($event['level'] ?? 'event') . ': ' . ($event['message'] ?? '')) ?></li><?php endforeach; ?></ul>
      </section>
    <?php endif; ?>

    <section class="grid">
      <div class="card metric">
        <div class="num"><?= v3oc_h((string)($metrics['live_submit_ready'] ?? 0)) ?></div>
        <div class="label">live_submit_ready</div>
        <div class="small">future active: <?= v3oc_h($metrics['future_active'] ?? 0) ?> / active: <?= v3oc_h($metrics['active'] ?? 0) ?></div>
      </div>
      <div class="card metric">
        <div class="num"><?= v3oc_h(($gate['mode'] ?? '-') . ' / ' . ($gate['adapter'] ?? '-')) ?></div>
        <div class="label">gate mode / adapter</div>
        <div class="small">enabled=<?= v3oc_h(v3oc_yn($gate['enabled'] ?? false)) ?> hard=<?= v3oc_h(v3oc_yn($gate['hard_enable_live_submit'] ?? false)) ?> ack=<?= v3oc_h(v3oc_yn($gate['acknowledgement_phrase_present'] ?? false)) ?></div>
      </div>
      <div class="card metric">
        <div class="num"><?= v3oc_h((string)($proofBundles['summary_json_count'] ?? 0)) ?></div>
        <div class="label">proof bundles</div>
        <div class="small">latest proof exports found locally</div>
      </div>
      <div class="card metric">
        <div class="num"><?= v3oc_h((string)($liveArtifacts['total_edxeix_fields'] ?? 0)) ?></div>
        <div class="label">live package field exports</div>
        <div class="small">local JSON field packages only</div>
      </div>
    </section>

    <section class="card <?= $liveRisk ? 'danger' : 'okbox' ?>">
      <h3>Gate posture</h3>
      <table class="table">
        <tr><th>Config path</th><td class="mono"><?= v3oc_h($gate['path'] ?? '') ?></td></tr>
        <tr><th>Loaded</th><td><?= v3oc_h(v3oc_yn($gate['loaded'] ?? false)) ?> · returned_array=<?= v3oc_h(v3oc_yn($gate['returned_array'] ?? false)) ?></td></tr>
        <tr><th>Expected closed pre-live posture</th><td><?= !empty($gate['expected_closed_pre_live']) ? v3oc_badge('yes', 'good') : v3oc_badge('no', 'warn') ?></td></tr>
        <tr><th>Live risk detected</th><td><?= !empty($gate['live_risk_detected']) ? v3oc_badge('yes', 'bad') : v3oc_badge('no', 'good') ?></td></tr>
        <tr><th>Master gate blockers</th><td><?php foreach ((array)($gate['blocks'] ?? []) as $block): ?><?= v3oc_badge((string)$block, 'bad') ?><?php endforeach; ?></td></tr>
      </table>
    </section>

    <section class="card">
      <h3>Current V3 queue rows</h3>
      <div class="scroll">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Status</th>
              <th>Transfer</th>
              <th>Pickup</th>
              <th>IDs</th>
              <th>Proof status</th>
              <th>Artifacts</th>
              <th>Read-only links</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="8">No V3 queue rows found.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $entry):
                $row = is_array($entry['row'] ?? null) ? $entry['row'] : [];
                $st = is_array($entry['status'] ?? null) ? $entry['status'] : [];
                $approval = is_array($st['approval'] ?? null) ? $st['approval'] : [];
                $payload = is_array($st['payload'] ?? null) ? $st['payload'] : [];
                $start = is_array($st['starting_point'] ?? null) ? $st['starting_point'] : [];
                $pkg = is_array($st['package_artifacts'] ?? null) ? $st['package_artifacts'] : [];
                $rid = (string)($row['id'] ?? '');
            ?>
              <tr>
                <td class="mono">#<?= v3oc_h($rid) ?></td>
                <td><?= v3oc_badge((string)($row['queue_status'] ?? '-'), (string)($row['queue_status'] ?? '') === 'live_submit_ready' ? 'good' : 'neutral') ?></td>
                <td>
                  <strong><?= v3oc_h($row['customer_name'] ?? '') ?></strong><br>
                  <span class="small"><?= v3oc_h($row['driver_name'] ?? '') ?> · <?= v3oc_h($row['vehicle_plate'] ?? '') ?></span>
                </td>
                <td>
                  <?= v3oc_h($row['pickup_datetime'] ?? '') ?><br>
                  <span class="small">minutes_until=<?= v3oc_h($row['minutes_until_now'] ?? '-') ?></span>
                </td>
                <td class="smallcell mono">
                  lessor=<?= v3oc_h($row['lessor_id'] ?? '') ?><br>
                  driver=<?= v3oc_h($row['driver_id'] ?? '') ?><br>
                  vehicle=<?= v3oc_h($row['vehicle_id'] ?? '') ?><br>
                  start=<?= v3oc_h($row['starting_point_id'] ?? '') ?>
                </td>
                <td>
                  <?= !empty($payload['complete']) ? v3oc_badge('payload complete', 'good') : v3oc_badge('payload missing', 'bad') ?>
                  <?= !empty($start['ok']) ? v3oc_badge('start verified', 'good') : v3oc_badge('start not verified', 'bad') ?>
                  <?= !empty($approval['valid']) ? v3oc_badge('approval valid', 'good') : v3oc_badge('approval missing/expired', 'bad') ?>
                  <?= !empty($st['ready_for_closed_gate_proof']) ? v3oc_badge('closed-gate proof ready', 'blue') : v3oc_badge('proof blocked', 'neutral') ?>
                </td>
                <td><?= v3oc_h($pkg['queue_count'] ?? 0) ?></td>
                <td class="rowlink">
                  <a href="?queue_id=<?= rawurlencode($rid) ?>">console</a><br>
                  <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php?queue_id=<?= rawurlencode($rid) ?>">switchboard</a><br>
                  <a href="/ops/pre-ride-email-v3-live-package-export.php?queue_id=<?= rawurlencode($rid) ?>">package export</a><br>
                  <a href="/ops/pre-ride-email-v3-adapter-row-simulation.php?queue_id=<?= rawurlencode($rid) ?>">adapter simulation</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="grid2">
      <div class="card">
        <h3>Selected row payload</h3>
        <?php if ($selectedRow === []): ?>
          <p>No selected row.</p>
        <?php else:
          $sp = is_array($selectedStatus['payload'] ?? null) ? $selectedStatus['payload'] : [];
          $edx = is_array($sp['edxeix_payload'] ?? null) ? $sp['edxeix_payload'] : [];
        ?>
          <table class="table">
            <tr><th>Queue ID</th><td class="mono">#<?= v3oc_h($selectedRow['id'] ?? '') ?></td></tr>
            <tr><th>Order reference</th><td class="mono"><?= v3oc_h($selectedRow['order_reference'] ?? '') ?></td></tr>
            <tr><th>Hash</th><td class="mono smallcell"><?= v3oc_h($sp['hash_sha256'] ?? '') ?></td></tr>
            <?php foreach ($edx as $key => $value): ?>
              <tr><th><?= v3oc_h($key) ?></th><td><?= v3oc_h($value) ?></td></tr>
            <?php endforeach; ?>
          </table>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>Selected row latest artifacts</h3>
        <p class="small mono"><?= v3oc_h($liveArtifacts['dir'] ?? '') ?></p>
        <?php $latest = is_array($liveArtifacts['latest'] ?? null) ? $liveArtifacts['latest'] : []; ?>
        <?php if ($latest === []): ?>
          <p>No local package artifacts found for the selected row.</p>
        <?php else: ?>
          <ul class="list">
            <?php foreach ($latest as $file): ?>
              <li><span class="mono"><?= v3oc_h($file['name'] ?? '') ?></span> <span class="small">size=<?= v3oc_h($file['size'] ?? 0) ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </section>

    <section class="grid2">
      <div class="card">
        <h3>Proof bundles</h3>
        <p class="small mono"><?= v3oc_h($proofBundles['dir'] ?? '') ?></p>
        <?php $proofLatest = is_array($proofBundles['latest'] ?? null) ? $proofBundles['latest'] : []; ?>
        <?php if ($proofLatest === []): ?>
          <p>No proof bundles found yet.</p>
        <?php else: ?>
          <ul class="list">
            <?php foreach ($proofLatest as $file): ?>
              <li><span class="mono"><?= v3oc_h($file['name'] ?? '') ?></span> <span class="small">size=<?= v3oc_h($file['size'] ?? 0) ?></span></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <div class="card">
        <h3>Adapter file scan</h3>
        <table class="table">
          <thead><tr><th>Adapter</th><th>Exists</th><th>Network-aware</th><th>Live capable true</th><th>Skeleton</th></tr></thead>
          <tbody>
          <?php foreach ($adapterFiles as $key => $scan): ?>
            <tr>
              <td class="mono"><?= v3oc_h($key) ?></td>
              <td><?= !empty($scan['exists']) ? v3oc_badge('yes', 'good') : v3oc_badge('no', 'bad') ?></td>
              <td><?= !empty($scan['looks_network_aware']) ? v3oc_badge('yes', 'warn') : v3oc_badge('no', 'good') ?></td>
              <td><?= !empty($scan['contains_live_capable_true']) ? v3oc_badge('yes', 'warn') : v3oc_badge('no', 'good') ?></td>
              <td><?= !empty($scan['contains_skeleton_not_implemented']) ? v3oc_badge('yes', 'good') : v3oc_badge('no', 'neutral') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="card blocks">
      <h3>Console final blocks</h3>
      <?php if ($blocks === []): ?>
        <p><?= v3oc_badge('no blocks', 'good') ?> Console has no internal blocks. This page is still read-only and does not submit.</p>
      <?php else: ?>
        <ul class="list"><?php foreach ($blocks as $block): ?><li><?= v3oc_h($block) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3>Operator note</h3>
      <p class="sub">This console is intentionally a visibility layer only. It confirms queue readiness, approval visibility, payload package presence, proof bundle history, and master gate closure. It does not approve, revoke, submit, or mutate rows.</p>
      <p class="small">Version: <?= v3oc_h(V3OC_VERSION) ?> · Finished: <?= v3oc_h($report['finished_at'] ?? '') ?></p>
    </section>
  </main>
</div>
</body>
</html>
