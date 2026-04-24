<?php
/**
 * gov.cabnet.app — Bolt/normalized booking → EDXEIX submission job staging.
 *
 * SAFETY:
 * - Does NOT call EDXEIX.
 * - Does NOT post a form.
 * - Default mode is dry-run only.
 * - create=1 only stages local records in submission_jobs.
 * - LAB rows are blocked unless allow_lab=1 is explicitly passed.
 *
 * Usage:
 *   /bolt_stage_edxeix_jobs.php
 *   /bolt_stage_edxeix_jobs.php?limit=30
 *   /bolt_stage_edxeix_jobs.php?create=1
 *   /bolt_stage_edxeix_jobs.php?create=1&allow_lab=1   // lab/dev only
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function gov_stage_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function gov_stage_terminal_status(string $status): bool
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

function gov_stage_booking_id(array $booking): string
{
    foreach (['id', 'booking_id', 'normalized_booking_id'] as $key) {
        if (isset($booking[$key]) && (string)$booking[$key] !== '') {
            return (string)$booking[$key];
        }
    }
    return '';
}

function gov_stage_order_reference(array $booking): string
{
    return (string)gov_stage_value($booking, ['order_reference', 'external_order_id', 'external_reference', 'source_trip_reference'], '');
}

function gov_stage_is_lab_row(array $booking): bool
{
    $source = strtolower((string)gov_stage_value($booking, ['source_system', 'source_type'], ''));
    $ref = strtoupper(gov_stage_order_reference($booking));
    return strpos($source, 'lab') !== false || strpos($ref, 'LAB-') === 0;
}

function gov_stage_analyze_booking(mysqli $db, array $booking, bool $allowLab): array
{
    $preview = gov_build_edxeix_preview_payload($db, $booking);
    $mapping = $preview['_mapping_status'] ?? [];

    $status = (string)gov_stage_value($booking, ['order_status', 'status'], '');
    $startedAt = (string)gov_stage_value($booking, ['started_at'], '');
    $source = (string)gov_stage_value($booking, ['source_system', 'source_type'], '');
    $orderRef = gov_stage_order_reference($booking);
    $bookingId = gov_stage_booking_id($booking);

    $driverMapped = !empty($mapping['driver_mapped']);
    $vehicleMapped = !empty($mapping['vehicle_mapped']);
    $futureGuard = !empty($mapping['passes_future_guard']);
    $terminal = gov_stage_terminal_status($status);
    $lab = gov_stage_is_lab_row($booking);

    $blockers = [];
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

    $submissionSafe = !$blockers;
    $hash = hash('sha256', json_encode([
        'normalized_booking_id' => $bookingId,
        'source_system' => $source,
        'order_reference' => $orderRef,
        'driver' => $preview['driver'] ?? '',
        'vehicle' => $preview['vehicle'] ?? '',
        'started_at' => $startedAt,
        'payload' => $preview,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    return [
        'booking' => $booking,
        'preview' => $preview,
        'booking_id' => $bookingId,
        'order_reference' => $orderRef,
        'source_system' => $source,
        'status' => $status,
        'started_at' => $startedAt,
        'driver_id' => (string)($preview['driver'] ?? ''),
        'vehicle_id' => (string)($preview['vehicle'] ?? ''),
        'mapping_ready' => $driverMapped && $vehicleMapped,
        'future_guard_passed' => $futureGuard,
        'terminal_status' => $terminal,
        'is_lab_row' => $lab,
        'submission_safe' => $submissionSafe,
        'blockers' => $blockers,
        'dedupe_hash' => $hash,
    ];
}

function gov_stage_existing_job(mysqli $db, array $analysis): ?array
{
    if (!gov_bridge_table_exists($db, 'submission_jobs')) {
        return null;
    }

    $columns = gov_bridge_table_columns($db, 'submission_jobs');
    $bookingId = $analysis['booking_id'];
    $hash = $analysis['dedupe_hash'];
    $orderRef = $analysis['order_reference'];

    if ($bookingId !== '' && isset($columns['normalized_booking_id'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM submission_jobs WHERE normalized_booking_id = ? LIMIT 1', [$bookingId]);
        if ($row) { return $row; }
    }
    if ($bookingId !== '' && isset($columns['booking_id'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM submission_jobs WHERE booking_id = ? LIMIT 1', [$bookingId]);
        if ($row) { return $row; }
    }
    if (isset($columns['dedupe_hash'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM submission_jobs WHERE dedupe_hash = ? LIMIT 1', [$hash]);
        if ($row) { return $row; }
    }
    if (isset($columns['payload_hash'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM submission_jobs WHERE payload_hash = ? LIMIT 1', [$hash]);
        if ($row) { return $row; }
    }
    if ($orderRef !== '' && isset($columns['order_reference'])) {
        $row = gov_bridge_fetch_one($db, 'SELECT * FROM submission_jobs WHERE order_reference = ? LIMIT 1', [$orderRef]);
        if ($row) { return $row; }
    }

    return null;
}

function gov_stage_job_row(array $analysis): array
{
    $now = date('Y-m-d H:i:s');
    $payload = $analysis['preview'];
    $payloadJson = gov_bridge_json_encode_db($payload);
    $source = $analysis['source_system'] ?: 'bolt';

    return [
        'source_system' => $source,
        'source_type' => $source,
        'job_type' => 'edxeix_form_submit',
        'type' => 'edxeix_form_submit',
        'status' => 'staged_dry_run',
        'state' => 'staged_dry_run',
        'job_status' => 'staged_dry_run',
        'normalized_booking_id' => $analysis['booking_id'],
        'booking_id' => $analysis['booking_id'],
        'external_order_id' => $analysis['order_reference'],
        'order_reference' => $analysis['order_reference'],
        'edxeix_driver_id' => $analysis['driver_id'],
        'edxeix_vehicle_id' => $analysis['vehicle_id'],
        'payload_hash' => $analysis['dedupe_hash'],
        'dedupe_hash' => $analysis['dedupe_hash'],
        'payload_json' => $payloadJson,
        'request_payload_json' => $payloadJson,
        'edxeix_payload_json' => $payloadJson,
        'notes' => 'STAGED ONLY. No EDXEIX HTTP request has been made by this script.',
        'created_at' => $now,
        'updated_at' => $now,
        'queued_at' => $now,
    ];
}

function gov_stage_insert_or_update_job(mysqli $db, array $analysis, bool $dryRun): array
{
    if (!gov_bridge_table_exists($db, 'submission_jobs')) {
        return [
            'ok' => false,
            'action' => 'blocked',
            'reason' => 'submission_jobs table does not exist',
        ];
    }

    $existing = gov_stage_existing_job($db, $analysis);
    if ($dryRun) {
        return [
            'ok' => true,
            'action' => $existing ? 'would_keep_existing_job' : 'would_stage_job',
            'existing_job_id' => $existing['id'] ?? null,
        ];
    }

    if ($existing) {
        $update = gov_stage_job_row($analysis);
        unset($update['created_at']);
        gov_bridge_update_row($db, 'submission_jobs', $update, 'id = ?', [(string)$existing['id']]);
        return [
            'ok' => true,
            'action' => 'updated_existing_job',
            'job_id' => $existing['id'] ?? null,
        ];
    }

    $id = gov_bridge_insert_row($db, 'submission_jobs', gov_stage_job_row($analysis));
    return [
        'ok' => true,
        'action' => 'staged_job',
        'job_id' => $id,
    ];
}

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $db = gov_bridge_db();
    $create = gov_bridge_bool_param('create', false);
    $allowLab = gov_bridge_bool_param('allow_lab', false);
    $limit = gov_bridge_int_param('limit', 30, 1, 200);

    $bookings = gov_recent_rows($db, 'normalized_bookings', $limit);
    $rows = [];
    $summary = [
        'checked' => 0,
        'eligible' => 0,
        'blocked' => 0,
        'staged_or_would_stage' => 0,
        'existing_or_would_keep' => 0,
    ];

    foreach ($bookings as $booking) {
        $analysis = gov_stage_analyze_booking($db, $booking, $allowLab);
        $summary['checked']++;

        $job = null;
        if ($analysis['submission_safe']) {
            $summary['eligible']++;
            $job = gov_stage_insert_or_update_job($db, $analysis, !$create);
            if (in_array($job['action'] ?? '', ['staged_job', 'would_stage_job', 'updated_existing_job'], true)) {
                $summary['staged_or_would_stage']++;
            }
            if (in_array($job['action'] ?? '', ['would_keep_existing_job'], true)) {
                $summary['existing_or_would_keep']++;
            }
        } else {
            $summary['blocked']++;
        }

        $rows[] = [
            'booking_id' => $analysis['booking_id'],
            'order_reference' => $analysis['order_reference'],
            'source_system' => $analysis['source_system'],
            'status' => $analysis['status'],
            'started_at' => $analysis['started_at'],
            'driver_id' => $analysis['driver_id'],
            'vehicle_id' => $analysis['vehicle_id'],
            'mapping_ready' => $analysis['mapping_ready'],
            'future_guard_passed' => $analysis['future_guard_passed'],
            'terminal_status' => $analysis['terminal_status'],
            'is_lab_row' => $analysis['is_lab_row'],
            'submission_safe' => $analysis['submission_safe'],
            'blockers' => $analysis['blockers'],
            'job' => $job,
            'edxeix_payload_preview' => $analysis['preview'],
        ];
    }

    gov_bridge_json_response([
        'ok' => true,
        'script' => 'bolt_stage_edxeix_jobs.php',
        'mode' => $create ? 'create_local_staged_jobs' : 'dry_run_only',
        'allow_lab' => $allowLab,
        'generated_at' => date('Y-m-d H:i:s'),
        'summary' => $summary,
        'rows' => $rows,
        'note' => 'No EDXEIX submission was performed. create=1 stages local submission_jobs records only. LAB rows require allow_lab=1 and still must never be sent live.',
    ]);
} catch (Throwable $e) {
    gov_bridge_json_response([
        'ok' => false,
        'script' => 'bolt_stage_edxeix_jobs.php',
        'error' => $e->getMessage(),
    ], 500);
}
