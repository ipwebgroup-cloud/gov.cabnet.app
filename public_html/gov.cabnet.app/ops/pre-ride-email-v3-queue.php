<?php
/**
 * gov.cabnet.app — V3 Pre-Ride Email Queue Dashboard
 *
 * Independent read-only V3 queue visibility page.
 * Does not modify /ops/pre-ride-email-tool.php or /ops/pre-ride-email-toolv3.php.
 *
 * Safety:
 * - SELECT only.
 * - No queue mutations.
 * - No production submission_jobs/submission_attempts access.
 * - No EDXEIX calls.
 * - No AADE calls.
 */

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

const V3Q_PAGE_VERSION = 'v3.0.14-submit-control-panel';

function v3q_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3q_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . v3q_h($type) . '">' . v3q_h($text) . '</span>';
}

function v3q_private_file(string $relative): string
{
    $relative = ltrim($relative, '/');
    $candidates = [
        dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . $relative,
        dirname(__DIR__, 2) . '/gov.cabnet.app_app/' . $relative,
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            return $file;
        }
    }
    return $candidates[0];
}

function v3q_app_context(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = v3q_private_file('src/bootstrap.php');
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            $loadError = 'Private app bootstrap did not return a usable DB context.';
            $error = $loadError;
            return null;
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function v3q_table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('s', $table);
    $stmt->execute();
    return (bool)$stmt->get_result()->fetch_assoc();
}

/** @return array<int,array<string,mixed>> */
function v3q_fetch_all(mysqli $db, string $sql): array
{
    $rows = [];
    $res = $db->query($sql);
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

/** @return array<int,array<string,mixed>> */
function v3q_fetch_queue_rows(mysqli $db, string $status, int $limit, int $offset): array
{
    $allowedStatuses = ['all', 'queued', 'ready', 'submit_dry_run_ready', 'locked', 'submitted', 'failed', 'blocked', 'needs_review', 'cancelled'];
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'all';
    }

    if ($status === 'all') {
        $stmt = $db->prepare('SELECT * FROM pre_ride_email_v3_queue ORDER BY COALESCE(pickup_datetime, created_at) DESC, id DESC LIMIT ? OFFSET ?');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('ii', $limit, $offset);
    } else {
        $stmt = $db->prepare('SELECT * FROM pre_ride_email_v3_queue WHERE queue_status = ? ORDER BY COALESCE(pickup_datetime, created_at) DESC, id DESC LIMIT ? OFFSET ?');
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('sii', $status, $limit, $offset);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function v3q_fetch_queue_row(mysqli $db, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    $stmt = $db->prepare('SELECT * FROM pre_ride_email_v3_queue WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return is_array($row) ? $row : null;
}

/** @return array<int,array<string,mixed>> */
function v3q_fetch_events(mysqli $db, int $queueId, string $dedupeKey): array
{
    $stmt = $db->prepare('SELECT * FROM pre_ride_email_v3_queue_events WHERE queue_id = ? OR dedupe_key = ? ORDER BY created_at DESC, id DESC LIMIT 50');
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('is', $queueId, $dedupeKey);
    $stmt->execute();
    $rows = [];
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function v3q_pretty_json($value): string
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $decoded = json_decode($value, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return (string)json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
    return $value;
}

function v3q_status_badge(string $status): string
{
    $status = trim($status) ?: 'unknown';
    $type = match ($status) {
        'queued', 'ready', 'submit_dry_run_ready' => 'good',
        'submitted' => 'neutral',
        'failed', 'blocked' => 'bad',
        'locked', 'needs_review' => 'warn',
        default => 'neutral',
    };
    return v3q_badge($status, $type);
}

function v3q_yes_no_badge($value, string $yes = 'yes', string $no = 'no'): string
{
    return !empty($value) ? v3q_badge($yes, 'good') : v3q_badge($no, 'bad');
}

function v3q_csrf_token(): string
{
    if (empty($_SESSION['v3q_csrf']) || !is_string($_SESSION['v3q_csrf'])) {
        $_SESSION['v3q_csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['v3q_csrf'];
}

function v3q_check_csrf(): bool
{
    $posted = (string)($_POST['csrf_token'] ?? '');
    $stored = (string)($_SESSION['v3q_csrf'] ?? '');
    return $posted !== '' && $stored !== '' && hash_equals($stored, $posted);
}

function v3q_redirect_after_action(int $id, string $status, string $result, string $message): never
{
    $params = [
        'id' => (string)$id,
        'status' => $status !== '' ? $status : 'all',
        'action_result' => $result,
        'action_message' => $message,
    ];
    header('Location: /ops/pre-ride-email-v3-queue.php?' . http_build_query($params));
    exit;
}

function v3q_insert_event(mysqli $db, int $queueId, string $dedupeKey, string $type, string $status, string $message, array $context = []): void
{
    $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $createdBy = 'v3_dashboard';
    $stmt = $db->prepare('INSERT INTO pre_ride_email_v3_queue_events (queue_id, dedupe_key, event_type, event_status, event_message, event_context_json, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('issssss', $queueId, $dedupeKey, $type, $status, $message, $contextJson, $createdBy);
    $stmt->execute();
}

function v3q_update_operator_note(mysqli $db, int $id, string $note): bool
{
    if ($note === '') {
        return true;
    }
    $stmt = $db->prepare("UPDATE pre_ride_email_v3_queue SET operator_note = CASE WHEN operator_note IS NULL OR operator_note = '' THEN ? ELSE CONCAT(operator_note, '\n', ?) END WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('ssi', $note, $note, $id);
    return $stmt->execute();
}

function v3q_apply_operator_action(mysqli $db, array $row, string $action, string $note): array
{
    $id = (int)($row['id'] ?? 0);
    $dedupeKey = (string)($row['dedupe_key'] ?? '');
    $note = trim(mb_substr($note, 0, 1000, 'UTF-8'));
    $stamp = date('Y-m-d H:i:s');
    $noteLine = $note !== '' ? '[' . $stamp . '] ' . $note : '[' . $stamp . '] ' . $action;

    if ($id <= 0 || $dedupeKey === '') {
        return [false, 'Invalid queue row.'];
    }

    if ($action === 'mark_reviewed') {
        $ok = v3q_update_operator_note($db, $id, $noteLine);
        v3q_insert_event($db, $id, $dedupeKey, 'operator_reviewed', $ok ? 'ok' : 'failed', $note !== '' ? $note : 'Operator marked row reviewed.', ['action' => $action]);
        return [$ok, $ok ? 'Row marked reviewed with V3-only event.' : 'Could not mark row reviewed.'];
    }

    if ($action === 'block_row') {
        $reason = $note !== '' ? $note : 'Operator blocked row from V3 dashboard.';
        $stmt = $db->prepare("UPDATE pre_ride_email_v3_queue SET queue_status = 'blocked', failed_at = NOW(), last_error = ?, operator_note = CASE WHEN operator_note IS NULL OR operator_note = '' THEN ? ELSE CONCAT(operator_note, '\n', ?) END WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return [false, 'Could not prepare block update.'];
        }
        $noteForDb = '[' . $stamp . '] BLOCKED: ' . $reason;
        $stmt->bind_param('sssi', $reason, $noteForDb, $noteForDb, $id);
        $ok = $stmt->execute();
        v3q_insert_event($db, $id, $dedupeKey, 'operator_blocked', $ok ? 'blocked' : 'failed', $reason, ['action' => $action]);
        return [$ok, $ok ? 'Row blocked in V3 queue only.' : 'Could not block row.'];
    }

    if ($action === 'reset_to_queued') {
        $stmt = $db->prepare("UPDATE pre_ride_email_v3_queue SET queue_status = 'queued', locked_at = NULL, submitted_at = NULL, failed_at = NULL, last_error = NULL WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return [false, 'Could not prepare reset update.'];
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        v3q_update_operator_note($db, $id, '[' . $stamp . '] RESET: ' . ($note !== '' ? $note : 'Operator reset row to queued.'));
        v3q_insert_event($db, $id, $dedupeKey, 'operator_reset_to_queued', $ok ? 'queued' : 'failed', $note !== '' ? $note : 'Operator reset row to queued.', ['action' => $action]);
        return [$ok, $ok ? 'Row reset to queued in V3 queue only.' : 'Could not reset row.'];
    }

    if ($action === 'mark_submit_dry_run_ready') {
        [$eligible, $reasons] = v3q_helper_eligibility($row);
        if (!$eligible) {
            v3q_insert_event($db, $id, $dedupeKey, 'operator_submit_dry_run_ready_blocked', 'blocked', implode('; ', $reasons), ['action' => $action, 'reasons' => $reasons]);
            return [false, 'Dry-run ready action blocked: ' . implode('; ', $reasons)];
        }
        $stmt = $db->prepare("UPDATE pre_ride_email_v3_queue SET queue_status = 'submit_dry_run_ready', locked_at = NULL, last_error = NULL WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return [false, 'Could not prepare dry-run ready update.'];
        }
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        v3q_update_operator_note($db, $id, '[' . $stamp . '] SUBMIT DRY-RUN READY: ' . ($note !== '' ? $note : 'Operator marked row ready for submit dry-run stage.'));
        v3q_insert_event($db, $id, $dedupeKey, 'operator_marked_submit_dry_run_ready', $ok ? 'submit_dry_run_ready' : 'failed', $note !== '' ? $note : 'Operator marked row ready for submit dry-run stage.', ['action' => $action]);
        return [$ok, $ok ? 'Row marked submit_dry_run_ready in V3 queue only.' : 'Could not mark row dry-run ready.'];
    }

    return [false, 'Unsupported action.'];
}


function v3q_log_file(string $relative): string
{
    $relative = ltrim($relative, '/');
    $candidates = [
        dirname(__DIR__, 3) . '/gov.cabnet.app_app/' . $relative,
        dirname(__DIR__, 2) . '/gov.cabnet.app_app/' . $relative,
    ];
    foreach ($candidates as $file) {
        if (is_file($file)) {
            return $file;
        }
    }
    return $candidates[0];
}

function v3q_tail_file(string $file, int $maxBytes = 16000): string
{
    if (!is_file($file) || !is_readable($file)) {
        return '';
    }
    $size = filesize($file);
    if ($size === false || $size <= 0) {
        return '';
    }
    $handle = fopen($file, 'rb');
    if (!$handle) {
        return '';
    }
    $seek = max(0, $size - $maxBytes);
    if ($seek > 0) {
        fseek($handle, $seek);
    }
    $data = stream_get_contents($handle);
    fclose($handle);
    return is_string($data) ? trim($data) : '';
}

/**
 * @return array<string,mixed>
 */
function v3q_cron_health(): array
{
    $file = v3q_log_file('logs/pre_ride_email_v3_cron.log');
    $exists = is_file($file);
    $readable = $exists && is_readable($file);
    $mtime = $exists ? (filemtime($file) ?: 0) : 0;
    $age = $mtime > 0 ? (time() - $mtime) : null;
    $tail = $readable ? v3q_tail_file($file) : '';

    $lastSummary = '';
    $lastStart = '';
    $lastFinish = '';
    $lastBlocked = [];
    if ($tail !== '') {
        $lines = preg_split('/\R/', $tail) ?: [];
        foreach ($lines as $line) {
            $line = trim((string)$line);
            if ($line === '') { continue; }
            if (str_contains($line, 'V3 cron worker start')) { $lastStart = $line; }
            if (str_contains($line, 'SUMMARY')) { $lastSummary = $line; }
            if (str_contains($line, 'BLOCKED')) { $lastBlocked[] = $line; }
            if (str_contains($line, 'V3 cron worker finish')) { $lastFinish = $line; }
        }
    }

    $status = 'missing';
    $statusType = 'bad';
    $message = 'Cron log file was not found yet.';
    if ($exists && !$readable) {
        $status = 'unreadable';
        $statusType = 'warn';
        $message = 'Cron log exists but is not readable by this page.';
    } elseif ($readable) {
        if ($age !== null && $age <= 180) {
            $status = 'fresh';
            $statusType = 'good';
            $message = 'Cron log was updated recently.';
        } elseif ($age !== null && $age <= 900) {
            $status = 'stale';
            $statusType = 'warn';
            $message = 'Cron log is older than 3 minutes. Check whether cPanel cron is still active.';
        } else {
            $status = 'old';
            $statusType = 'bad';
            $message = 'Cron log is old or has no timestamp. Check the cron job.';
        }
    }

    return [
        'file' => $file,
        'safe_file' => 'gov.cabnet.app_app/logs/pre_ride_email_v3_cron.log',
        'exists' => $exists,
        'readable' => $readable,
        'mtime' => $mtime,
        'age_seconds' => $age,
        'status' => $status,
        'status_type' => $statusType,
        'message' => $message,
        'last_summary' => $lastSummary,
        'last_start' => $lastStart,
        'last_finish' => $lastFinish,
        'last_blocked' => array_slice(array_reverse($lastBlocked), 0, 3),
        'tail' => $tail,
    ];
}


/**
 * @return array<string,mixed>
 */
function v3q_payload_from_queue_row(array $row): array
{
    $decoded = [];
    $payloadJson = trim((string)($row['payload_json'] ?? ''));
    if ($payloadJson !== '') {
        $tmp = json_decode($payloadJson, true);
        if (is_array($tmp)) {
            $decoded = $tmp;
        }
    }

    $pickupDateTime = trim((string)($row['pickup_datetime'] ?? ''));
    $endDateTime = trim((string)($row['estimated_end_datetime'] ?? ''));
    $pickupDateIso = '';
    $pickupTime = '';
    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}:\d{2})(?::\d{2})?/', $pickupDateTime, $m) === 1) {
        $pickupDateIso = $m[1];
        $pickupTime = $m[2] . ':00';
    }

    $payload = array_merge($decoded, [
        'source' => 'gov.cabnet.app V3 queue dashboard',
        'queueId' => (string)($row['id'] ?? ''),
        'dedupeKey' => (string)($row['dedupe_key'] ?? ''),
        'lessorId' => (string)($row['lessor_id'] ?? ''),
        'lessorSource' => (string)($row['lessor_source'] ?? ''),
        'driverId' => (string)($row['driver_id'] ?? ''),
        'vehicleId' => (string)($row['vehicle_id'] ?? ''),
        'startingPointId' => (string)($row['starting_point_id'] ?? ''),
        'passengerName' => (string)($row['customer_name'] ?? ''),
        'passengerPhone' => (string)($row['customer_phone'] ?? ''),
        'customerName' => (string)($row['customer_name'] ?? ''),
        'customerPhone' => (string)($row['customer_phone'] ?? ''),
        'driver' => (string)($row['driver_name'] ?? ''),
        'driverName' => (string)($row['driver_name'] ?? ''),
        'vehicle' => (string)($row['vehicle_plate'] ?? ''),
        'vehiclePlate' => (string)($row['vehicle_plate'] ?? ''),
        'pickupAddress' => (string)($row['pickup_address'] ?? ''),
        'dropoffAddress' => (string)($row['dropoff_address'] ?? ''),
        'pickupDateIso' => $decoded['pickupDateIso'] ?? $pickupDateIso,
        'pickupTime' => $decoded['pickupTime'] ?? $pickupTime,
        'pickupDateTime' => $decoded['pickupDateTime'] ?? $pickupDateTime,
        'endDateTime' => $decoded['endDateTime'] ?? $endDateTime,
        'priceText' => (string)($row['price_text'] ?? ''),
        'priceAmount' => (string)($row['price_amount'] ?? ''),
        'orderReference' => (string)($row['order_reference'] ?? ''),
    ]);

    $payload['savedAt'] = date(DATE_ATOM);
    return $payload;
}

/**
 * @return array{0:bool,1:array<int,string>}
 */
function v3q_helper_eligibility(array $row): array
{
    $reasons = [];
    if (empty($row['parser_ok'])) { $reasons[] = 'parser gate is not OK'; }
    if (empty($row['mapping_ok'])) { $reasons[] = 'mapping gate is not OK'; }
    if (empty($row['future_ok'])) { $reasons[] = 'future gate is not OK'; }
    foreach (['lessor_id', 'driver_id', 'vehicle_id', 'starting_point_id'] as $key) {
        if (trim((string)($row[$key] ?? '')) === '') {
            $reasons[] = $key . ' is missing';
        }
    }
    $pickup = trim((string)($row['pickup_datetime'] ?? ''));
    if ($pickup === '') {
        $reasons[] = 'pickup datetime is missing';
    } else {
        try {
            $pickupTs = strtotime($pickup);
            if ($pickupTs === false) {
                $reasons[] = 'pickup datetime is invalid';
            } elseif ($pickupTs <= time()) {
                $reasons[] = 'pickup is no longer in the future';
            }
        } catch (Throwable) {
            $reasons[] = 'pickup datetime could not be checked';
        }
    }
    return [count($reasons) === 0, $reasons];
}

$ctxError = null;
$ctx = v3q_app_context($ctxError);
$db = null;
$dbName = '';
$schemaOk = false;
$tableQueue = false;
$tableEvents = false;
$summaryRows = [];
$upcomingRows = [];
$recentRows = [];
$events = [];
$selectedRow = null;
$error = null;
$cronHealth = v3q_cron_health();
$actionResult = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['action_result'] ?? ''));
$actionMessage = trim((string)($_GET['action_message'] ?? ''));

$status = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_GET['status'] ?? 'all')) ?: 'all';
$limit = (int)($_GET['limit'] ?? 50);
$limit = max(10, min(200, $limit));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$selectedId = max(0, (int)($_GET['id'] ?? 0));

if (!$ctx) {
    $error = $ctxError ?: 'DB context unavailable.';
} else {
    try {
        $db = $ctx['db']->connection();
        $dbNameRow = $db->query('SELECT DATABASE() AS db_name');
        if ($dbNameRow) {
            $tmp = $dbNameRow->fetch_assoc();
            $dbName = (string)($tmp['db_name'] ?? '');
        }
        $tableQueue = v3q_table_exists($db, 'pre_ride_email_v3_queue');
        $tableEvents = v3q_table_exists($db, 'pre_ride_email_v3_queue_events');
        $schemaOk = $tableQueue && $tableEvents;

        if ($schemaOk) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $postId = max(0, (int)($_POST['queue_id'] ?? 0));
                $postAction = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_POST['queue_action'] ?? '')) ?: '';
                $postStatus = preg_replace('/[^a-z0-9_\-]/i', '', (string)($_POST['return_status'] ?? $status)) ?: 'all';
                $postNote = (string)($_POST['operator_note'] ?? '');

                if (!v3q_check_csrf()) {
                    v3q_redirect_after_action($postId, $postStatus, 'bad', 'CSRF check failed. Reload the page and try again.');
                }

                $postRow = v3q_fetch_queue_row($db, $postId);
                if (!$postRow) {
                    v3q_redirect_after_action($postId, $postStatus, 'bad', 'Queue row was not found.');
                }

                [$ok, $message] = v3q_apply_operator_action($db, $postRow, $postAction, $postNote);
                v3q_redirect_after_action($postId, $postStatus, $ok ? 'good' : 'bad', $message);
            }

            $summaryRows = v3q_fetch_all($db, "
                SELECT queue_status, COUNT(*) AS total,
                       SUM(CASE WHEN pickup_datetime IS NOT NULL AND pickup_datetime >= NOW() THEN 1 ELSE 0 END) AS future_count,
                       SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS submitted_count,
                       MIN(pickup_datetime) AS first_pickup,
                       MAX(pickup_datetime) AS last_pickup
                FROM pre_ride_email_v3_queue
                GROUP BY queue_status
                ORDER BY total DESC, queue_status ASC
            ");
            $upcomingRows = v3q_fetch_all($db, "
                SELECT id, dedupe_key, queue_status, pickup_datetime, customer_name, driver_name, vehicle_plate, lessor_id, driver_id, vehicle_id, starting_point_id
                FROM pre_ride_email_v3_queue
                WHERE pickup_datetime IS NOT NULL AND pickup_datetime >= NOW()
                ORDER BY pickup_datetime ASC, id ASC
                LIMIT 10
            ");
            $recentRows = v3q_fetch_queue_rows($db, $status, $limit, $offset);
            if ($selectedId > 0) {
                $selectedRow = v3q_fetch_queue_row($db, $selectedId);
                if ($selectedRow) {
                    $events = v3q_fetch_events($db, (int)$selectedRow['id'], (string)($selectedRow['dedupe_key'] ?? ''));
                }
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$totalRows = 0;
$futureRows = 0;
$submittedRows = 0;
foreach ($summaryRows as $srow) {
    $totalRows += (int)($srow['total'] ?? 0);
    $futureRows += (int)($srow['future_count'] ?? 0);
    $submittedRows += (int)($srow['submitted_count'] ?? 0);
}

$selectedPayload = null;
$selectedPayloadJson = 'null';
$helperEligible = false;
$helperBlockReasons = [];
$helperBlockReasonsJson = '[]';
if (is_array($selectedRow)) {
    $selectedPayload = v3q_payload_from_queue_row($selectedRow);
    $selectedPayloadJson = (string)json_encode($selectedPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    [$helperEligible, $helperBlockReasons] = v3q_helper_eligibility($selectedRow);
    $helperBlockReasonsJson = (string)json_encode($helperBlockReasons, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>V3 Pre-Ride Email Queue | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#061735;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--purple:#6d28d9;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:14px;white-space:nowrap;opacity:.94}.nav a:hover{text-decoration:underline;opacity:1}.wrap{width:min(1480px,calc(100% - 48px));margin:22px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--purple)}h1{font-size:32px;margin:0 0 10px}h2{font-size:22px;margin:0 0 14px}h3{font-size:18px;margin:18px 0 10px}p{color:var(--muted);line-height:1.45}.small{font-size:13px;color:var(--muted)}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.metrics{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:86px}.metric strong{display:block;font-size:28px;line-height:1.08;word-break:break-word}.metric span{color:var(--muted);font-size:13px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}.table{width:100%;border-collapse:collapse;min-width:900px}.table th,.table td{border-bottom:1px solid var(--line);padding:9px 10px;text-align:left;vertical-align:top;font-size:13px}.table th{background:#f8fafc;color:#12305d}.table tr:hover td{background:#fbfdff}.mini{font-size:12px;color:#18315d;line-height:1.35}.actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.btn{display:inline-block;border:0;padding:10px 13px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:13px;cursor:pointer}.btn.light{background:#eaf1ff;color:#1e40af}.btn.dark{background:#334155}.btn.purple{background:var(--purple)}select,input{border:1px solid var(--line);border-radius:8px;padding:9px 10px;font-size:14px;background:#fff;color:var(--ink)}.okbox{background:#ecfdf3;border:1px solid #bbf7d0;border-radius:10px;padding:12px;color:#065f46}.warnbox{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;color:#9a3412}.badbox{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:12px;color:#991b1b}.code-box{width:100%;min-height:180px;border:1px solid var(--line);border-radius:10px;background:#0b1220;color:#dbeafe;font-family:Consolas,Menlo,Monaco,monospace;font-size:12px;line-height:1.35;padding:12px;resize:vertical}.kv{display:grid;grid-template-columns:180px 1fr;gap:6px 12px;font-size:13px}.kv strong{color:#102a55}.statusline{display:flex;gap:8px;flex-wrap:wrap;align-items:center}@media(max-width:980px){.wrap{width:calc(100% - 24px);margin-top:14px}.metrics,.two{grid-template-columns:1fr}.nav{padding:0 14px}.kv{grid-template-columns:1fr}.table{min-width:760px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/pre-ride-email-tool.php">Production Tool</a>
    <a href="/ops/pre-ride-email-toolv3.php">V3 Intake</a>
    <a href="/ops/pre-ride-email-toolv3.php?watch=1">V3 Watch</a>
    <a href="/ops/pre-ride-email-v3-queue.php">V3 Queue</a>
</nav>

<main class="wrap">
    <section class="card hero">
        <h1>V3 Pre-Ride Email Queue</h1>
        <p>Visibility, operator controls, and helper handoff for the isolated V3 queue tables. This page may write operator actions only to V3 queue/events tables; it does not submit to EDXEIX, does not issue AADE, and does not touch the production pre-ride email tool.</p>
        <div>
            <?= v3q_badge('V3 ISOLATED', 'purple') ?>
            <?= v3q_badge('V3 DB WRITES ONLY', 'warn') ?>
            <?= v3q_badge('HELPER HANDOFF ONLY', 'purple') ?>
            <?= v3q_badge('NO EDXEIX CALL', 'good') ?>
            <?= v3q_badge('NO AADE CALL', 'good') ?>
            <?= v3q_badge(V3Q_PAGE_VERSION, 'neutral') ?>
        </div>
    </section>

    <?php if ($error): ?>
        <section class="card badbox"><strong>Error:</strong> <?= v3q_h($error) ?></section>
    <?php endif; ?>
    <?php if ($actionMessage !== ''): ?>
        <section class="card <?= $actionResult === 'good' ? 'okbox' : 'badbox' ?>"><strong>V3 operator action:</strong> <?= v3q_h($actionMessage) ?></section>
    <?php endif; ?>

    <section class="card">
        <h2>Queue foundation status</h2>
        <div class="statusline">
            <strong>Database:</strong> <code><?= v3q_h($dbName ?: '-') ?></code>
            <?= $schemaOk ? v3q_badge('schema installed', 'good') : v3q_badge('schema missing', 'warn') ?>
            <?= $tableQueue ? v3q_badge('queue table OK', 'good') : v3q_badge('queue table missing', 'bad') ?>
            <?= $tableEvents ? v3q_badge('events table OK', 'good') : v3q_badge('events table missing', 'bad') ?>
        </div>
        <?php if (!$schemaOk): ?>
            <p class="small">Run the V3 queue foundation SQL first. This page will remain read-only after the schema is installed.</p>
        <?php endif; ?>
    </section>


    <section class="card">
        <h2>V3 cron intake health</h2>
        <div class="statusline">
            <?= v3q_badge('cron ' . (string)$cronHealth['status'], (string)$cronHealth['status_type']) ?>
            <strong>Log:</strong> <code><?= v3q_h($cronHealth['safe_file'] ?? '') ?></code>
            <?php if (!empty($cronHealth['mtime'])): ?>
                <span class="small">Last update: <?= v3q_h(date('Y-m-d H:i:s', (int)$cronHealth['mtime'])) ?> · age <?= v3q_h((string)($cronHealth['age_seconds'] ?? '')) ?>s</span>
            <?php endif; ?>
        </div>
        <p class="small"><?= v3q_h($cronHealth['message'] ?? '') ?></p>
        <?php if (!empty($cronHealth['last_summary'])): ?>
            <div class="okbox"><strong>Last summary</strong><br><code><?= v3q_h($cronHealth['last_summary']) ?></code></div>
        <?php elseif (!empty($cronHealth['exists'])): ?>
            <div class="warnbox"><strong>No SUMMARY line found yet.</strong> Wait for the next cron run or run the worker once manually.</div>
        <?php else: ?>
            <div class="warnbox"><strong>No cron log yet.</strong> Confirm the cPanel cron line is active and writes to the expected log file.</div>
        <?php endif; ?>
        <?php if (!empty($cronHealth['last_blocked'])): ?>
            <h3>Last blocked examples</h3>
            <ul class="mini">
                <?php foreach ($cronHealth['last_blocked'] as $line): ?><li><code><?= v3q_h($line) ?></code></li><?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <details>
            <summary class="small">Show cron log tail</summary>
            <textarea class="code-box" readonly><?= v3q_h($cronHealth['tail'] ?? '') ?></textarea>
        </details>
    </section>

    <?php if ($schemaOk): ?>
    <section class="card">
        <h2>Summary</h2>
        <div class="metrics">
            <div class="metric"><strong><?= v3q_h((string)$totalRows) ?></strong><span>Total V3 queue rows</span></div>
            <div class="metric"><strong><?= v3q_h((string)$futureRows) ?></strong><span>Future pickups in queue</span></div>
            <div class="metric"><strong><?= v3q_h((string)$submittedRows) ?></strong><span>Rows marked submitted</span></div>
            <div class="metric"><strong><?= v3q_h((string)count($events)) ?></strong><span>Events for selected row</span></div>
        </div>

        <h3>Status counts</h3>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Status</th><th>Total</th><th>Future</th><th>Submitted</th><th>First pickup</th><th>Last pickup</th></tr></thead>
                <tbody>
                <?php if (empty($summaryRows)): ?>
                    <tr><td colspan="6">No V3 queue rows yet.</td></tr>
                <?php else: foreach ($summaryRows as $row): ?>
                    <tr>
                        <td><?= v3q_status_badge((string)($row['queue_status'] ?? '')) ?></td>
                        <td><?= v3q_h($row['total'] ?? '0') ?></td>
                        <td><?= v3q_h($row['future_count'] ?? '0') ?></td>
                        <td><?= v3q_h($row['submitted_count'] ?? '0') ?></td>
                        <td><?= v3q_h($row['first_pickup'] ?? '') ?></td>
                        <td><?= v3q_h($row['last_pickup'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="two">
        <section class="card">
            <h2>Upcoming queued rides</h2>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>ID</th><th>Status</th><th>Pickup</th><th>Transfer</th><th>IDs</th></tr></thead>
                    <tbody>
                    <?php if (empty($upcomingRows)): ?>
                        <tr><td colspan="5">No future queue rows yet.</td></tr>
                    <?php else: foreach ($upcomingRows as $row): ?>
                        <tr>
                            <td><a href="?id=<?= v3q_h($row['id'] ?? '') ?>">#<?= v3q_h($row['id'] ?? '') ?></a></td>
                            <td><?= v3q_status_badge((string)($row['queue_status'] ?? '')) ?></td>
                            <td><?= v3q_h($row['pickup_datetime'] ?? '') ?></td>
                            <td class="mini"><?= v3q_h($row['customer_name'] ?? '') ?><br><?= v3q_h($row['driver_name'] ?? '') ?> / <?= v3q_h($row['vehicle_plate'] ?? '') ?></td>
                            <td class="mini">Lessor: <?= v3q_h($row['lessor_id'] ?? '') ?><br>Driver: <?= v3q_h($row['driver_id'] ?? '') ?><br>Vehicle: <?= v3q_h($row['vehicle_id'] ?? '') ?><br>Start: <?= v3q_h($row['starting_point_id'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Filters</h2>
            <form method="get" action="/ops/pre-ride-email-v3-queue.php" class="actions">
                <label>Status<br>
                    <select name="status">
                        <?php foreach (['all','queued','ready','submit_dry_run_ready','locked','submitted','failed','blocked','needs_review','cancelled'] as $opt): ?>
                            <option value="<?= v3q_h($opt) ?>" <?= $status === $opt ? 'selected' : '' ?>><?= v3q_h($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Limit<br><input type="number" name="limit" value="<?= v3q_h((string)$limit) ?>" min="10" max="200"></label>
                <label>Offset<br><input type="number" name="offset" value="<?= v3q_h((string)$offset) ?>" min="0"></label>
                <button class="btn purple" type="submit">Apply</button>
                <a class="btn light" href="/ops/pre-ride-email-v3-queue.php">Reset</a>
                <a class="btn dark" href="/ops/pre-ride-email-toolv3.php">Back to V3 intake</a>
            </form>
            <p class="small">This page writes only explicit operator actions to V3 queue/events tables. If a selected row is future-safe, it can also be saved to the isolated V3 Firefox helper for fill-only EDXEIX review.</p>
        </section>
    </section>

    <section class="card">
        <h2>Recent V3 queue rows</h2>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th><th>Status</th><th>Pickup</th><th>Customer</th><th>Driver / Vehicle</th><th>IDs</th><th>Gates</th><th>Dedupe</th><th>Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recentRows)): ?>
                    <tr><td colspan="9">No matching V3 queue rows yet.</td></tr>
                <?php else: foreach ($recentRows as $row): ?>
                    <tr>
                        <td><a href="?id=<?= v3q_h($row['id'] ?? '') ?>&status=<?= v3q_h($status) ?>&limit=<?= v3q_h((string)$limit) ?>">#<?= v3q_h($row['id'] ?? '') ?></a></td>
                        <td><?= v3q_status_badge((string)($row['queue_status'] ?? '')) ?></td>
                        <td><?= v3q_h($row['pickup_datetime'] ?? '') ?><br><span class="mini">End: <?= v3q_h($row['estimated_end_datetime'] ?? '') ?></span></td>
                        <td><?= v3q_h($row['customer_name'] ?? '') ?><br><span class="mini"><?= v3q_h($row['customer_phone'] ?? '') ?></span></td>
                        <td><?= v3q_h($row['driver_name'] ?? '') ?><br><span class="mini"><?= v3q_h($row['vehicle_plate'] ?? '') ?></span></td>
                        <td class="mini">Lessor: <?= v3q_h($row['lessor_id'] ?? '') ?><br>Driver: <?= v3q_h($row['driver_id'] ?? '') ?><br>Vehicle: <?= v3q_h($row['vehicle_id'] ?? '') ?><br>Start: <?= v3q_h($row['starting_point_id'] ?? '') ?></td>
                        <td class="mini"><?= v3q_yes_no_badge($row['parser_ok'] ?? 0, 'parser', 'parser') ?> <?= v3q_yes_no_badge($row['mapping_ok'] ?? 0, 'ids', 'ids') ?> <?= v3q_yes_no_badge($row['future_ok'] ?? 0, 'future', 'future') ?></td>
                        <td><code><?= v3q_h($row['dedupe_key'] ?? '') ?></code></td>
                        <td><?= v3q_h($row['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php if (is_array($selectedRow)): ?>
    <section class="card">
        <h2>Selected queue row #<?= v3q_h($selectedRow['id'] ?? '') ?></h2>
        <div class="kv">
            <strong>Status</strong><div><?= v3q_status_badge((string)($selectedRow['queue_status'] ?? '')) ?></div>
            <strong>Dedupe key</strong><div><code><?= v3q_h($selectedRow['dedupe_key'] ?? '') ?></code></div>
            <strong>Source</strong><div><?= v3q_h($selectedRow['source_mailbox'] ?? '') ?> <?= !empty($selectedRow['source_mtime']) ? '(' . v3q_h($selectedRow['source_mtime']) . ')' : '' ?></div>
            <strong>Customer</strong><div><?= v3q_h($selectedRow['customer_name'] ?? '') ?> · <?= v3q_h($selectedRow['customer_phone'] ?? '') ?></div>
            <strong>Driver / vehicle</strong><div><?= v3q_h($selectedRow['driver_name'] ?? '') ?> / <?= v3q_h($selectedRow['vehicle_plate'] ?? '') ?></div>
            <strong>Pickup</strong><div><?= v3q_h($selectedRow['pickup_datetime'] ?? '') ?> → <?= v3q_h($selectedRow['estimated_end_datetime'] ?? '') ?></div>
            <strong>Route</strong><div><?= v3q_h($selectedRow['pickup_address'] ?? '') ?> → <?= v3q_h($selectedRow['dropoff_address'] ?? '') ?></div>
            <strong>Price</strong><div><?= v3q_h($selectedRow['price_text'] ?? '') ?> / <?= v3q_h($selectedRow['price_amount'] ?? '') ?></div>
            <strong>Last error</strong><div><?= v3q_h($selectedRow['last_error'] ?? '') ?></div>
        </div>

        <h3>V3 submit control panel</h3>
        <div class="warnbox">
            <strong>Operator-controlled V3 state only.</strong>
            <p class="small">These buttons update only <code>pre_ride_email_v3_queue</code> and <code>pre_ride_email_v3_queue_events</code>. They do not call EDXEIX, do not call AADE, and do not touch production submission tables.</p>
            <form method="post" action="/ops/pre-ride-email-v3-queue.php" class="actions" onsubmit="return confirm('Apply this V3-only operator action?');">
                <input type="hidden" name="csrf_token" value="<?= v3q_h(v3q_csrf_token()) ?>">
                <input type="hidden" name="queue_id" value="<?= v3q_h($selectedRow['id'] ?? '') ?>">
                <input type="hidden" name="return_status" value="<?= v3q_h($status) ?>">
                <label style="flex:1 1 360px;">Operator note / reason<br>
                    <input type="text" name="operator_note" maxlength="1000" placeholder="Optional note for the V3 event log" style="width:100%;">
                </label>
                <button class="btn light" type="submit" name="queue_action" value="mark_reviewed">Mark reviewed</button>
                <button class="btn purple" type="submit" name="queue_action" value="mark_submit_dry_run_ready" <?= $helperEligible ? '' : 'disabled' ?>>Mark submit dry-run ready</button>
                <button class="btn dark" type="submit" name="queue_action" value="reset_to_queued">Reset to queued</button>
                <button class="btn" style="background:#b42318" type="submit" name="queue_action" value="block_row">Block row</button>
            </form>
            <?php if (!$helperEligible): ?>
                <p class="small"><strong>Submit dry-run ready is disabled because:</strong> <?= v3q_h(implode('; ', $helperBlockReasons)) ?></p>
            <?php endif; ?>
        </div>

        <h3>V3 helper handoff</h3>
        <div class="<?= $helperEligible ? 'okbox' : 'warnbox' ?>">
            <strong><?= $helperEligible ? 'Ready for V3 helper fill-only handoff.' : 'Helper handoff blocked.' ?></strong>
            <p class="small">
                This saves the selected queue row to the isolated Firefox V3 helper storage only. It does not submit to EDXEIX and does not update the queue row.
            </p>
            <?php if (!$helperEligible): ?>
                <ul>
                    <?php foreach ($helperBlockReasons as $reason): ?><li><?= v3q_h($reason) ?></li><?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div class="actions">
                <button class="btn purple" type="button" onclick="v3qSaveSelectedPayload(false)" <?= $helperEligible ? '' : 'disabled' ?>>Save selected row to V3 helper</button>
                <button class="btn" type="button" onclick="v3qSaveSelectedPayload(true)" <?= $helperEligible ? '' : 'disabled' ?>>Save + open EDXEIX company form</button>
                <span id="v3q-helper-status" class="small">Waiting for operator action.</span>
            </div>
        </div>

        <h3>Events</h3>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>ID</th><th>Type</th><th>Status</th><th>Message</th><th>Created</th></tr></thead>
                <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="5">No events for this queue row yet.</td></tr>
                <?php else: foreach ($events as $event): ?>
                    <tr>
                        <td>#<?= v3q_h($event['id'] ?? '') ?></td>
                        <td><?= v3q_h($event['event_type'] ?? '') ?></td>
                        <td><?= v3q_h($event['event_status'] ?? '') ?></td>
                        <td><?= v3q_h($event['event_message'] ?? '') ?></td>
                        <td><?= v3q_h($event['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <h3>Payload JSON</h3>
        <textarea class="code-box" readonly><?= v3q_h(v3q_pretty_json($selectedRow['payload_json'] ?? '')) ?></textarea>
        <h3>Parsed fields JSON</h3>
        <textarea class="code-box" readonly><?= v3q_h(v3q_pretty_json($selectedRow['parsed_fields_json'] ?? '')) ?></textarea>
        <h3>Block reasons JSON</h3>
        <textarea class="code-box" readonly><?= v3q_h(v3q_pretty_json($selectedRow['block_reasons_json'] ?? '')) ?></textarea>
        <h3>Raw email preview</h3>
        <textarea class="code-box" readonly><?= v3q_h($selectedRow['raw_email_preview'] ?? '') ?></textarea>
    </section>
    <?php elseif ($selectedId > 0): ?>
        <section class="card warnbox">Queue row #<?= v3q_h((string)$selectedId) ?> was not found.</section>
    <?php endif; ?>
    <?php endif; ?>
</main>
<?php if (is_array($selectedRow)): ?>
<script>
(function () {
    'use strict';
    const V3Q_PAYLOAD = <?= $selectedPayloadJson ?>;
    const V3Q_HELPER_ELIGIBLE = <?= $helperEligible ? 'true' : 'false' ?>;
    const V3Q_BLOCK_REASONS = <?= $helperBlockReasonsJson ?>;
    const statusEl = document.getElementById('v3q-helper-status');

    function setStatus(text, ok) {
        if (!statusEl) { return; }
        statusEl.textContent = text;
        statusEl.style.color = ok ? '#065f46' : '#991b1b';
        statusEl.style.fontWeight = '700';
    }

    window.v3qSaveSelectedPayload = function (openEdxeix) {
        if (!V3Q_HELPER_ELIGIBLE) {
            alert('V3 helper handoff is blocked:\n- ' + V3Q_BLOCK_REASONS.join('\n- '));
            setStatus('Blocked: ' + V3Q_BLOCK_REASONS.join('; '), false);
            return;
        }
        if (!V3Q_PAYLOAD || !V3Q_PAYLOAD.lessorId) {
            alert('Selected row payload is missing the lessor/company ID.');
            setStatus('Payload missing lessor/company ID.', false);
            return;
        }

        const payload = Object.assign({}, V3Q_PAYLOAD, {
            savedAt: new Date().toISOString(),
            source: 'gov.cabnet.app V3 queue dashboard selected row'
        });

        setStatus('Saving selected row to V3 helper...', true);
        let finished = false;
        const timeout = window.setTimeout(function () {
            if (finished) { return; }
            finished = true;
            window.removeEventListener('message', onSaved);
            setStatus('V3 helper did not respond. Reload the V3 Firefox helper extension and try again.', false);
            alert('V3 helper did not respond. Make sure tools/firefox-edxeix-autofill-helper-v3 is loaded in Firefox.');
        }, 2500);

        function onSaved(event) {
            if (event.source !== window) { return; }
            const msg = event.data || {};
            if (!msg || msg.type !== 'GOV_CABNET_EDXEIX_PAYLOAD_V3_SAVED') { return; }
            finished = true;
            window.clearTimeout(timeout);
            window.removeEventListener('message', onSaved);
            if (!msg.ok) {
                setStatus('V3 helper save failed: ' + (msg.error || 'unknown error'), false);
                alert('V3 helper save failed: ' + (msg.error || 'unknown error'));
                return;
            }
            setStatus('V3 helper payload saved at ' + (msg.savedAt || 'now'), true);
            if (openEdxeix) {
                window.location.href = 'https://edxeix.yme.gov.gr/dashboard/lease-agreement/create?lessor=' + encodeURIComponent(String(payload.lessorId || ''));
            }
        }

        window.addEventListener('message', onSaved);
        window.postMessage({ type: 'GOV_CABNET_EDXEIX_PAYLOAD_V3', payload: payload }, '*');
    };
})();
</script>
<?php endif; ?>
</body>
</html>
