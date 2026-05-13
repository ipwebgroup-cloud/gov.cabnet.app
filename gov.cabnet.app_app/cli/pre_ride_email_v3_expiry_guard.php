<?php
/**
 * gov.cabnet.app — V3 queue expiry guard.
 *
 * Purpose:
 * - Block active V3 queue rows once their pickup time is no longer future-safe.
 * - Prevent stale queued / submit-ready / live-ready rows from remaining actionable.
 *
 * Safety:
 * - Default mode is SELECT-only dry-run.
 * - --commit writes only to pre_ride_email_v3_queue and pre_ride_email_v3_queue_events.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs/submission_attempts writes.
 * - No production pre-ride-email-tool.php changes.
 */

declare(strict_types=1);

const PRV3_EXPIRY_GUARD_VERSION = 'v3.0.34-v3-queue-expiry-guard';
const PRV3_EXPIRY_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const PRV3_EXPIRY_EVENTS_TABLE = 'pre_ride_email_v3_queue_events';
const PRV3_EXPIRY_BLOCK_REASON = 'v3_queue_row_expired_pickup_not_future_safe';

date_default_timezone_set('Europe/Athens');

/** @return array<string,mixed> */
function prv3eg_parse_args(array $argv): array
{
    $opts = [
        'help' => false,
        'json' => false,
        'commit' => false,
        'limit' => 200,
        'min_future_minutes' => 0,
        'status_csv' => 'queued,ready,submit_dry_run_ready,live_submit_ready',
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--commit') {
            $opts['commit'] = true;
        } elseif (str_starts_with($arg, '--limit=')) {
            $opts['limit'] = max(1, min(1000, (int)substr($arg, 8)));
        } elseif (str_starts_with($arg, '--min-future-minutes=')) {
            $opts['min_future_minutes'] = max(0, min(240, (int)substr($arg, 21)));
        } elseif (str_starts_with($arg, '--statuses=')) {
            $raw = trim(substr($arg, 11));
            if ($raw !== '') {
                $opts['status_csv'] = $raw;
            }
        }
    }

    return $opts;
}

function prv3eg_help(): string
{
    return "V3 queue expiry guard " . PRV3_EXPIRY_GUARD_VERSION . "\n\n"
        . "Usage:\n"
        . "  php pre_ride_email_v3_expiry_guard.php [--limit=200] [--min-future-minutes=0] [--statuses=queued,ready,submit_dry_run_ready,live_submit_ready] [--json] [--commit]\n\n"
        . "Default mode is SELECT-only. --commit blocks only active V3 rows whose pickup is already past or below the configured future buffer.\n";
}

/** @return array<int,string> */
function prv3eg_statuses(string $csv): array
{
    $allowed = [
        'queued' => true,
        'ready' => true,
        'submit_dry_run_ready' => true,
        'live_submit_ready' => true,
        'live_submit_pending' => true,
    ];
    $out = [];
    foreach (explode(',', $csv) as $status) {
        $status = trim($status);
        if ($status !== '' && isset($allowed[$status])) {
            $out[$status] = $status;
        }
    }
    if ($out === []) {
        $out = [
            'queued' => 'queued',
            'ready' => 'ready',
            'submit_dry_run_ready' => 'submit_dry_run_ready',
            'live_submit_ready' => 'live_submit_ready',
        ];
    }
    return array_values($out);
}

/** @return array<string,mixed> */
function prv3eg_bootstrap_context(): array
{
    $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $bootstrap = dirname(__DIR__, 2) . '/src/bootstrap.php';
    }
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Private app bootstrap not found from CLI path.');
    }

    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Private app bootstrap did not return a usable DB context.');
    }
    return $ctx;
}

function prv3eg_db_name(mysqli $db): string
{
    $res = $db->query('SELECT DATABASE() AS db');
    $row = $res ? $res->fetch_assoc() : null;
    return is_array($row) ? (string)($row['db'] ?? '') : '';
}

function prv3eg_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @param array<int,string> $statuses @return array<int,array<string,mixed>> */
function prv3eg_fetch_active_rows(mysqli $db, array $statuses, int $limit): array
{
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $sql = 'SELECT id, dedupe_key, queue_status, customer_name, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id, pickup_datetime, created_at, last_error '
        . 'FROM ' . PRV3_EXPIRY_QUEUE_TABLE . ' '
        . 'WHERE queue_status IN (' . $placeholders . ') '
        . "AND pickup_datetime IS NOT NULL AND pickup_datetime <> '' AND pickup_datetime <> '0000-00-00 00:00:00' "
        . 'ORDER BY pickup_datetime ASC, id ASC LIMIT ?';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 expiry select: ' . $db->error);
    }

    $types = str_repeat('s', count($statuses)) . 'i';
    $values = array_values($statuses);
    $values[] = $limit;
    $refs = [];
    $refs[] = &$types;
    foreach ($values as $i => &$value) {
        $refs[] = &$value;
    }
    $stmt->bind_param(...$refs);
    $stmt->execute();

    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

/** @return array{expired:bool,minutes_until:int|null,message:string} */
function prv3eg_expiry_check(?string $pickup, int $minFutureMinutes): array
{
    $pickup = trim((string)$pickup);
    if ($pickup === '' || $pickup === '0000-00-00 00:00:00') {
        return ['expired' => true, 'minutes_until' => null, 'message' => 'Pickup datetime is missing or invalid.'];
    }

    try {
        $tz = new DateTimeZone('Europe/Athens');
        $pickupDt = new DateTimeImmutable($pickup, $tz);
        $now = new DateTimeImmutable('now', $tz);
        $minutes = (int)floor(($pickupDt->getTimestamp() - $now->getTimestamp()) / 60);
        if ($minutes < $minFutureMinutes) {
            return [
                'expired' => true,
                'minutes_until' => $minutes,
                'message' => 'Pickup is only ' . $minutes . ' minute(s) from now. V3 expiry guard requires at least ' . $minFutureMinutes . ' future minute(s).',
            ];
        }
        return [
            'expired' => false,
            'minutes_until' => $minutes,
            'message' => 'Pickup remains future-safe for V3 queue status.',
        ];
    } catch (Throwable $e) {
        return ['expired' => true, 'minutes_until' => null, 'message' => 'Pickup datetime could not be parsed: ' . $e->getMessage()];
    }
}

function prv3eg_insert_event(mysqli $db, int $queueId, string $dedupeKey, string $status, string $message, array $context): void
{
    $json = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = '{}';
    }
    $type = 'v3_expiry_guard_blocked';
    $createdBy = 'v3_expiry_guard';
    $stmt = $db->prepare('INSERT INTO ' . PRV3_EXPIRY_EVENTS_TABLE . ' (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 expiry event insert: ' . $db->error);
    }
    $stmt->bind_param('issssss', $queueId, $dedupeKey, $type, $status, $message, $json, $createdBy);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to insert V3 expiry event: ' . $stmt->error);
    }
}

/** @param array<int,string> $activeStatuses */
function prv3eg_block_row(mysqli $db, int $queueId, array $activeStatuses, string $message): bool
{
    $placeholders = implode(',', array_fill(0, count($activeStatuses), '?'));
    $sql = 'UPDATE ' . PRV3_EXPIRY_QUEUE_TABLE . ' SET queue_status = ?, failed_at = COALESCE(failed_at, NOW()), last_error = ? WHERE id = ? AND queue_status IN (' . $placeholders . ') LIMIT 1';
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Failed to prepare V3 expiry queue update: ' . $db->error);
    }

    $newStatus = 'blocked';
    $types = 'ssi' . str_repeat('s', count($activeStatuses));
    $values = [$newStatus, $message, $queueId];
    foreach ($activeStatuses as $status) {
        $values[] = $status;
    }

    $refs = [];
    $refs[] = &$types;
    foreach ($values as $i => &$value) {
        $refs[] = &$value;
    }
    $stmt->bind_param(...$refs);
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to block expired V3 row: ' . $stmt->error);
    }
    return $stmt->affected_rows > 0;
}

/** @return array<string,mixed> */
function prv3eg_run(array $argv): array
{
    $opts = prv3eg_parse_args($argv);
    $statuses = prv3eg_statuses((string)$opts['status_csv']);

    $summary = [
        'ok' => false,
        'version' => PRV3_EXPIRY_GUARD_VERSION,
        'mode' => $opts['commit'] ? 'commit_v3_expiry_block_only' : 'dry_run_select_only',
        'started_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM),
        'finished_at' => '',
        'database' => '',
        'limit' => (int)$opts['limit'],
        'min_future_minutes' => (int)$opts['min_future_minutes'],
        'statuses' => $statuses,
        'schema_ok' => false,
        'rows_checked' => 0,
        'expired_count' => 0,
        'future_safe_count' => 0,
        'blocked_count' => 0,
        'events_inserted' => 0,
        'safety' => [
            'v3_tables_only' => true,
            'edxeix_call' => false,
            'aade_call' => false,
            'production_submission_jobs' => false,
            'production_submission_attempts' => false,
            'production_pre_ride_tool_change' => false,
        ],
        'error' => '',
    ];
    $results = [];

    if (!empty($opts['help'])) {
        return ['help' => true, 'text' => prv3eg_help(), 'summary' => $summary, 'rows' => []];
    }

    try {
        $ctx = prv3eg_bootstrap_context();
        /** @var mysqli $db */
        $db = $ctx['db']->connection();
        $db->set_charset('utf8mb4');
        $summary['database'] = prv3eg_db_name($db);

        $queueExists = prv3eg_table_exists($db, PRV3_EXPIRY_QUEUE_TABLE);
        $eventsExists = prv3eg_table_exists($db, PRV3_EXPIRY_EVENTS_TABLE);
        $summary['schema_ok'] = $queueExists && $eventsExists;
        if (!$summary['schema_ok']) {
            throw new RuntimeException('V3 queue/events schema is not installed.');
        }

        $rows = prv3eg_fetch_active_rows($db, $statuses, (int)$opts['limit']);
        $summary['rows_checked'] = count($rows);

        foreach ($rows as $row) {
            $check = prv3eg_expiry_check($row['pickup_datetime'] ?? null, (int)$opts['min_future_minutes']);
            $isExpired = (bool)$check['expired'];
            if ($isExpired) {
                $summary['expired_count']++;
            } else {
                $summary['future_safe_count']++;
            }

            $entry = [
                'queue_id' => (int)($row['id'] ?? 0),
                'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
                'old_status' => (string)($row['queue_status'] ?? ''),
                'customer_name' => (string)($row['customer_name'] ?? ''),
                'driver_name' => (string)($row['driver_name'] ?? ''),
                'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
                'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
                'minutes_until' => $check['minutes_until'],
                'expired' => $isExpired,
                'message' => $check['message'],
                'blocked' => false,
            ];

            if ($isExpired && $opts['commit']) {
                $message = PRV3_EXPIRY_BLOCK_REASON . ': ' . $check['message'];
                $blocked = prv3eg_block_row($db, (int)$entry['queue_id'], $statuses, $message);
                $entry['blocked'] = $blocked;
                if ($blocked) {
                    $summary['blocked_count']++;
                    prv3eg_insert_event($db, (int)$entry['queue_id'], (string)$entry['dedupe_key'], 'blocked', $message, [
                        'version' => PRV3_EXPIRY_GUARD_VERSION,
                        'old_status' => $entry['old_status'],
                        'pickup_datetime' => $entry['pickup_datetime'],
                        'minutes_until' => $entry['minutes_until'],
                        'min_future_minutes' => (int)$opts['min_future_minutes'],
                        'vehicle_plate' => $entry['vehicle_plate'],
                        'customer_name' => $entry['customer_name'],
                    ]);
                    $summary['events_inserted']++;
                }
            }

            $results[] = $entry;
        }

        $summary['ok'] = true;
    } catch (Throwable $e) {
        $summary['error'] = $e->getMessage();
    }

    $summary['finished_at'] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM);
    return ['help' => false, 'summary' => $summary, 'rows' => $results];
}

$result = prv3eg_run($argv);
if (!empty($result['help'])) {
    echo (string)$result['text'];
    exit(0);
}

$opts = prv3eg_parse_args($argv);
if (!empty($opts['json'])) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    exit(!empty($result['summary']['ok']) ? 0 : 1);
}

$s = (array)$result['summary'];
echo 'V3 queue expiry guard ' . ($s['version'] ?? PRV3_EXPIRY_GUARD_VERSION) . PHP_EOL;
echo 'Mode: ' . ($s['mode'] ?? '-') . PHP_EOL;
echo 'Database: ' . (($s['database'] ?? '') ?: '-') . PHP_EOL;
echo 'Schema OK: ' . (!empty($s['schema_ok']) ? 'yes' : 'no') . ' | Min future minutes: ' . (int)($s['min_future_minutes'] ?? 0) . PHP_EOL;
echo 'Rows checked: ' . (int)($s['rows_checked'] ?? 0) . ' | Expired: ' . (int)($s['expired_count'] ?? 0) . ' | Future-safe: ' . (int)($s['future_safe_count'] ?? 0) . ' | Blocked: ' . (int)($s['blocked_count'] ?? 0) . ' | Events: ' . (int)($s['events_inserted'] ?? 0) . PHP_EOL;
echo 'No EDXEIX call. No AADE call. No production submission tables.' . PHP_EOL;

if (($s['error'] ?? '') !== '') {
    echo 'ERROR: ' . $s['error'] . PHP_EOL;
    exit(1);
}

if (empty($opts['commit'])) {
    echo 'DRY RUN only. Add --commit to block expired active V3 rows.' . PHP_EOL;
} else {
    echo 'Commit mode wrote only V3 queue/status/events for expired rows.' . PHP_EOL;
}

if (empty($result['rows'])) {
    echo 'No active V3 rows matched expiry guard status filter.' . PHP_EOL;
    exit(0);
}

foreach ((array)$result['rows'] as $i => $row) {
    echo '#' . ($i + 1) . ' ' . (!empty($row['expired']) ? 'EXPIRED' : 'FUTURE-SAFE') . ' queue_id=' . (int)$row['queue_id'] . ' status=' . $row['old_status'] . ' ' . $row['dedupe_key'] . PHP_EOL;
    echo '  Pickup: ' . $row['pickup_datetime'] . ' (' . ($row['minutes_until'] === null ? '-' : $row['minutes_until'] . ' min') . ')' . PHP_EOL;
    echo '  Transfer: ' . $row['customer_name'] . ' | ' . $row['driver_name'] . ' | ' . $row['vehicle_plate'] . PHP_EOL;
    echo '  Message: ' . $row['message'] . PHP_EOL;
    if (!empty($row['blocked'])) {
        echo '  Action: blocked' . PHP_EOL;
    }
}

exit(0);
