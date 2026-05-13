<?php
/**
 * gov.cabnet.app — V3 starting-point guard.
 *
 * Purpose:
 * - Validate active V3 queue rows against operator-verified EDXEIX starting-point options per lessor.
 * - Block only V3 queue rows whose starting_point_id is known-invalid for their lessor.
 *
 * Safety:
 * - Default mode is dry-run SELECT-only.
 * - --commit writes only to pre_ride_email_v3_queue and pre_ride_email_v3_queue_events.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs/submission_attempts access.
 */

declare(strict_types=1);

const SPG_VERSION = 'v3.0.18-starting-point-guard';
const SPG_DEFAULT_LIMIT = 50;

function spg_arg_value(array $argv, string $name, string $default): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}

function spg_has_flag(array $argv, string $flag): bool
{
    return in_array($flag, $argv, true);
}

function spg_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<string,mixed> */
function spg_fetch_option_status(mysqli $db, string $lessorId, string $startingPointId): array
{
    $lessorId = trim($lessorId);
    $startingPointId = trim($startingPointId);
    if ($lessorId === '') {
        return ['known_lessor' => false, 'valid' => false, 'reason' => 'missing lessor_id', 'options' => []];
    }

    $stmt = $db->prepare('SELECT edxeix_starting_point_id, label, is_active FROM pre_ride_email_v3_starting_point_options WHERE edxeix_lessor_id = ? ORDER BY is_active DESC, label ASC, id ASC');
    if (!$stmt) {
        return ['known_lessor' => false, 'valid' => false, 'reason' => 'could not prepare option lookup', 'options' => []];
    }
    $stmt->bind_param('s', $lessorId);
    $stmt->execute();
    $res = $stmt->get_result();
    $options = [];
    $valid = false;
    while ($row = $res->fetch_assoc()) {
        $optId = trim((string)($row['edxeix_starting_point_id'] ?? ''));
        $active = (int)($row['is_active'] ?? 0) === 1;
        $options[] = [
            'id' => $optId,
            'label' => (string)($row['label'] ?? ''),
            'active' => $active,
        ];
        if ($active && $startingPointId !== '' && $optId === $startingPointId) {
            $valid = true;
        }
    }

    if (!$options) {
        return ['known_lessor' => false, 'valid' => false, 'reason' => 'no verified starting-point options for lessor ' . $lessorId, 'options' => []];
    }
    if ($startingPointId === '') {
        return ['known_lessor' => true, 'valid' => false, 'reason' => 'missing starting_point_id', 'options' => $options];
    }
    if (!$valid) {
        return ['known_lessor' => true, 'valid' => false, 'reason' => 'starting_point_id ' . $startingPointId . ' is not verified for lessor ' . $lessorId, 'options' => $options];
    }
    return ['known_lessor' => true, 'valid' => true, 'reason' => '', 'options' => $options];
}

function spg_insert_event(mysqli $db, int $queueId, string $dedupeKey, string $type, string $status, string $message, array $context): bool
{
    $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $createdBy = 'v3_starting_point_guard';
    $stmt = $db->prepare('INSERT INTO pre_ride_email_v3_queue_events (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('issssss', $queueId, $dedupeKey, $type, $status, $message, $contextJson, $createdBy);
    return $stmt->execute();
}

function spg_block_row(mysqli $db, array $row, string $reason, array $context): bool
{
    $id = (int)($row['id'] ?? 0);
    $dedupeKey = (string)($row['dedupe_key'] ?? '');
    if ($id <= 0 || $dedupeKey === '') {
        return false;
    }

    $stmt = $db->prepare("UPDATE pre_ride_email_v3_queue SET queue_status = 'blocked', failed_at = NOW(), last_error = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('si', $reason, $id);
    $ok = $stmt->execute();
    spg_insert_event($db, $id, $dedupeKey, 'starting_point_guard_blocked', $ok ? 'blocked' : 'failed', $reason, $context);
    return $ok;
}

$help = spg_has_flag($argv, '--help') || spg_has_flag($argv, '-h');
$json = spg_has_flag($argv, '--json');
$commit = spg_has_flag($argv, '--commit');
$limit = (int)spg_arg_value($argv, '--limit', (string)SPG_DEFAULT_LIMIT);
if ($limit < 1 || $limit > 500) {
    $limit = SPG_DEFAULT_LIMIT;
}
$statusFilter = trim(spg_arg_value($argv, '--status', 'queued,submit_dry_run_ready,ready'));

if ($help) {
    echo "V3 starting-point guard " . SPG_VERSION . "\n";
    echo "Usage: php pre_ride_email_v3_starting_point_guard.php [--limit=50] [--status=queued,submit_dry_run_ready,ready] [--commit] [--json]\n";
    exit(0);
}

$summary = [
    'ok' => false,
    'version' => SPG_VERSION,
    'mode' => $commit ? 'commit_v3_guard_only' : 'dry_run_select_only',
    'started_at' => date(DATE_ATOM),
    'limit' => $limit,
    'status_filter' => $statusFilter,
    'database' => '',
    'schema_ok' => false,
    'rows_checked' => 0,
    'known_lessor_count' => 0,
    'valid_count' => 0,
    'unknown_lessor_count' => 0,
    'invalid_count' => 0,
    'blocked_count' => 0,
    'event_count' => 0,
    'error_count' => 0,
    'safety' => [
        'default_select_only' => !$commit,
        'v3_tables_only' => true,
        'edxeix_server_call' => false,
        'aade_call' => false,
        'production_submission_jobs' => false,
        'production_submission_attempts' => false,
    ],
];
$rowsOut = [];
$error = '';

try {
    $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Bootstrap not found: ' . $bootstrap);
    }
    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Bootstrap did not return a usable DB context.');
    }
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $dbNameResult = $db->query('SELECT DATABASE() AS db_name');
    $dbNameRow = $dbNameResult ? $dbNameResult->fetch_assoc() : null;
    $summary['database'] = (string)($dbNameRow['db_name'] ?? '');

    $queueOk = spg_table_exists($db, 'pre_ride_email_v3_queue');
    $eventsOk = spg_table_exists($db, 'pre_ride_email_v3_queue_events');
    $optionsOk = spg_table_exists($db, 'pre_ride_email_v3_starting_point_options');
    $summary['schema_ok'] = $queueOk && $eventsOk && $optionsOk;
    if (!$summary['schema_ok']) {
        throw new RuntimeException('Required V3 tables missing. queue=' . ($queueOk ? 'yes' : 'no') . ' events=' . ($eventsOk ? 'yes' : 'no') . ' options=' . ($optionsOk ? 'yes' : 'no'));
    }

    $statusList = array_values(array_filter(array_map('trim', explode(',', $statusFilter))));
    $allowed = ['queued', 'submit_dry_run_ready', 'ready', 'needs_review'];
    $statusList = array_values(array_intersect($statusList, $allowed));
    if (!$statusList) {
        $statusList = ['queued', 'submit_dry_run_ready', 'ready'];
    }
    $placeholders = implode(',', array_fill(0, count($statusList), '?'));
    $types = str_repeat('s', count($statusList)) . 'i';

    $sql = "SELECT id, dedupe_key, queue_status, lessor_id, starting_point_id, customer_name, driver_name, vehicle_plate, pickup_datetime, pickup_address, dropoff_address FROM pre_ride_email_v3_queue WHERE queue_status IN ($placeholders) ORDER BY COALESCE(pickup_datetime, created_at) ASC, id ASC LIMIT ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Could not prepare queue query: ' . $db->error);
    }
    $bindValues = $statusList;
    $bindValues[] = $limit;
    $stmt->bind_param($types, ...$bindValues);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $summary['rows_checked']++;
        $lessorId = (string)($row['lessor_id'] ?? '');
        $startId = (string)($row['starting_point_id'] ?? '');
        $status = spg_fetch_option_status($db, $lessorId, $startId);
        $known = !empty($status['known_lessor']);
        $valid = !empty($status['valid']);
        $reason = (string)($status['reason'] ?? '');

        if ($known) {
            $summary['known_lessor_count']++;
        } else {
            $summary['unknown_lessor_count']++;
        }
        if ($valid) {
            $summary['valid_count']++;
        } elseif ($known) {
            $summary['invalid_count']++;
        }

        $out = [
            'queue_id' => (int)$row['id'],
            'dedupe_key' => (string)$row['dedupe_key'],
            'queue_status' => (string)$row['queue_status'],
            'lessor_id' => $lessorId,
            'starting_point_id' => $startId,
            'valid' => $valid,
            'known_lessor' => $known,
            'reason' => $reason,
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'driver_name' => (string)($row['driver_name'] ?? ''),
            'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
            'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
            'options' => $status['options'] ?? [],
            'action' => 'none',
        ];

        if (!$valid && $known) {
            $blockReason = 'V3 starting-point guard: ' . $reason . '. Row blocked before helper/submission.';
            $context = [
                'lessor_id' => $lessorId,
                'queued_starting_point_id' => $startId,
                'verified_options' => $status['options'] ?? [],
                'guard_version' => SPG_VERSION,
            ];
            if ($commit) {
                if (spg_block_row($db, $row, $blockReason, $context)) {
                    $summary['blocked_count']++;
                    $summary['event_count']++;
                    $out['action'] = 'blocked';
                } else {
                    $summary['error_count']++;
                    $out['action'] = 'block_failed';
                }
            } else {
                $out['action'] = 'would_block';
            }
        }

        $rowsOut[] = $out;
    }

    $summary['ok'] = true;
} catch (Throwable $e) {
    $summary['error_count']++;
    $error = $e->getMessage();
}

$summary['finished_at'] = date(DATE_ATOM);

if ($json) {
    echo json_encode(['summary' => $summary, 'rows' => $rowsOut, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit($summary['ok'] ? 0 : 1);
}

echo 'V3 starting-point guard ' . SPG_VERSION . PHP_EOL;
echo 'Mode: ' . $summary['mode'] . PHP_EOL;
echo 'Database: ' . $summary['database'] . PHP_EOL;
echo 'Schema OK: ' . (!empty($summary['schema_ok']) ? 'yes' : 'no') . PHP_EOL;
if ($error !== '') {
    echo 'ERROR: ' . $error . PHP_EOL;
}
echo 'Rows checked: ' . $summary['rows_checked'] . ' | Valid: ' . $summary['valid_count'] . ' | Invalid-known: ' . $summary['invalid_count'] . ' | Unknown-lessor: ' . $summary['unknown_lessor_count'] . ' | Blocked: ' . $summary['blocked_count'] . PHP_EOL;
echo $commit ? 'Commit mode wrote only V3 queue/status/events where needed.' . PHP_EOL : 'SELECT-only dry-run. Add --commit to block known-invalid V3 rows.' . PHP_EOL;

foreach ($rowsOut as $row) {
    $state = !empty($row['valid']) ? 'VALID' : (!empty($row['known_lessor']) ? 'INVALID' : 'UNKNOWN');
    echo '#' . $row['queue_id'] . ' ' . $state . ' ' . $row['dedupe_key'] . PHP_EOL;
    echo '  Status: ' . $row['queue_status'] . ' | Lessor: ' . $row['lessor_id'] . ' | Start: ' . $row['starting_point_id'] . PHP_EOL;
    echo '  Transfer: ' . $row['customer_name'] . ' | ' . $row['driver_name'] . ' | ' . $row['vehicle_plate'] . PHP_EOL;
    if ((string)$row['reason'] !== '') {
        echo '  Reason: ' . $row['reason'] . PHP_EOL;
    }
    echo '  Action: ' . $row['action'] . PHP_EOL;
}

exit($summary['ok'] ? 0 : 1);
