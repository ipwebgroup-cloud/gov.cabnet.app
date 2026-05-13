<?php
declare(strict_types=1);

/**
 * gov.cabnet.app — V3 live-submit final rehearsal CLI
 *
 * Purpose:
 * - Read live_submit_ready V3 rows.
 * - Rehearse the final pre-submit chain: master gate visibility, operator approval,
 *   verified starting point, required payload fields, future/time/price checks.
 * - Build the final no-submit adapter handoff package for inspection.
 *
 * Safety:
 * - No EDXEIX call.
 * - No AADE call.
 * - No DB writes.
 * - No production submission_jobs/submission_attempts writes.
 */

const PRV3_REHEARSAL_VERSION = 'v3.0.29-live-submit-final-rehearsal';
const PRV3_REHEARSAL_DEFAULT_STATUS = 'live_submit_ready';

function prv3_rehearsal_options(array $argv): array
{
    $opts = [
        'limit' => 20,
        'status' => PRV3_REHEARSAL_DEFAULT_STATUS,
        'json' => false,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
            $opts['limit'] = max(1, min(200, (int)$m[1]));
        } elseif (preg_match('/^--status=([a-zA-Z0-9_\-]+)$/', $arg, $m)) {
            $opts['status'] = $m[1];
        } else {
            fwrite(STDERR, "Unknown option: {$arg}\n");
            $opts['help'] = true;
        }
    }

    return $opts;
}

function prv3_rehearsal_help(): string
{
    return <<<TEXT
V3 live-submit final rehearsal CLI

Usage:
  php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_live_submit_rehearsal.php [options]

Options:
  --limit=N       Rows to inspect. Default 20, max 200.
  --status=NAME   Queue status to inspect. Default live_submit_ready.
  --json          Output JSON.
  --help          Show this help.

Safety:
  This tool does not submit to EDXEIX, does not call AADE, and does not write to any DB table.

TEXT;
}

function prv3_rehearsal_app_root(): string
{
    return dirname(__DIR__);
}

function prv3_rehearsal_config_path(): string
{
    return dirname(__DIR__, 2) . '/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';
}

function prv3_rehearsal_bootstrap(): array
{
    $bootstrap = prv3_rehearsal_app_root() . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Missing bootstrap: ' . $bootstrap);
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Bootstrap did not return usable DB context.');
    }
    return $ctx;
}

function prv3_rehearsal_db(): mysqli
{
    $ctx = prv3_rehearsal_bootstrap();
    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('DB connection is not mysqli.');
    }
    return $db;
}

function prv3_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0) > 0;
}

/** @return array<string,bool> */
function prv3_table_columns(mysqli $db, string $table): array
{
    $out = [];
    $stmt = $db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    if (!$stmt) {
        return $out;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $name = (string)($row['COLUMN_NAME'] ?? '');
        if ($name !== '') {
            $out[$name] = true;
        }
    }
    return $out;
}

/** @return array<string,mixed> */
function prv3_schema(mysqli $db): array
{
    $tables = [
        'pre_ride_email_v3_queue',
        'pre_ride_email_v3_queue_events',
        'pre_ride_email_v3_starting_point_options',
        'pre_ride_email_v3_live_submit_approvals',
    ];
    $out = [];
    foreach ($tables as $table) {
        $out[$table] = prv3_table_exists($db, $table);
    }
    return $out;
}

/** @return array<string,mixed> */
function prv3_master_gate_snapshot(): array
{
    $path = prv3_rehearsal_config_path();
    $loaded = false;
    $config = [];
    $error = '';

    if (is_file($path)) {
        try {
            $loadedConfig = require $path;
            if (is_array($loadedConfig)) {
                $config = $loadedConfig;
                $loaded = true;
            } else {
                $error = 'Config did not return an array.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = 'Config file not found.';
    }

    $enabled = filter_var($config['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $mode = strtolower(trim((string)($config['mode'] ?? 'disabled')));
    $adapter = strtolower(trim((string)($config['adapter'] ?? 'disabled')));
    $ack = trim((string)($config['acknowledgement'] ?? $config['acknowledgement_phrase'] ?? ''));
    $minFuture = (int)($config['min_future_minutes'] ?? 1);
    $hard = filter_var($config['hard_enable_live_submit'] ?? false, FILTER_VALIDATE_BOOLEAN);

    $blocks = [];
    if (!$loaded) {
        $blocks[] = 'server live-submit config is missing or invalid';
    }
    if (!$enabled) {
        $blocks[] = 'enabled is false';
    }
    if ($mode !== 'live') {
        $blocks[] = 'mode is not live';
    }
    if ($ack === '') {
        $blocks[] = 'required acknowledgement phrase is not present';
    }
    if ($adapter === '' || $adapter === 'disabled') {
        $blocks[] = 'adapter is disabled';
    }
    if (!$hard) {
        $blocks[] = 'hard_enable_live_submit is false';
    }

    return [
        'config_path' => $loaded ? $path : '',
        'config_loaded' => $loaded,
        'config_error' => $error,
        'enabled' => $enabled,
        'mode' => $mode,
        'adapter' => $adapter === '' ? 'disabled' : $adapter,
        'acknowledgement_present' => $ack !== '',
        'min_future_minutes' => max(1, $minFuture),
        'hard_enable_live_submit' => $hard,
        'ok' => $blocks === [],
        'blocks' => $blocks,
        'allowed_lessors' => is_array($config['allowed_lessors'] ?? null) ? array_values(array_map('strval', $config['allowed_lessors'])) : [],
    ];
}

function prv3_fetch_rows(mysqli $db, string $status, int $limit): array
{
    if (!prv3_table_exists($db, 'pre_ride_email_v3_queue')) {
        return [];
    }

    $sql = "SELECT * FROM pre_ride_email_v3_queue WHERE queue_status = ? ORDER BY pickup_datetime ASC, id ASC LIMIT ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Queue select prepare failed: ' . $db->error);
    }
    $stmt->bind_param('si', $status, $limit);
    $stmt->execute();

    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function prv3_is_verified_start(mysqli $db, string $lessorId, string $startId): array
{
    if ($lessorId === '' || $startId === '') {
        return ['ok' => false, 'reason' => 'lessor_id or starting_point_id missing', 'label' => ''];
    }
    if (!prv3_table_exists($db, 'pre_ride_email_v3_starting_point_options')) {
        return ['ok' => false, 'reason' => 'verified starting-point options table missing', 'label' => ''];
    }

    $stmt = $db->prepare("SELECT label FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1");
    if (!$stmt) {
        return ['ok' => false, 'reason' => 'starting-point verify prepare failed: ' . $db->error, 'label' => ''];
    }
    $stmt->bind_param('ss', $lessorId, $startId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (!is_array($row)) {
        return ['ok' => false, 'reason' => 'starting point is not operator-verified for this lessor', 'label' => ''];
    }
    return ['ok' => true, 'reason' => '', 'label' => (string)($row['label'] ?? '')];
}

function prv3_has_valid_approval(mysqli $db, string $queueId, string $dedupeKey): array
{
    if (!prv3_table_exists($db, 'pre_ride_email_v3_live_submit_approvals')) {
        return ['ok' => false, 'reason' => 'approval table missing', 'approval_id' => null];
    }

    $columns = prv3_table_columns($db, 'pre_ride_email_v3_live_submit_approvals');
    $where = [];
    $params = [];
    $types = '';

    if (isset($columns['queue_id'])) {
        $where[] = 'queue_id = ?';
        $params[] = $queueId;
        $types .= 's';
    }
    if (isset($columns['dedupe_key']) && $dedupeKey !== '') {
        $where[] = 'dedupe_key = ?';
        $params[] = $dedupeKey;
        $types .= 's';
    }
    if ($where === []) {
        return ['ok' => false, 'reason' => 'approval table has no queue_id/dedupe_key column', 'approval_id' => null];
    }

    $statusSql = '';
    if (isset($columns['approval_status'])) {
        $statusSql = " AND approval_status IN ('approved','valid','active')";
    } elseif (isset($columns['status'])) {
        $statusSql = " AND status IN ('approved','valid','active')";
    }

    $expirySql = '';
    if (isset($columns['expires_at'])) {
        $expirySql = " AND (expires_at IS NULL OR expires_at >= NOW())";
    }

    $approvedSql = '';
    if (isset($columns['approved_at'])) {
        $approvedSql = " AND approved_at IS NOT NULL";
    }

    $idCol = isset($columns['id']) ? 'id' : 'queue_id';
    $order = isset($columns['created_at']) ? 'created_at DESC' : $idCol . ' DESC';
    $sql = 'SELECT ' . $idCol . ' AS approval_id FROM pre_ride_email_v3_live_submit_approvals WHERE (' . implode(' OR ', $where) . ')' . $statusSql . $expirySql . $approvedSql . ' ORDER BY ' . $order . ' LIMIT 1';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'reason' => 'approval select prepare failed: ' . $db->error, 'approval_id' => null];
    }

    $refs = [];
    $refs[] = &$types;
    foreach ($params as $k => &$v) {
        $refs[] = &$v;
    }
    $stmt->bind_param(...$refs);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!is_array($row)) {
        return ['ok' => false, 'reason' => 'no valid operator approval found', 'approval_id' => null];
    }

    return ['ok' => true, 'reason' => '', 'approval_id' => (string)($row['approval_id'] ?? '')];
}

function prv3_payload_from_row(array $row): array
{
    $payloadJson = trim((string)($row['payload_json'] ?? ''));
    $payload = [];
    if ($payloadJson !== '') {
        $decoded = json_decode($payloadJson, true);
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    }

    return [
        'queue_id' => (string)($row['id'] ?? ''),
        'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
        'lessor' => (string)($row['lessor_id'] ?? ($payload['lessorId'] ?? '')),
        'driver' => (string)($row['driver_id'] ?? ($payload['driverId'] ?? '')),
        'vehicle' => (string)($row['vehicle_id'] ?? ($payload['vehicleId'] ?? '')),
        'starting_point_id' => (string)($row['starting_point_id'] ?? ($payload['startingPointId'] ?? '')),
        'lessee_type' => 'natural_person',
        'lessee_name' => (string)($row['customer_name'] ?? ($payload['passengerName'] ?? '')),
        'boarding_point' => (string)($row['pickup_address'] ?? ($payload['pickupAddress'] ?? '')),
        'disembark_point' => (string)($row['dropoff_address'] ?? ($payload['dropoffAddress'] ?? '')),
        'drafted_at' => (string)($row['pickup_datetime'] ?? ($payload['pickupDateTime'] ?? '')),
        'started_at' => (string)($row['pickup_datetime'] ?? ($payload['pickupDateTime'] ?? '')),
        'ended_at' => (string)($row['estimated_end_datetime'] ?? ($payload['endDateTime'] ?? '')),
        'price' => (string)($row['price_amount'] ?? ($payload['priceAmount'] ?? '')),
        'source_status' => (string)($row['queue_status'] ?? ''),
    ];
}

function prv3_validate_row(mysqli $db, array $row, array $gate): array
{
    $payload = prv3_payload_from_row($row);
    $blocks = [];
    $warnings = [];

    if (empty($gate['ok'])) {
        foreach ((array)($gate['blocks'] ?? []) as $block) {
            $blocks[] = 'master_gate: ' . (string)$block;
        }
    }

    $lessorId = (string)$payload['lessor'];
    $startId = (string)$payload['starting_point_id'];
    $allowedLessors = (array)($gate['allowed_lessors'] ?? []);
    if ($allowedLessors !== [] && !in_array($lessorId, $allowedLessors, true)) {
        $blocks[] = 'lessor is not allowed by live-submit config';
    }

    $approval = prv3_has_valid_approval($db, (string)$payload['queue_id'], (string)$payload['dedupe_key']);
    if (empty($approval['ok'])) {
        $blocks[] = 'approval: ' . (string)($approval['reason'] ?? 'missing');
    }

    $start = prv3_is_verified_start($db, $lessorId, $startId);
    if (empty($start['ok'])) {
        $blocks[] = 'starting_point: ' . (string)($start['reason'] ?? 'not verified');
    }

    foreach (['lessor','driver','vehicle','starting_point_id','lessee_name','boarding_point','disembark_point','started_at','ended_at','price'] as $key) {
        if (trim((string)($payload[$key] ?? '')) === '') {
            $blocks[] = 'missing required payload field: ' . $key;
        }
    }

    $price = trim((string)($payload['price'] ?? ''));
    if ($price !== '' && (!is_numeric($price) || (float)$price <= 0)) {
        $blocks[] = 'price is not positive';
    }

    $minFuture = max(1, (int)($gate['min_future_minutes'] ?? 1));
    $startedAt = trim((string)($payload['started_at'] ?? ''));
    if ($startedAt !== '') {
        try {
            $tz = new DateTimeZone('Europe/Athens');
            $pickup = new DateTimeImmutable($startedAt, $tz);
            $now = new DateTimeImmutable('now', $tz);
            $minutes = (int)floor(($pickup->getTimestamp() - $now->getTimestamp()) / 60);
            if ($minutes < $minFuture) {
                $blocks[] = 'pickup is only ' . $minutes . ' minutes from now; required minimum is ' . $minFuture;
            }
        } catch (Throwable $e) {
            $blocks[] = 'pickup datetime parse failed: ' . $e->getMessage();
        }
    }

    $endAt = trim((string)($payload['ended_at'] ?? ''));
    if ($startedAt !== '' && $endAt !== '') {
        try {
            $tz = new DateTimeZone('Europe/Athens');
            $startDt = new DateTimeImmutable($startedAt, $tz);
            $endDt = new DateTimeImmutable($endAt, $tz);
            if ($endDt <= $startDt) {
                $blocks[] = 'ended_at is not after started_at';
            }
        } catch (Throwable $e) {
            $warnings[] = 'end datetime parse warning: ' . $e->getMessage();
        }
    }

    return [
        'queue_id' => (string)($payload['queue_id'] ?? ''),
        'dedupe_key' => (string)($payload['dedupe_key'] ?? ''),
        'pre_live_ok' => $blocks === [],
        'blocks' => $blocks,
        'warnings' => $warnings,
        'approval' => $approval,
        'verified_start' => $start,
        'adapter_package' => $payload,
    ];
}

function prv3_rehearsal_run(array $argv): array
{
    $opts = prv3_rehearsal_options($argv);
    if (!empty($opts['help'])) {
        return ['help' => true, 'text' => prv3_rehearsal_help()];
    }

    $db = prv3_rehearsal_db();
    $schema = prv3_schema($db);
    $schemaOk = !in_array(false, [
        $schema['pre_ride_email_v3_queue'] ?? false,
        $schema['pre_ride_email_v3_starting_point_options'] ?? false,
        $schema['pre_ride_email_v3_live_submit_approvals'] ?? false,
    ], true);

    $gate = prv3_master_gate_snapshot();
    $rows = $schemaOk ? prv3_fetch_rows($db, (string)$opts['status'], (int)$opts['limit']) : [];
    $checks = [];
    foreach ($rows as $row) {
        $checks[] = prv3_validate_row($db, $row, $gate);
    }

    return [
        'help' => false,
        'summary' => [
            'ok' => true,
            'version' => PRV3_REHEARSAL_VERSION,
            'mode' => 'dry_run_no_submit_no_write',
            'database' => (string)($db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? ''),
            'status_filter' => (string)$opts['status'],
            'limit' => (int)$opts['limit'],
            'schema_ok' => $schemaOk,
            'schema' => $schema,
            'master_gate_ok' => !empty($gate['ok']),
            'config_loaded' => !empty($gate['config_loaded']),
            'adapter' => (string)($gate['adapter'] ?? 'disabled'),
            'live_hard_enabled' => !empty($gate['hard_enable_live_submit']),
            'rows_checked' => count($checks),
            'pre_live_passed' => count(array_filter($checks, static fn(array $r): bool => !empty($r['pre_live_ok']))),
            'blocked' => count(array_filter($checks, static fn(array $r): bool => empty($r['pre_live_ok']))),
            'safety' => [
                'edxeix_call' => false,
                'aade_call' => false,
                'db_writes' => false,
                'production_submission_jobs' => false,
                'production_submission_attempts' => false,
            ],
        ],
        'gate' => $gate,
        'rows' => $checks,
    ];
}

function prv3_rehearsal_print_text(array $result): void
{
    if (!empty($result['help'])) {
        echo (string)$result['text'];
        return;
    }

    $s = (array)($result['summary'] ?? []);
    echo "V3 live-submit final rehearsal " . ($s['version'] ?? PRV3_REHEARSAL_VERSION) . "\n";
    echo "Mode: " . ($s['mode'] ?? 'dry_run_no_submit_no_write') . "\n";
    echo "Database: " . ($s['database'] ?? '-') . "\n";
    echo "Schema OK: " . (!empty($s['schema_ok']) ? 'yes' : 'no') . " | Approval table: " . (!empty($s['schema']['pre_ride_email_v3_live_submit_approvals']) ? 'yes' : 'no') . " | Start options: " . (!empty($s['schema']['pre_ride_email_v3_starting_point_options']) ? 'yes' : 'no') . "\n";
    echo "Master gate OK: " . (!empty($s['master_gate_ok']) ? 'yes' : 'no') . " | config_loaded=" . (!empty($s['config_loaded']) ? 'yes' : 'no') . " | adapter=" . ($s['adapter'] ?? 'disabled') . " | hard_enabled=" . (!empty($s['live_hard_enabled']) ? 'yes' : 'no') . "\n";
    echo "Rows checked: " . ($s['rows_checked'] ?? 0) . " | Pre-live passed: " . ($s['pre_live_passed'] ?? 0) . " | Blocked: " . ($s['blocked'] ?? 0) . "\n";
    echo "No EDXEIX call. No AADE call. No DB writes. No production submission tables.\n";

    $gate = (array)($result['gate'] ?? []);
    foreach ((array)($gate['blocks'] ?? []) as $block) {
        echo "Gate block: " . $block . "\n";
    }

    $rows = (array)($result['rows'] ?? []);
    if ($rows === []) {
        echo "No V3 queue rows matched status filter: " . ($s['status_filter'] ?? PRV3_REHEARSAL_DEFAULT_STATUS) . "\n";
        return;
    }

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        echo "\n#" . ($row['queue_id'] ?? '-') . " " . (!empty($row['pre_live_ok']) ? 'PRE-LIVE-PASS' : 'BLOCKED') . " " . ($row['dedupe_key'] ?? '') . "\n";
        $pkg = (array)($row['adapter_package'] ?? []);
        echo "  Transfer: " . ($pkg['lessee_name'] ?? '-') . " | driver=" . ($pkg['driver'] ?? '-') . " | vehicle=" . ($pkg['vehicle'] ?? '-') . " | start=" . ($pkg['starting_point_id'] ?? '-') . "\n";
        foreach ((array)($row['blocks'] ?? []) as $block) {
            echo "  Block: " . $block . "\n";
        }
    }
}

try {
    $result = prv3_rehearsal_run($argv);
    if (!empty($result['help'])) {
        prv3_rehearsal_print_text($result);
        exit(0);
    }
    if (!empty($result['summary']['ok'])) {
        if (!empty($result['summary']['schema_ok']) || !empty($result['summary']['rows_checked'])) {
            // OK.
        }
    }
    if (!empty(prv3_rehearsal_options($argv)['json'])) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        prv3_rehearsal_print_text($result);
    }
    exit(0);
} catch (Throwable $e) {
    $err = [
        'ok' => false,
        'version' => PRV3_REHEARSAL_VERSION,
        'error' => $e->getMessage(),
        'safety' => [
            'edxeix_call' => false,
            'aade_call' => false,
            'db_writes' => false,
        ],
    ];
    if (in_array('--json', $argv, true)) {
        echo json_encode($err, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    }
    exit(1);
}
