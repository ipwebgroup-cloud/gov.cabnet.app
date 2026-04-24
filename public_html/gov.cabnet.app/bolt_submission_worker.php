<?php
/**
 * gov.cabnet.app — EDXEIX Submission Worker Dry-Run Gate
 *
 * SAFETY CONTRACT:
 * - This script does NOT call EDXEIX.
 * - This script does NOT submit forms.
 * - Default mode is read-only analysis.
 * - record=1 writes local submission_attempts audit rows only.
 * - LAB rows remain blocked unless allow_lab=1 is explicit.
 *
 * Usage:
 *   /bolt_submission_worker.php?limit=30
 *   /bolt_submission_worker.php?record=1&limit=30
 *   /bolt_submission_worker.php?allow_lab=1&record=1&limit=30   // local lab only
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function gov_worker_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function gov_worker_terminal_status(string $status): bool
{
    $status = strtolower(trim($status));
    if ($status === '') {
        return false;
    }
    $terminalExact = [
        'finished',
        'completed',
        'client_cancelled',
        'driver_cancelled',
        'driver_cancelled_after_accept',
        'cancelled',
        'canceled',
        'expired',
        'rejected',
        'failed',
    ];
    if (in_array($status, $terminalExact, true)) {
        return true;
    }
    return strpos($status, 'cancel') !== false || strpos($status, 'finished') !== false || strpos($status, 'complete') !== false;
}

function gov_worker_order_reference(array $row): string
{
    return (string)gov_worker_value($row, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_reference'], '');
}

function gov_worker_is_lab_reference(string $source, string $orderRef): bool
{
    $source = strtolower(trim($source));
    $ref = strtoupper(trim($orderRef));
    return strpos($source, 'lab') !== false || strpos($ref, 'LAB-') === 0;
}

function gov_worker_decode_json_value($raw): ?array
{
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : null;
}

function gov_worker_payload_from_job(array $job): ?array
{
    foreach (['edxeix_payload_json', 'payload_json', 'request_payload_json', 'payload', 'body'] as $key) {
        if (!array_key_exists($key, $job)) {
            continue;
        }
        $decoded = gov_worker_decode_json_value((string)$job[$key]);
        if ($decoded !== null) {
            return $decoded;
        }
    }
    return null;
}

function gov_worker_find_booking(mysqli $db, array $job): ?array
{
    if (!gov_bridge_table_exists($db, 'normalized_bookings')) {
        return null;
    }
    $columns = gov_bridge_table_columns($db, 'normalized_bookings');
    $bookingId = (string)gov_worker_value($job, ['normalized_booking_id', 'booking_id'], '');
    $orderRef = gov_worker_order_reference($job);

    if ($bookingId !== '' && isset($columns['id'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE id = ? LIMIT 1', [$bookingId]);
        if ($row) { return $row; }
    }
    if ($bookingId !== '' && isset($columns['booking_id'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE booking_id = ? LIMIT 1', [$bookingId]);
        if ($row) { return $row; }
    }
    if ($bookingId !== '' && isset($columns['normalized_booking_id'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE normalized_booking_id = ? LIMIT 1', [$bookingId]);
        if ($row) { return $row; }
    }
    if ($orderRef !== '' && isset($columns['order_reference'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE order_reference = ? LIMIT 1', [$orderRef]);
        if ($row) { return $row; }
    }
    if ($orderRef !== '' && isset($columns['external_order_id'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM normalized_bookings WHERE external_order_id = ? LIMIT 1', [$orderRef]);
        if ($row) { return $row; }
    }
    return null;
}

function gov_worker_payload_for_job(mysqli $db, array $job): array
{
    $payload = gov_worker_payload_from_job($job);
    $booking = gov_worker_find_booking($db, $job);

    if ($payload === null && $booking !== null && function_exists('gov_build_edxeix_preview_payload')) {
        $payload = gov_build_edxeix_preview_payload($db, $booking);
    }

    return [$payload, $booking];
}

function gov_worker_order_column(array $columns): string
{
    foreach (['updated_at', 'queued_at', 'created_at', 'id'] as $column) {
        if (isset($columns[$column])) {
            return $column;
        }
    }
    return array_key_first($columns) ?: 'id';
}

function gov_worker_recent_jobs(mysqli $db, int $limit): array
{
    if (!gov_bridge_table_exists($db, 'submission_jobs')) {
        return [];
    }
    $columns = gov_bridge_table_columns($db, 'submission_jobs');
    if (!$columns) {
        return [];
    }
    $orderColumn = gov_worker_order_column($columns);
    $sql = 'SELECT * FROM submission_jobs ORDER BY ' . gov_bridge_quote_identifier($orderColumn) . ' DESC LIMIT ' . (int)$limit;
    return gov_bridge_fetch_all($db, $sql);
}

function gov_worker_analyze_job(mysqli $db, array $job, bool $allowLab): array
{
    [$payload, $booking] = gov_worker_payload_for_job($db, $job);

    $jobId = (string)gov_worker_value($job, ['id'], '');
    $source = (string)gov_worker_value($job, ['source_system', 'source_type'], gov_worker_value($booking ?: [], ['source_system', 'source_type'], ''));
    $source = $source ?: 'bolt';
    $orderRef = gov_worker_order_reference($job) ?: gov_worker_order_reference($booking ?: []);
    $status = (string)gov_worker_value($booking ?: [], ['order_status', 'status'], gov_worker_value($job, ['booking_status', 'order_status'], ''));
    $jobStatus = (string)gov_worker_value($job, ['status', 'state', 'job_status'], '');
    $bookingId = (string)gov_worker_value($job, ['normalized_booking_id', 'booking_id'], gov_worker_value($booking ?: [], ['id', 'booking_id', 'normalized_booking_id'], ''));

    $driver = $payload ? (string)gov_worker_value($payload, ['driver'], '') : '';
    $vehicle = $payload ? (string)gov_worker_value($payload, ['vehicle'], '') : '';
    $startedAt = $payload ? (string)gov_worker_value($payload, ['started_at'], '') : '';
    $endedAt = $payload ? (string)gov_worker_value($payload, ['ended_at'], '') : '';
    $price = $payload ? (string)gov_worker_value($payload, ['price'], '') : '';

    $mapping = is_array($payload['_mapping_status'] ?? null) ? $payload['_mapping_status'] : [];
    $driverMapped = $driver !== '' || !empty($mapping['driver_mapped']);
    $vehicleMapped = $vehicle !== '' || !empty($mapping['vehicle_mapped']);
    $futureGuard = function_exists('gov_edxeix_future_guard_passes') ? gov_edxeix_future_guard_passes($startedAt ?: null) : false;
    $terminal = gov_worker_terminal_status($status);
    $lab = gov_worker_is_lab_reference($source, $orderRef);

    $blockers = [];
    if ($payload === null) {
        $blockers[] = 'missing_payload_and_no_normalized_booking_match';
    }
    if ($lab && !$allowLab) {
        $blockers[] = 'lab_row_blocked';
    }
    if (!$driverMapped) {
        $blockers[] = 'driver_not_mapped';
    }
    if (!$vehicleMapped) {
        $blockers[] = 'vehicle_not_mapped';
    }
    if ($startedAt === '') {
        $blockers[] = 'missing_started_at';
    } elseif (!$futureGuard) {
        $blockers[] = 'started_at_not_30_min_future';
    }
    if ($terminal) {
        $blockers[] = 'terminal_order_status';
    }

    $payloadHash = $payload ? hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) : '';
    $submissionSafe = $payload !== null && !$blockers;

    return [
        'job_id' => $jobId,
        'booking_id' => $bookingId,
        'source_system' => $source,
        'order_reference' => $orderRef,
        'job_status' => $jobStatus,
        'booking_status' => $status,
        'driver_id' => $driver,
        'vehicle_id' => $vehicle,
        'started_at' => $startedAt,
        'ended_at' => $endedAt,
        'price' => $price,
        'mapping_ready' => $driverMapped && $vehicleMapped,
        'future_guard_passed' => $futureGuard,
        'terminal_status' => $terminal,
        'is_lab_row' => $lab,
        'submission_safe' => $submissionSafe,
        'blockers' => $blockers,
        'payload_hash' => $payloadHash,
        'payload_source' => $payload !== null ? (gov_worker_payload_from_job($job) !== null ? 'submission_job' : 'normalized_booking_rebuild') : 'missing',
        'edxeix_payload_preview' => $payload,
    ];
}

function gov_worker_attempt_exists(mysqli $db, array $analysis): bool
{
    if (!gov_bridge_table_exists($db, 'submission_attempts')) {
        return false;
    }
    $columns = gov_bridge_table_columns($db, 'submission_attempts');
    $jobId = (string)$analysis['job_id'];
    $hash = (string)$analysis['payload_hash'];

    if ($jobId !== '' && $hash !== '' && isset($columns['submission_job_id']) && isset($columns['payload_hash'])) {
        return (bool)gov_bridge_fetch_one($db, 'SELECT * FROM submission_attempts WHERE submission_job_id = ? AND payload_hash = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1', [$jobId, $hash]);
    }
    if ($jobId !== '' && $hash !== '' && isset($columns['job_id']) && isset($columns['payload_hash'])) {
        return (bool)gov_bridge_fetch_one($db, 'SELECT * FROM submission_attempts WHERE job_id = ? AND payload_hash = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) LIMIT 1', [$jobId, $hash]);
    }
    return false;
}

function gov_worker_record_attempt(mysqli $db, array $analysis): array
{
    if (!gov_bridge_table_exists($db, 'submission_attempts')) {
        return [
            'ok' => false,
            'action' => 'not_recorded',
            'reason' => 'submission_attempts table does not exist',
        ];
    }

    if (gov_worker_attempt_exists($db, $analysis)) {
        return [
            'ok' => true,
            'action' => 'kept_recent_attempt',
            'reason' => 'recent attempt already exists for this job/hash',
        ];
    }

    $now = date('Y-m-d H:i:s');
    $status = $analysis['submission_safe'] ? 'dry_run_validated' : 'blocked_by_preflight';
    $response = [
        'ok' => $analysis['submission_safe'],
        'mode' => 'dry_run_worker',
        'would_submit_to_edxeix' => false,
        'submission_safe' => $analysis['submission_safe'],
        'blockers' => $analysis['blockers'],
        'note' => 'Local audit attempt only. No EDXEIX HTTP request was performed.',
    ];

    $payloadJson = gov_bridge_json_encode_db($analysis['edxeix_payload_preview'] ?? []);
    $responseJson = gov_bridge_json_encode_db($response);
    $row = [
        'submission_job_id' => $analysis['job_id'],
        'job_id' => $analysis['job_id'],
        'normalized_booking_id' => $analysis['booking_id'],
        'booking_id' => $analysis['booking_id'],
        'source_system' => $analysis['source_system'],
        'source_type' => $analysis['source_system'],
        'order_reference' => $analysis['order_reference'],
        'external_order_id' => $analysis['order_reference'],
        'status' => $status,
        'state' => $status,
        'attempt_status' => $status,
        'mode' => 'dry_run_worker',
        'is_dry_run' => '1',
        'http_status' => '0',
        'payload_hash' => $analysis['payload_hash'],
        'dedupe_hash' => $analysis['payload_hash'],
        'request_payload_json' => $payloadJson,
        'payload_json' => $payloadJson,
        'response_payload_json' => $responseJson,
        'response_json' => $responseJson,
        'error_message' => $analysis['submission_safe'] ? '' : implode(', ', $analysis['blockers']),
        'notes' => 'DRY RUN ONLY. No EDXEIX HTTP request was performed.',
        'created_at' => $now,
        'updated_at' => $now,
        'attempted_at' => $now,
        'started_at' => $now,
        'finished_at' => $now,
    ];

    $id = gov_bridge_insert_row($db, 'submission_attempts', $row);
    return [
        'ok' => true,
        'action' => 'recorded_local_attempt',
        'attempt_id' => $id,
        'status' => $status,
    ];
}

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $limit = gov_bridge_int_param('limit', 30, 1, 200);
    $record = gov_bridge_bool_param('record', false);
    $allowLab = gov_bridge_bool_param('allow_lab', false);

    $db = gov_bridge_db();
    $jobs = gov_worker_recent_jobs($db, $limit);

    $rows = [];
    $summary = [
        'jobs_checked' => 0,
        'would_submit' => 0,
        'blocked' => 0,
        'recorded_attempts' => 0,
        'kept_recent_attempts' => 0,
        'missing_payload_or_booking' => 0,
    ];

    foreach ($jobs as $job) {
        $analysis = gov_worker_analyze_job($db, $job, $allowLab);
        $summary['jobs_checked']++;
        if ($analysis['submission_safe']) {
            $summary['would_submit']++;
        } else {
            $summary['blocked']++;
        }
        if (in_array('missing_payload_and_no_normalized_booking_match', $analysis['blockers'], true)) {
            $summary['missing_payload_or_booking']++;
        }

        $attempt = null;
        if ($record) {
            $attempt = gov_worker_record_attempt($db, $analysis);
            if (($attempt['action'] ?? '') === 'recorded_local_attempt') {
                $summary['recorded_attempts']++;
            }
            if (($attempt['action'] ?? '') === 'kept_recent_attempt') {
                $summary['kept_recent_attempts']++;
            }
        }

        $rows[] = [
            'job_id' => $analysis['job_id'],
            'booking_id' => $analysis['booking_id'],
            'source_system' => $analysis['source_system'],
            'order_reference' => $analysis['order_reference'],
            'job_status' => $analysis['job_status'],
            'booking_status' => $analysis['booking_status'],
            'driver_id' => $analysis['driver_id'],
            'vehicle_id' => $analysis['vehicle_id'],
            'started_at' => $analysis['started_at'],
            'ended_at' => $analysis['ended_at'],
            'price' => $analysis['price'],
            'mapping_ready' => $analysis['mapping_ready'],
            'future_guard_passed' => $analysis['future_guard_passed'],
            'terminal_status' => $analysis['terminal_status'],
            'is_lab_row' => $analysis['is_lab_row'],
            'submission_safe' => $analysis['submission_safe'],
            'payload_source' => $analysis['payload_source'],
            'payload_hash' => $analysis['payload_hash'],
            'blockers' => $analysis['blockers'],
            'attempt' => $attempt,
            'edxeix_payload_preview' => $analysis['edxeix_payload_preview'],
        ];
    }

    gov_bridge_json_response([
        'ok' => true,
        'script' => 'bolt_submission_worker.php',
        'mode' => $record ? 'record_local_dry_run_attempts' : 'analysis_only',
        'allow_lab' => $allowLab,
        'generated_at' => date('Y-m-d H:i:s'),
        'guard_minutes' => (int)($config['edxeix']['future_start_guard_minutes'] ?? 30),
        'summary' => $summary,
        'rows' => $rows,
        'note' => 'No EDXEIX submission was performed. record=1 only writes local submission_attempts audit rows. Live submission remains intentionally unimplemented in this worker.',
    ]);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt_submission_worker.php',
        'error' => $e->getMessage(),
    ], 500);
}
