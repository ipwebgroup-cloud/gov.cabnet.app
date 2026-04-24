<?php
/**
 * gov.cabnet.app — local EDXEIX submission jobs queue JSON report.
 *
 * Read-only diagnostic endpoint.
 * - Does not call EDXEIX.
 * - Does not create/update jobs.
 * - Shows local submission_jobs and submission_attempts state.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function gov_jobs_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function gov_jobs_pick_order_column(array $columns): string
{
    foreach (['updated_at', 'created_at', 'queued_at', 'id'] as $column) {
        if (isset($columns[$column])) {
            return $column;
        }
    }
    return array_key_first($columns) ?: 'id';
}

function gov_jobs_recent_table(mysqli $db, string $table, int $limit): array
{
    if (!gov_bridge_table_exists($db, $table)) {
        return [];
    }
    $columns = gov_bridge_table_columns($db, $table);
    if (!$columns) {
        return [];
    }
    $orderColumn = gov_jobs_pick_order_column($columns);
    $sql = 'SELECT * FROM ' . gov_bridge_quote_identifier($table) . ' ORDER BY ' . gov_bridge_quote_identifier($orderColumn) . ' DESC LIMIT ' . (int)$limit;
    return gov_bridge_fetch_all($db, $sql);
}

function gov_jobs_count_attempts(mysqli $db, array $attemptColumns, array $job): int
{
    if (!$attemptColumns || !isset($job['id'])) {
        return 0;
    }
    foreach (['submission_job_id', 'job_id'] as $fk) {
        if (isset($attemptColumns[$fk])) {
            $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts WHERE ' . gov_bridge_quote_identifier($fk) . ' = ?', [(string)$job['id']]);
            return (int)($row['c'] ?? 0);
        }
    }
    if (isset($attemptColumns['order_reference'])) {
        $orderRef = (string)gov_jobs_value($job, ['order_reference', 'external_order_id'], '');
        if ($orderRef !== '') {
            $row = gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM submission_attempts WHERE order_reference = ?', [$orderRef]);
            return (int)($row['c'] ?? 0);
        }
    }
    return 0;
}

function gov_jobs_payload_preview(array $row)
{
    $raw = gov_jobs_value($row, ['edxeix_payload_json', 'payload_json', 'request_payload_json', 'payload', 'body'], '');
    if ($raw === '') {
        return null;
    }
    if (is_array($raw)) {
        return $raw;
    }
    $decoded = json_decode((string)$raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $decoded : (string)$raw;
}

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $limit = gov_bridge_int_param('limit', 50, 1, 200);
    $db = gov_bridge_db();

    $jobsColumns = gov_bridge_table_columns($db, 'submission_jobs');
    $attemptColumns = gov_bridge_table_columns($db, 'submission_attempts');
    $jobs = gov_jobs_recent_table($db, 'submission_jobs', $limit);
    $attempts = gov_jobs_recent_table($db, 'submission_attempts', $limit);

    $statusCounts = [];
    $normalizedJobs = [];
    foreach ($jobs as $job) {
        $status = (string)gov_jobs_value($job, ['status', 'state', 'job_status'], 'unknown');
        $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        $normalizedJobs[] = [
            'id' => $job['id'] ?? null,
            'status' => $status,
            'source_system' => gov_jobs_value($job, ['source_system', 'source_type'], ''),
            'job_type' => gov_jobs_value($job, ['job_type', 'type'], ''),
            'normalized_booking_id' => gov_jobs_value($job, ['normalized_booking_id', 'booking_id'], ''),
            'order_reference' => gov_jobs_value($job, ['order_reference', 'external_order_id'], ''),
            'edxeix_driver_id' => gov_jobs_value($job, ['edxeix_driver_id', 'driver_id'], ''),
            'edxeix_vehicle_id' => gov_jobs_value($job, ['edxeix_vehicle_id', 'vehicle_id'], ''),
            'payload_hash' => gov_jobs_value($job, ['payload_hash', 'dedupe_hash'], ''),
            'queued_at' => gov_jobs_value($job, ['queued_at'], ''),
            'created_at' => gov_jobs_value($job, ['created_at'], ''),
            'updated_at' => gov_jobs_value($job, ['updated_at'], ''),
            'attempts_count' => gov_jobs_count_attempts($db, $attemptColumns, $job),
            'notes' => gov_jobs_value($job, ['notes', 'message'], ''),
            'payload_preview' => gov_jobs_payload_preview($job),
        ];
    }

    $normalizedAttempts = [];
    foreach ($attempts as $attempt) {
        $normalizedAttempts[] = [
            'id' => $attempt['id'] ?? null,
            'job_id' => gov_jobs_value($attempt, ['submission_job_id', 'job_id'], ''),
            'status' => gov_jobs_value($attempt, ['status', 'state', 'result'], ''),
            'http_status' => gov_jobs_value($attempt, ['http_status', 'status_code'], ''),
            'created_at' => gov_jobs_value($attempt, ['created_at', 'attempted_at'], ''),
            'message' => gov_jobs_value($attempt, ['message', 'error', 'response_summary'], ''),
        ];
    }

    gov_bridge_json_response([
        'ok' => true,
        'script' => 'bolt_jobs_queue.php',
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => [
            'submission_jobs_table_exists' => gov_bridge_table_exists($db, 'submission_jobs'),
            'submission_attempts_table_exists' => gov_bridge_table_exists($db, 'submission_attempts'),
            'jobs_returned' => count($normalizedJobs),
            'attempts_returned' => count($normalizedAttempts),
            'status_counts' => $statusCounts,
        ],
        'jobs' => $normalizedJobs,
        'attempts' => $normalizedAttempts,
        'note' => 'Read-only queue report. No EDXEIX submission was performed.',
    ]);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt_jobs_queue.php',
        'error' => $e->getMessage(),
    ], 500);
}
