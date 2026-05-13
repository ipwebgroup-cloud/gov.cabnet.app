<?php
/**
 * gov.cabnet.app — V3 live-submit operator approval audit.
 *
 * Purpose:
 * - Read live_submit_ready rows and show whether a V3-only operator approval exists.
 * - This is an audit/select tool only.
 *
 * Safety:
 * - SELECT only.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No queue status changes.
 * - No production submission_jobs/submission_attempts writes.
 */

declare(strict_types=1);

const PRV3_APPROVAL_AUDIT_VERSION = 'v3.0.26-live-submit-approval-audit';
const PRV3_QUEUE_TABLE = 'pre_ride_email_v3_queue';
const PRV3_APPROVAL_TABLE = 'pre_ride_email_v3_live_submit_approvals';
const PRV3_OPTIONS_TABLE = 'pre_ride_email_v3_starting_point_options';

date_default_timezone_set('Europe/Athens');

/** @return array<string,mixed> */
function prv3aa_args(array $argv): array
{
    $opts = [
        'help' => false,
        'json' => false,
        'limit' => 20,
        'status' => 'live_submit_ready',
        'min_future_minutes' => 1,
    ];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') { $opts['help'] = true; }
        elseif ($arg === '--json') { $opts['json'] = true; }
        elseif (str_starts_with($arg, '--limit=')) { $opts['limit'] = max(1, min(200, (int)substr($arg, 8))); }
        elseif (str_starts_with($arg, '--status=')) { $v = trim(substr($arg, 9)); if ($v !== '') { $opts['status'] = $v; } }
        elseif (str_starts_with($arg, '--min-future-minutes=')) { $opts['min_future_minutes'] = max(0, min(240, (int)substr($arg, 21))); }
    }
    return $opts;
}

function prv3aa_help(): void
{
    echo 'V3 live-submit approval audit ' . PRV3_APPROVAL_AUDIT_VERSION . "\n\n";
    echo "Usage:\n";
    echo "  php pre_ride_email_v3_live_submit_approval_audit.php [--limit=20] [--status=live_submit_ready] [--min-future-minutes=1] [--json]\n\n";
    echo "Safety: SELECT only; no EDXEIX call; no AADE call; no DB writes.\n";
}

/** @return array<string,mixed> */
function prv3aa_bootstrap(): array
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

function prv3aa_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

function prv3aa_db_name(mysqli $db): string
{
    $res = $db->query('SELECT DATABASE() AS db');
    $row = $res ? $res->fetch_assoc() : null;
    return is_array($row) ? (string)($row['db'] ?? '') : '';
}

/** @return array{ok:bool,minutes_until:int|null,message:string} */
function prv3aa_future_check(?string $pickup, int $minFutureMinutes): array
{
    $pickup = trim((string)$pickup);
    if ($pickup === '' || $pickup === '0000-00-00 00:00:00') {
        return ['ok' => false, 'minutes_until' => null, 'message' => 'Pickup datetime is missing.'];
    }
    try {
        $tz = new DateTimeZone('Europe/Athens');
        $pickupDt = new DateTimeImmutable($pickup, $tz);
        $now = new DateTimeImmutable('now', $tz);
        $minutes = (int)floor(($pickupDt->getTimestamp() - $now->getTimestamp()) / 60);
        if ($minutes < $minFutureMinutes) {
            return ['ok' => false, 'minutes_until' => $minutes, 'message' => 'Pickup is only ' . $minutes . ' minutes from now; approval requires at least ' . $minFutureMinutes . ' minute(s).'];
        }
        return ['ok' => true, 'minutes_until' => $minutes, 'message' => 'Pickup is future-safe for approval audit.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'minutes_until' => null, 'message' => 'Pickup datetime parse failed: ' . $e->getMessage()];
    }
}

function prv3aa_start_verified(mysqli $db, string $lessorId, string $startId): bool
{
    if (!prv3aa_table_exists($db, PRV3_OPTIONS_TABLE)) { return false; }
    $stmt = $db->prepare('SELECT id FROM ' . PRV3_OPTIONS_TABLE . ' WHERE edxeix_lessor_id = ? AND edxeix_starting_point_id = ? AND is_active = 1 LIMIT 1');
    if (!$stmt) { return false; }
    $stmt->bind_param('ss', $lessorId, $startId);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<int,array<string,mixed>> */
function prv3aa_fetch_rows(mysqli $db, string $status, int $limit): array
{
    $sql = '
        SELECT q.*,
               a.approval_status, a.approved_by, a.approved_at, a.expires_at, a.revoked_at, a.approval_note
        FROM ' . PRV3_QUEUE_TABLE . ' q
        LEFT JOIN ' . PRV3_APPROVAL_TABLE . ' a ON a.queue_id = q.id
        WHERE q.queue_status = ?
        ORDER BY q.pickup_datetime ASC, q.id ASC
        LIMIT ?
    ';
    $stmt = $db->prepare($sql);
    if (!$stmt) { throw new RuntimeException('Failed to prepare approval audit select: ' . $db->error); }
    $stmt->bind_param('si', $status, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) { $rows[] = $row; }
    return $rows;
}

/** @return array<string,mixed> */
function prv3aa_check_row(mysqli $db, array $row, int $minFutureMinutes): array
{
    $blocks = [];
    $warnings = [];
    $future = prv3aa_future_check($row['pickup_datetime'] ?? null, $minFutureMinutes);
    if (!$future['ok']) { $blocks[] = $future['message']; }

    if ((string)($row['queue_status'] ?? '') !== 'live_submit_ready') { $blocks[] = 'queue_status is not live_submit_ready.'; }
    foreach (['parser_ok', 'mapping_ok', 'future_ok'] as $gate) {
        if ((int)($row[$gate] ?? 0) !== 1) { $blocks[] = $gate . ' is not 1.'; }
    }
    foreach (['lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id', 'customer_name', 'customer_phone', 'pickup_datetime', 'estimated_end_datetime', 'payload_json'] as $key) {
        if (trim((string)($row[$key] ?? '')) === '') { $blocks[] = $key . ' is missing.'; }
    }
    if (!prv3aa_start_verified($db, (string)($row['lessor_id'] ?? ''), (string)($row['starting_point_id'] ?? ''))) {
        $blocks[] = 'Starting point is not verified for lessor.';
    }

    $approvalStatus = (string)($row['approval_status'] ?? '');
    $expiresAt = trim((string)($row['expires_at'] ?? ''));
    $revokedAt = trim((string)($row['revoked_at'] ?? ''));
    $hasApproval = ($approvalStatus === 'approved' && $revokedAt === '');
    $approvalValid = false;
    if ($hasApproval) {
        if ($expiresAt === '') {
            $warnings[] = 'Approval has no expires_at; future worker should treat this as invalid.';
        } else {
            $expiryTs = strtotime($expiresAt);
            if ($expiryTs !== false && $expiryTs > time()) {
                $approvalValid = true;
            } else {
                $warnings[] = 'Approval is expired.';
            }
        }
    }

    return [
        'queue_id' => (int)($row['id'] ?? 0),
        'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
        'queue_status' => (string)($row['queue_status'] ?? ''),
        'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
        'minutes_until' => $future['minutes_until'],
        'customer_name' => (string)($row['customer_name'] ?? ''),
        'driver_name' => (string)($row['driver_name'] ?? ''),
        'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
        'ids' => [
            'lessor_id' => (string)($row['lessor_id'] ?? ''),
            'driver_id' => (string)($row['driver_id'] ?? ''),
            'vehicle_id' => (string)($row['vehicle_id'] ?? ''),
            'starting_point_id' => (string)($row['starting_point_id'] ?? ''),
        ],
        'approval' => [
            'status' => $approvalStatus,
            'valid' => $approvalValid,
            'approved_by' => (string)($row['approved_by'] ?? ''),
            'approved_at' => (string)($row['approved_at'] ?? ''),
            'expires_at' => $expiresAt,
            'revoked_at' => $revokedAt,
        ],
        'eligible_for_operator_approval' => count($blocks) === 0,
        'blocks' => array_values(array_unique($blocks)),
        'warnings' => array_values(array_unique($warnings)),
    ];
}

$opts = prv3aa_args($argv);
if ($opts['help']) { prv3aa_help(); exit(0); }

$summary = [
    'ok' => false,
    'version' => PRV3_APPROVAL_AUDIT_VERSION,
    'mode' => 'dry_run_select_only',
    'started_at' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM),
    'finished_at' => '',
    'database' => '',
    'limit' => (int)$opts['limit'],
    'status_filter' => (string)$opts['status'],
    'min_future_minutes' => (int)$opts['min_future_minutes'],
    'schema_ok' => false,
    'approval_table_ok' => false,
    'start_options_ok' => false,
    'rows_checked' => 0,
    'eligible_for_operator_approval' => 0,
    'valid_approval_count' => 0,
    'blocked_count' => 0,
    'warning_count' => 0,
    'safety' => [
        'select_only' => true,
        'edxeix_call' => false,
        'aade_call' => false,
        'db_writes' => false,
        'production_submission_jobs' => false,
        'production_submission_attempts' => false,
    ],
    'error' => '',
];
$results = [];

try {
    $ctx = prv3aa_bootstrap();
    /** @var mysqli $db */
    $db = $ctx['db']->connection();
    $db->set_charset('utf8mb4');
    $summary['database'] = prv3aa_db_name($db);
    $queueOk = prv3aa_table_exists($db, PRV3_QUEUE_TABLE);
    $approvalOk = prv3aa_table_exists($db, PRV3_APPROVAL_TABLE);
    $optionsOk = prv3aa_table_exists($db, PRV3_OPTIONS_TABLE);
    $summary['schema_ok'] = $queueOk && $approvalOk;
    $summary['approval_table_ok'] = $approvalOk;
    $summary['start_options_ok'] = $optionsOk;
    if (!$summary['schema_ok']) { throw new RuntimeException('V3 approval schema is not installed.'); }

    $rows = prv3aa_fetch_rows($db, (string)$opts['status'], (int)$opts['limit']);
    $summary['rows_checked'] = count($rows);
    foreach ($rows as $row) {
        $check = prv3aa_check_row($db, $row, (int)$opts['min_future_minutes']);
        $results[] = $check;
        if ($check['eligible_for_operator_approval']) { $summary['eligible_for_operator_approval']++; }
        else { $summary['blocked_count']++; }
        if (!empty($check['approval']['valid'])) { $summary['valid_approval_count']++; }
        $summary['warning_count'] += count($check['warnings']);
    }
    $summary['ok'] = true;
} catch (Throwable $e) {
    $summary['error'] = $e->getMessage();
}
$summary['finished_at'] = (new DateTimeImmutable('now', new DateTimeZone('Europe/Athens')))->format(DATE_ATOM);

if ($opts['json']) {
    echo json_encode(['summary' => $summary, 'rows' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
    exit($summary['ok'] ? 0 : 1);
}

echo 'V3 live-submit approval audit ' . PRV3_APPROVAL_AUDIT_VERSION . "\n";
echo 'Mode: dry_run_select_only' . "\n";
echo 'Database: ' . ($summary['database'] ?: '-') . "\n";
echo 'Schema OK: ' . ($summary['schema_ok'] ? 'yes' : 'no') . ' | Approval table: ' . ($summary['approval_table_ok'] ? 'yes' : 'no') . ' | Starting-point options: ' . ($summary['start_options_ok'] ? 'yes' : 'no') . "\n";
echo 'Rows checked: ' . $summary['rows_checked'] . ' | Eligible for approval: ' . $summary['eligible_for_operator_approval'] . ' | Valid approvals: ' . $summary['valid_approval_count'] . ' | Blocked: ' . $summary['blocked_count'] . ' | Warnings: ' . $summary['warning_count'] . "\n";
echo 'SELECT only. No EDXEIX call. No AADE call. No DB writes.' . "\n";
if ($summary['error'] !== '') { echo 'ERROR: ' . $summary['error'] . "\n"; exit(1); }
if (empty($results)) { echo 'No V3 queue rows matched status filter: ' . $opts['status'] . "\n"; exit(0); }
foreach ($results as $i => $row) {
    echo '#' . ($i + 1) . ' queue_id=' . $row['queue_id'] . ' ' . ($row['eligible_for_operator_approval'] ? 'APPROVABLE' : 'BLOCKED') . ' approval=' . ($row['approval']['valid'] ? 'valid' : 'none/invalid') . "\n";
    echo '  Pickup: ' . $row['pickup_datetime'] . ' (' . ($row['minutes_until'] === null ? '-' : $row['minutes_until'] . ' min') . ")\n";
    echo '  Transfer: ' . $row['customer_name'] . ' | ' . $row['driver_name'] . ' | ' . $row['vehicle_plate'] . "\n";
    echo '  IDs: lessor=' . $row['ids']['lessor_id'] . ' driver=' . $row['ids']['driver_id'] . ' vehicle=' . $row['ids']['vehicle_id'] . ' start=' . $row['ids']['starting_point_id'] . "\n";
    foreach ($row['blocks'] as $block) { echo '  Block: ' . $block . "\n"; }
    foreach ($row['warnings'] as $warning) { echo '  Warning: ' . $warning . "\n"; }
}
