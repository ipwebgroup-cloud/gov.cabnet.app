<?php
/**
 * gov.cabnet.app — EMT8640 existing V3 queue blocker.
 *
 * Purpose:
 * - Find any already-existing V3 pre-ride queue rows for vehicle EMT8640.
 * - Default to dry-run.
 * - With --commit, mark active/non-terminal V3 rows as blocked and record a V3-only event.
 *
 * Safety:
 * - V3 queue tables only.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No production submission_jobs/submission_attempts writes.
 * - Does not touch production pre-ride-email-tool.php.
 */

declare(strict_types=1);

const EMT8640_V3_BLOCKER_VERSION = 'v2026-05-13-emt8640-v3-existing-queue-blocker';
const EMT8640_PLATE_CANONICAL = 'EMT8640';
const EMT8640_BOLT_VEHICLE_IDENTIFIER = 'f9170acc-3bc4-43c5-9eed-65d9cadee490';
const EMT8640_BLOCK_REASON = 'vehicle_exempt_emt8640_no_voucher_no_driver_email_no_invoice';

/** @return array<string,mixed> */
function emt8640_options(array $argv): array
{
    $opts = [
        'commit' => false,
        'json' => false,
        'limit' => 200,
        'help' => false,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--commit') {
            $opts['commit'] = true;
        } elseif ($arg === '--json') {
            $opts['json'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $opts['help'] = true;
        } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
            $opts['limit'] = max(1, min(5000, (int)$m[1]));
        } else {
            fwrite(STDERR, "Unknown option: {$arg}\n");
            $opts['help'] = true;
        }
    }

    return $opts;
}

function emt8640_help(): string
{
    return <<<TEXT
EMT8640 existing V3 queue blocker

Usage:
  php /home/cabnet/gov.cabnet.app_app/cli/block_emt8640_existing_v3_queue_rows.php [options]

Options:
  --limit=N    Max V3 queue rows to inspect. Default: 200, max: 5000.
  --json       Output JSON.
  --commit     Block active EMT8640 V3 queue rows and write V3-only events.
  --help       Show this help.

Default mode is DRY RUN. It performs no database writes.
--commit writes only to pre_ride_email_v3_queue and pre_ride_email_v3_queue_events.
No EDXEIX call. No AADE call. No production submission tables.

TEXT;
}

/** @return mysqli */
function emt8640_db(): mysqli
{
    $bootstrap = dirname(__DIR__) . '/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        throw new RuntimeException('Missing bootstrap: ' . $bootstrap);
    }

    $ctx = require $bootstrap;
    if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
        throw new RuntimeException('Bootstrap did not return a usable DB context.');
    }

    $db = $ctx['db']->connection();
    if (!$db instanceof mysqli) {
        throw new RuntimeException('DB connection is not mysqli.');
    }

    return $db;
}

function emt8640_normalize_plate(string $plate): string
{
    $plate = strtoupper(trim($plate));
    return preg_replace('/[^A-Z0-9]/', '', $plate) ?? $plate;
}

/** @param array<string,mixed> $row */
function emt8640_row_matches(array $row): bool
{
    $plate = emt8640_normalize_plate((string)($row['vehicle_plate'] ?? ''));
    if ($plate === EMT8640_PLATE_CANONICAL) {
        return true;
    }

    $haystack = strtoupper(implode(' ', [
        (string)($row['vehicle_plate'] ?? ''),
        (string)($row['payload_json'] ?? ''),
        (string)($row['parsed_fields_json'] ?? ''),
        (string)($row['raw_email_preview'] ?? ''),
        (string)($row['operator_note'] ?? ''),
    ]));

    if (str_contains($haystack, EMT8640_PLATE_CANONICAL)) {
        return true;
    }

    return str_contains(strtolower($haystack), strtolower(EMT8640_BOLT_VEHICLE_IDENTIFIER));
}

function emt8640_table_exists(mysqli $db, string $table): bool
{
    $sql = "SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['c'] ?? 0) > 0;
}

/** @return array<int,array<string,mixed>> */
function emt8640_fetch_active_rows(mysqli $db, int $limit): array
{
    $sql = "SELECT
                id,
                dedupe_key,
                queue_status,
                lessor_id,
                vehicle_id,
                vehicle_plate,
                customer_name,
                driver_name,
                pickup_datetime,
                payload_json,
                parsed_fields_json,
                raw_email_preview,
                operator_note,
                created_at,
                updated_at
            FROM pre_ride_email_v3_queue
            WHERE queue_status NOT IN ('blocked','cancelled','failed','failed_permanent','expired','submitted')
            ORDER BY id DESC
            LIMIT ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Queue select prepare failed: ' . $db->error);
    }
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        if (is_array($row) && emt8640_row_matches($row)) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/** @param array<string,mixed> $row */
function emt8640_block_row(mysqli $db, array $row): array
{
    $id = (int)($row['id'] ?? 0);
    $dedupe = (string)($row['dedupe_key'] ?? '');
    if ($id <= 0 || $dedupe === '') {
        return ['ok' => false, 'id' => $id, 'error' => 'invalid_row_identity'];
    }

    $now = date('Y-m-d H:i:s');
    $note = '[' . $now . '] EMT8640 exemption blocker: ' . EMT8640_BLOCK_REASON;
    $lastError = EMT8640_BLOCK_REASON . ' — existing V3 queue row blocked by operator vehicle exemption.';

    $sql = "UPDATE pre_ride_email_v3_queue
            SET queue_status = 'blocked',
                failed_at = COALESCE(failed_at, NOW()),
                last_error = ?,
                operator_note = TRIM(CONCAT(COALESCE(operator_note, ''), CASE WHEN COALESCE(operator_note, '') = '' THEN '' ELSE '\n' END, ?))
            WHERE id = ?
              AND dedupe_key = ?
              AND queue_status NOT IN ('blocked','cancelled','failed','failed_permanent','expired','submitted')";
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return ['ok' => false, 'id' => $id, 'error' => 'update_prepare_failed: ' . $db->error];
    }
    $stmt->bind_param('ssis', $lastError, $note, $id, $dedupe);
    if (!$stmt->execute()) {
        return ['ok' => false, 'id' => $id, 'error' => 'update_execute_failed: ' . $stmt->error];
    }

    $affected = $stmt->affected_rows;
    if ($affected > 0) {
        $eventType = 'vehicle_exemption_blocked';
        $eventStatus = 'blocked';
        $message = 'Existing V3 queue row blocked because vehicle EMT8640 is permanently exempt: no voucher, no driver email, no invoice.';
        $context = json_encode([
            'version' => EMT8640_V3_BLOCKER_VERSION,
            'plate' => EMT8640_PLATE_CANONICAL,
            'bolt_vehicle_identifier' => EMT8640_BOLT_VEHICLE_IDENTIFIER,
            'previous_status' => (string)($row['queue_status'] ?? ''),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'driver_name' => (string)($row['driver_name'] ?? ''),
            'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        $createdBy = 'emt8640_v3_existing_queue_blocker';

        $evt = $db->prepare("INSERT INTO pre_ride_email_v3_queue_events (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($evt) {
            $queueId = (string)$id;
            $evt->bind_param('sssssss', $queueId, $dedupe, $eventType, $eventStatus, $message, $context, $createdBy);
            $evt->execute();
        }
    }

    return ['ok' => true, 'id' => $id, 'dedupe_key' => $dedupe, 'affected' => $affected];
}

/** @return array<string,mixed> */
function emt8640_run(array $argv): array
{
    $opts = emt8640_options($argv);
    if (!empty($opts['help'])) {
        return ['help' => true, 'text' => emt8640_help()];
    }

    $db = emt8640_db();
    $schema = [
        'queue' => emt8640_table_exists($db, 'pre_ride_email_v3_queue'),
        'events' => emt8640_table_exists($db, 'pre_ride_email_v3_queue_events'),
    ];
    $schemaOk = !in_array(false, $schema, true);
    if (!$schemaOk) {
        return [
            'help' => false,
            'summary' => [
                'ok' => false,
                'version' => EMT8640_V3_BLOCKER_VERSION,
                'mode' => !empty($opts['commit']) ? 'commit_v3_block_only' : 'dry_run_select_only',
                'schema_ok' => false,
                'schema' => $schema,
                'error' => 'V3 queue schema is not installed.',
            ],
            'rows' => [],
            'results' => [],
        ];
    }

    $rows = emt8640_fetch_active_rows($db, (int)$opts['limit']);
    $results = [];
    if (!empty($opts['commit'])) {
        foreach ($rows as $row) {
            $results[] = emt8640_block_row($db, $row);
        }
    }

    return [
        'help' => false,
        'summary' => [
            'ok' => true,
            'version' => EMT8640_V3_BLOCKER_VERSION,
            'mode' => !empty($opts['commit']) ? 'commit_v3_block_only' : 'dry_run_select_only',
            'database' => $db->query('SELECT DATABASE() AS db')->fetch_assoc()['db'] ?? '',
            'schema_ok' => true,
            'schema' => $schema,
            'limit' => (int)$opts['limit'],
            'matched_active_rows' => count($rows),
            'blocked_rows' => count(array_filter($results, static fn(array $r): bool => !empty($r['ok']) && (int)($r['affected'] ?? 0) > 0)),
            'safety' => [
                'v3_queue_only' => true,
                'edxeix_call' => false,
                'aade_call' => false,
                'production_submission_jobs' => false,
                'production_submission_attempts' => false,
            ],
        ],
        'rows' => array_map(static fn(array $row): array => [
            'id' => (int)($row['id'] ?? 0),
            'dedupe_key' => (string)($row['dedupe_key'] ?? ''),
            'queue_status' => (string)($row['queue_status'] ?? ''),
            'vehicle_plate' => (string)($row['vehicle_plate'] ?? ''),
            'customer_name' => (string)($row['customer_name'] ?? ''),
            'driver_name' => (string)($row['driver_name'] ?? ''),
            'pickup_datetime' => (string)($row['pickup_datetime'] ?? ''),
        ], $rows),
        'results' => $results,
    ];
}

function emt8640_print_text(array $out): void
{
    if (!empty($out['help'])) {
        echo (string)$out['text'];
        return;
    }

    $s = (array)($out['summary'] ?? []);
    echo "EMT8640 existing V3 queue blocker " . ($s['version'] ?? EMT8640_V3_BLOCKER_VERSION) . "\n";
    echo "Mode: " . ($s['mode'] ?? 'unknown') . "\n";
    echo "Database: " . ($s['database'] ?? '-') . "\n";
    echo "Schema OK: " . (!empty($s['schema_ok']) ? 'yes' : 'no') . "\n";
    if (empty($s['ok'])) {
        echo "ERROR: " . ($s['error'] ?? 'unknown') . "\n";
        return;
    }

    echo "Matched active EMT8640 V3 rows: " . ($s['matched_active_rows'] ?? 0) . " | Blocked: " . ($s['blocked_rows'] ?? 0) . "\n";
    echo "No EDXEIX call. No AADE call. No production submission tables.\n";
    if (($s['mode'] ?? '') === 'dry_run_select_only') {
        echo "DRY RUN only. Add --commit to block matched active V3 rows.\n";
    }

    foreach ((array)($out['rows'] ?? []) as $row) {
        echo "#" . ($row['id'] ?? '-') . " " . ($row['queue_status'] ?? '-') . " " . ($row['vehicle_plate'] ?? '-') . " | " . ($row['pickup_datetime'] ?? '-') . " | " . ($row['customer_name'] ?? '-') . " | " . ($row['driver_name'] ?? '-') . "\n";
    }
}

try {
    $out = emt8640_run($argv);
    $opts = emt8640_options($argv);
    if (!empty($opts['json'])) {
        echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        emt8640_print_text($out);
    }
    exit(empty($out['summary']['ok']) && empty($out['help']) ? 1 : 0);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'version' => EMT8640_V3_BLOCKER_VERSION,
        'error' => $e->getMessage(),
    ];
    $opts = emt8640_options($argv);
    if (!empty($opts['json'])) {
        echo json_encode($error, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    }
    exit(1);
}
