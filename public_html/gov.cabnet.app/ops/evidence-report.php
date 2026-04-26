<?php
/**
 * gov.cabnet.app — Bolt Evidence Markdown Report Exporter
 *
 * Purpose:
 * - Produce a copy/paste-ready Markdown report from the existing sanitized
 *   Bolt API visibility timeline and readiness audit.
 * - Speed up sharing test evidence back into development without screenshots.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not write database rows or files.
 * - Reads sanitized Bolt visibility JSONL snapshots only.
 */

declare(strict_types=1);

header('X-Robots-Tag: noindex,nofollow', true);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function ber_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ber_param(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    if (is_array($value)) {
        return $default;
    }
    return trim((string)$value);
}

function ber_int_param(string $key, int $default, int $min, int $max): int
{
    $raw = $_GET[$key] ?? $_POST[$key] ?? $default;
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => $default]]);
    return max($min, min($max, (int)$value));
}

function ber_date_param(string $key): string
{
    $raw = ber_param($key, date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
        return date('Y-m-d');
    }
    $parts = explode('-', $raw);
    if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
        return date('Y-m-d');
    }
    return $raw;
}

function ber_bool_param(string $key, bool $default = false): bool
{
    $raw = $_GET[$key] ?? $_POST[$key] ?? null;
    if ($raw === null || $raw === '') {
        return $default;
    }
    $parsed = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $parsed === null ? $default : $parsed;
}

function ber_load_readiness(): array
{
    $path = dirname(__DIR__) . '/bolt_readiness_audit.php';
    $out = ['ok' => false, 'audit' => null, 'error' => null, 'path' => $path];
    try {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Readiness audit file is missing or unreadable.');
        }
        require_once $path;
        if (!function_exists('gov_readiness_build_audit')) {
            throw new RuntimeException('gov_readiness_build_audit() is unavailable.');
        }
        $out['audit'] = gov_readiness_build_audit(['limit' => 60, 'analysis_limit' => 350]);
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }
    return $out;
}

function ber_load_visibility(): array
{
    $path = '/home/cabnet/gov.cabnet.app_app/lib/bolt_visibility_diagnostic.php';
    $out = ['ok' => false, 'error' => null, 'path' => $path];
    try {
        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException('Bolt visibility diagnostic library is missing or unreadable.');
        }
        require_once $path;
        foreach (['gov_bolt_visibility_recent_snapshots', 'gov_bolt_visibility_snapshot_file'] as $fn) {
            if (!function_exists($fn)) {
                throw new RuntimeException($fn . '() is unavailable.');
            }
        }
        $out['ok'] = true;
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
    }
    return $out;
}

function ber_stage_from_snapshot(array $row): string
{
    $label = strtolower((string)($row['label'] ?? ''));
    if (strpos($label, 'accepted') !== false || strpos($label, 'assigned') !== false) {
        return 'accepted-assigned';
    }
    if (strpos($label, 'pickup') !== false || strpos($label, 'waiting') !== false || strpos($label, 'picked') !== false) {
        return 'pickup-waiting';
    }
    if (strpos($label, 'trip-started') !== false || strpos($label, 'started') !== false || strpos($label, 'in-progress') !== false) {
        return 'trip-started';
    }
    if (strpos($label, 'completed') !== false || strpos($label, 'finished') !== false) {
        return 'completed';
    }
    if (strpos($label, 'watch') !== false || strpos($label, 'auto') !== false) {
        return 'auto-watch';
    }
    return 'manual-other';
}

function ber_snapshot_metric(array $row, string $key): int
{
    $visibility = is_array($row['visibility'] ?? null) ? $row['visibility'] : [];
    return isset($visibility[$key]) && is_numeric($visibility[$key]) ? (int)$visibility[$key] : 0;
}

function ber_watch_match(array $row, string $key): bool
{
    $visibility = is_array($row['visibility'] ?? null) ? $row['visibility'] : [];
    $watch = is_array($visibility['watch'] ?? null) ? $visibility['watch'] : [];
    $matches = is_array($watch['matches'] ?? null) ? $watch['matches'] : [];
    return !empty($matches[$key]);
}

function ber_empty_stage_summary(): array
{
    return [
        'count' => 0,
        'latest_at' => null,
        'max_orders_seen' => 0,
        'max_sample_count' => 0,
        'max_local_recent_count' => 0,
        'driver_match' => false,
        'vehicle_match' => false,
        'order_match' => false,
    ];
}

function ber_analyze_snapshots(array $snapshots): array
{
    $stages = [];
    foreach (['accepted-assigned', 'pickup-waiting', 'trip-started', 'completed', 'auto-watch', 'manual-other'] as $stage) {
        $stages[$stage] = ber_empty_stage_summary();
    }

    $summary = [
        'total_snapshots' => count($snapshots),
        'first_captured_at' => null,
        'latest_captured_at' => null,
        'latest_snapshot' => null,
        'max_orders_seen' => 0,
        'max_sample_count' => 0,
        'max_local_recent_count' => 0,
        'ever_orders_seen' => false,
        'ever_samples_seen' => false,
        'ever_local_recent_seen' => false,
        'ever_driver_match' => false,
        'ever_vehicle_match' => false,
        'ever_order_match' => false,
        'stages' => $stages,
    ];

    foreach ($snapshots as $index => $row) {
        if (!is_array($row)) {
            continue;
        }
        $captured = (string)($row['captured_at'] ?? '');
        if ($summary['first_captured_at'] === null && $captured !== '') {
            $summary['first_captured_at'] = $captured;
        }
        if ($captured !== '') {
            $summary['latest_captured_at'] = $captured;
        }
        $summary['latest_snapshot'] = $row;

        $orders = ber_snapshot_metric($row, 'orders_seen');
        $samples = ber_snapshot_metric($row, 'sample_count');
        $local = ber_snapshot_metric($row, 'local_recent_count');
        $driverMatch = ber_watch_match($row, 'driver_uuid');
        $vehicleMatch = ber_watch_match($row, 'vehicle_plate');
        $orderMatch = ber_watch_match($row, 'order_id');

        $summary['max_orders_seen'] = max($summary['max_orders_seen'], $orders);
        $summary['max_sample_count'] = max($summary['max_sample_count'], $samples);
        $summary['max_local_recent_count'] = max($summary['max_local_recent_count'], $local);
        $summary['ever_orders_seen'] = $summary['ever_orders_seen'] || $orders > 0;
        $summary['ever_samples_seen'] = $summary['ever_samples_seen'] || $samples > 0;
        $summary['ever_local_recent_seen'] = $summary['ever_local_recent_seen'] || $local > 0;
        $summary['ever_driver_match'] = $summary['ever_driver_match'] || $driverMatch;
        $summary['ever_vehicle_match'] = $summary['ever_vehicle_match'] || $vehicleMatch;
        $summary['ever_order_match'] = $summary['ever_order_match'] || $orderMatch;

        $stage = ber_stage_from_snapshot($row);
        if (!isset($summary['stages'][$stage])) {
            $summary['stages'][$stage] = ber_empty_stage_summary();
        }
        $summary['stages'][$stage]['count']++;
        if ($captured !== '') {
            $summary['stages'][$stage]['latest_at'] = $captured;
        }
        $summary['stages'][$stage]['max_orders_seen'] = max($summary['stages'][$stage]['max_orders_seen'], $orders);
        $summary['stages'][$stage]['max_sample_count'] = max($summary['stages'][$stage]['max_sample_count'], $samples);
        $summary['stages'][$stage]['max_local_recent_count'] = max($summary['stages'][$stage]['max_local_recent_count'], $local);
        $summary['stages'][$stage]['driver_match'] = $summary['stages'][$stage]['driver_match'] || $driverMatch;
        $summary['stages'][$stage]['vehicle_match'] = $summary['stages'][$stage]['vehicle_match'] || $vehicleMatch;
        $summary['stages'][$stage]['order_match'] = $summary['stages'][$stage]['order_match'] || $orderMatch;
    }

    return $summary;
}

function ber_bool_word(bool $value): string
{
    return $value ? 'YES' : 'NO';
}

function ber_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . ber_h($type) . '">' . ber_h($text) . '</span>';
}

function ber_bool_badge(bool $value, string $yes = 'YES', string $no = 'NO'): string
{
    return ber_badge($value ? $yes : $no, $value ? 'good' : 'neutral');
}

function ber_bundle_verdict(array $summary): array
{
    if ((int)$summary['total_snapshots'] === 0) {
        return ['WAITING_FOR_EVIDENCE', 'No sanitized Bolt visibility snapshots have been recorded for this date yet.', 'warn'];
    }
    if (!empty($summary['ever_driver_match']) || !empty($summary['ever_vehicle_match']) || !empty($summary['ever_order_match'])) {
        return ['WATCH_MATCH_EVIDENCE_PRESENT', 'At least one watched identifier matched in the sanitized evidence timeline.', 'good'];
    }
    if (!empty($summary['ever_local_recent_seen'])) {
        return ['LOCAL_NORMALIZED_EVIDENCE_PRESENT', 'Local normalized Bolt rows appeared in the sanitized evidence timeline.', 'good'];
    }
    if (!empty($summary['ever_orders_seen'])) {
        return ['BOLT_ORDERS_SEEN', 'The dry-run visibility probe saw one or more Bolt orders.', 'warn'];
    }
    return ['EVIDENCE_RECORDED_NO_ORDER_VISIBILITY', 'Snapshots exist, but no Bolt order visibility was recorded yet.', 'warn'];
}

function ber_md_escape($value): string
{
    $text = (string)$value;
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    return trim($text);
}

function ber_build_markdown(array $payload): string
{
    $readiness = $payload['readiness'];
    $evidence = $payload['evidence'];
    $latest = is_array($evidence['latest_snapshot'] ?? null) ? $evidence['latest_snapshot'] : [];
    $latestVisibility = is_array($latest['visibility'] ?? null) ? $latest['visibility'] : [];
    $latestWatch = is_array($latestVisibility['watch']['matches'] ?? null) ? $latestVisibility['watch']['matches'] : [];

    $lines = [];
    $lines[] = '# gov.cabnet.app — Bolt Test Evidence Report';
    $lines[] = '';
    $lines[] = '- Generated at: `' . ber_md_escape($payload['generated_at']) . '`';
    $lines[] = '- Evidence date: `' . ber_md_escape($payload['date']) . '`';
    $lines[] = '- Bundle verdict: **' . ber_md_escape($payload['bundle_verdict']) . '**';
    $lines[] = '- Summary: ' . ber_md_escape($payload['bundle_text']);
    $lines[] = '';
    $lines[] = '## Safety contract';
    $lines[] = '';
    $lines[] = '- Calls Bolt: **NO**';
    $lines[] = '- Calls EDXEIX: **NO**';
    $lines[] = '- Stages jobs: **NO**';
    $lines[] = '- Updates mappings: **NO**';
    $lines[] = '- Live EDXEIX submission: **DISABLED / NOT USED**';
    $lines[] = '- Source: sanitized Bolt visibility JSONL snapshots only';
    $lines[] = '';
    $lines[] = '## Readiness passport';
    $lines[] = '';
    $lines[] = '- Readiness loaded: **' . ber_bool_word((bool)$readiness['loaded']) . '**';
    $lines[] = '- Readiness verdict: `' . ber_md_escape((string)($readiness['verdict'] ?? '')) . '`';
    $lines[] = '- Ready for future test: **' . ber_bool_word((bool)$readiness['ready_for_future_test']) . '**';
    $lines[] = '- Real candidate ready: **' . ber_bool_word((bool)$readiness['real_candidate_ready']) . '**';
    $lines[] = '- Candidate count: **' . (int)$readiness['candidate_count'] . '**';
    $lines[] = '- Driver mappings: **' . (int)($readiness['mapped_drivers']['mapped'] ?? 0) . '/' . (int)($readiness['mapped_drivers']['total'] ?? 0) . '**';
    $lines[] = '- Vehicle mappings: **' . (int)($readiness['mapped_vehicles']['mapped'] ?? 0) . '/' . (int)($readiness['mapped_vehicles']['total'] ?? 0) . '**';
    $lines[] = '- Clean LAB state: **' . ber_bool_word((bool)$readiness['clean_lab']) . '**';
    $lines[] = '- Clean queue: **' . ber_bool_word((bool)$readiness['clean_queue']) . '**';
    $lines[] = '- No live attempts: **' . ber_bool_word((bool)$readiness['no_live_attempts']) . '**';
    if (!empty($readiness['error'])) {
        $lines[] = '- Readiness error: `' . ber_md_escape((string)$readiness['error']) . '`';
    }
    $lines[] = '';
    $lines[] = '## Evidence summary';
    $lines[] = '';
    $lines[] = '- Total snapshots: **' . (int)$evidence['total_snapshots'] . '**';
    $lines[] = '- First captured at: `' . ber_md_escape((string)($evidence['first_captured_at'] ?? '')) . '`';
    $lines[] = '- Latest captured at: `' . ber_md_escape((string)($evidence['latest_captured_at'] ?? '')) . '`';
    $lines[] = '- Max orders seen: **' . (int)$evidence['max_orders_seen'] . '**';
    $lines[] = '- Max sanitized samples: **' . (int)$evidence['max_sample_count'] . '**';
    $lines[] = '- Max local rows: **' . (int)$evidence['max_local_recent_count'] . '**';
    $lines[] = '- Orders ever seen: **' . ber_bool_word((bool)$evidence['ever_orders_seen']) . '**';
    $lines[] = '- Samples ever seen: **' . ber_bool_word((bool)$evidence['ever_samples_seen']) . '**';
    $lines[] = '- Local rows ever seen: **' . ber_bool_word((bool)$evidence['ever_local_recent_seen']) . '**';
    $lines[] = '- Driver match ever seen: **' . ber_bool_word((bool)$evidence['ever_driver_match']) . '**';
    $lines[] = '- Vehicle match ever seen: **' . ber_bool_word((bool)$evidence['ever_vehicle_match']) . '**';
    $lines[] = '- Order match ever seen: **' . ber_bool_word((bool)$evidence['ever_order_match']) . '**';
    $lines[] = '';
    $lines[] = '## Stage coverage';
    $lines[] = '';
    $lines[] = '| Stage | Count | Latest | Max orders | Max samples | Max local rows | Driver match | Vehicle match | Order match |';
    $lines[] = '|---|---:|---|---:|---:|---:|---|---|---|';
    foreach ($evidence['stages'] as $stage => $row) {
        $lines[] = '| ' . ber_md_escape($stage) . ' | ' . (int)$row['count'] . ' | `' . ber_md_escape((string)($row['latest_at'] ?? '')) . '` | ' . (int)$row['max_orders_seen'] . ' | ' . (int)$row['max_sample_count'] . ' | ' . (int)$row['max_local_recent_count'] . ' | ' . ber_bool_word((bool)$row['driver_match']) . ' | ' . ber_bool_word((bool)$row['vehicle_match']) . ' | ' . ber_bool_word((bool)$row['order_match']) . ' |';
    }
    $lines[] = '';
    $lines[] = '## Latest snapshot';
    $lines[] = '';
    if ($latest) {
        $lines[] = '- Label: `' . ber_md_escape((string)($latest['label'] ?? '')) . '`';
        $lines[] = '- Captured at: `' . ber_md_escape((string)($latest['captured_at'] ?? '')) . '`';
        $lines[] = '- Probe ID: `' . ber_md_escape((string)($latest['probe_id'] ?? '')) . '`';
        $lines[] = '- Orders seen: **' . (int)($latestVisibility['orders_seen'] ?? 0) . '**';
        $lines[] = '- Sample count: **' . (int)($latestVisibility['sample_count'] ?? 0) . '**';
        $lines[] = '- Local recent count: **' . (int)($latestVisibility['local_recent_count'] ?? 0) . '**';
        $lines[] = '- Watch driver match: **' . ber_bool_word(!empty($latestWatch['driver_uuid'])) . '**';
        $lines[] = '- Watch vehicle match: **' . ber_bool_word(!empty($latestWatch['vehicle_plate'])) . '**';
        $lines[] = '- Watch order match: **' . ber_bool_word(!empty($latestWatch['order_id'])) . '**';
    } else {
        $lines[] = 'No latest snapshot is available yet.';
    }
    $lines[] = '';
    $lines[] = '## Snapshot source';
    $lines[] = '';
    $lines[] = '- Private sanitized file: `' . ber_md_escape((string)($payload['snapshot_file'] ?? '')) . '`';
    if (!empty($payload['snapshot_read_error'])) {
        $lines[] = '- Snapshot read error: `' . ber_md_escape((string)$payload['snapshot_read_error']) . '`';
    }
    $lines[] = '';
    $lines[] = '## Recommended next action';
    $lines[] = '';
    if ((int)$evidence['total_snapshots'] === 0) {
        $lines[] = 'Open `/ops/dev-accelerator.php` and record the four capture stages during a real future Bolt ride.';
    } elseif (empty($evidence['ever_orders_seen']) && empty($evidence['ever_local_recent_seen'])) {
        $lines[] = 'Evidence exists, but Bolt order visibility/local rows were not observed yet. Continue capturing accepted, pickup, started, and completed stages.';
    } elseif (!empty($evidence['ever_driver_match']) || !empty($evidence['ever_vehicle_match'])) {
        $lines[] = 'A watched identifier matched. Review `/bolt_edxeix_preflight.php?limit=30` for preflight-only validation. Do not submit live.';
    } else {
        $lines[] = 'Review the Evidence Bundle and continue with preflight-only diagnostics. Do not submit live.';
    }
    $lines[] = '';

    return implode("\n", $lines);
}

$date = ber_date_param('date');
$limit = ber_int_param('limit', 300, 1, 1000);
$format = strtolower(ber_param('format', 'html'));
$download = ber_bool_param('download', false);

$readinessLoad = ber_load_readiness();
$audit = is_array($readinessLoad['audit'] ?? null) ? $readinessLoad['audit'] : [];
$config = is_array($audit['config_state'] ?? null) ? $audit['config_state'] : [];
$drivers = is_array($audit['reference_counts']['drivers'] ?? null) ? $audit['reference_counts']['drivers'] : ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'placeholder' => 0];
$vehicles = is_array($audit['reference_counts']['vehicles'] ?? null) ? $audit['reference_counts']['vehicles'] : ['total' => 0, 'mapped' => 0, 'unmapped' => 0, 'placeholder' => 0];
$recent = is_array($audit['recent_bookings'] ?? null) ? $audit['recent_bookings'] : ['submission_safe_rows' => 0];
$lab = is_array($audit['lab_safety'] ?? null) ? $audit['lab_safety'] : [];
$queue = is_array($audit['queue_safety'] ?? null) ? $audit['queue_safety'] : [];
$attempts = is_array($audit['submission_attempt_safety'] ?? null) ? $audit['submission_attempt_safety'] : [];

$dryRun = !empty($config['dry_run_enabled']);
$boltConfig = !empty($config['bolt_credentials_present']);
$edxeixConfig = !empty($config['edxeix_lessor_present']) && !empty($config['edxeix_default_starting_point_present']);
$hasMappedDriver = (int)($drivers['mapped'] ?? 0) > 0;
$hasMappedVehicle = (int)($vehicles['mapped'] ?? 0) > 0;
$cleanLab = (int)($lab['normalized_lab_rows'] ?? 0) === 0 && (int)($lab['staged_lab_jobs'] ?? 0) === 0;
$cleanQueue = (int)($queue['submission_jobs_total'] ?? 0) === 0;
$noLiveAttempts = (int)($attempts['confirmed_live_indicated'] ?? 0) === 0;
$readyForFutureTest = $readinessLoad['ok'] && $dryRun && $boltConfig && $edxeixConfig && $hasMappedDriver && $hasMappedVehicle && $cleanLab && $cleanQueue && $noLiveAttempts;
$candidateCount = (int)($recent['submission_safe_rows'] ?? 0);
$realCandidateReady = $readyForFutureTest && $candidateCount > 0;

$visibilityLoad = ber_load_visibility();
$snapshots = [];
$snapshotFile = null;
$snapshotReadError = null;
if ($visibilityLoad['ok']) {
    try {
        $snapshotFile = gov_bolt_visibility_snapshot_file($date);
        $snapshots = gov_bolt_visibility_recent_snapshots($limit, $date);
    } catch (Throwable $e) {
        $snapshotReadError = $e->getMessage();
    }
} else {
    $snapshotReadError = $visibilityLoad['error'];
}

$evidence = ber_analyze_snapshots($snapshots);
[$bundleVerdict, $bundleText, $bundleType] = ber_bundle_verdict($evidence);

$payload = [
    'ok' => $readinessLoad['ok'] && $snapshotReadError === null,
    'script' => 'ops/evidence-report.php',
    'generated_at' => date('c'),
    'date' => $date,
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'writes_database' => false,
        'reads_sanitized_visibility_snapshots_only' => true,
        'live_edxeix_submission' => 'disabled_not_used',
    ],
    'readiness' => [
        'loaded' => $readinessLoad['ok'],
        'error' => $readinessLoad['error'],
        'verdict' => $audit['verdict'] ?? null,
        'ready_for_future_test' => $readyForFutureTest,
        'real_candidate_ready' => $realCandidateReady,
        'candidate_count' => $candidateCount,
        'mapped_drivers' => $drivers,
        'mapped_vehicles' => $vehicles,
        'clean_lab' => $cleanLab,
        'clean_queue' => $cleanQueue,
        'no_live_attempts' => $noLiveAttempts,
    ],
    'evidence' => $evidence,
    'bundle_verdict' => $bundleVerdict,
    'bundle_text' => $bundleText,
    'snapshot_file' => $snapshotFile,
    'snapshot_read_error' => $snapshotReadError,
    'links' => [
        'html' => '/ops/evidence-report.php?date=' . rawurlencode($date),
        'markdown' => '/ops/evidence-report.php?format=md&date=' . rawurlencode($date),
        'json' => '/ops/evidence-report.php?format=json&date=' . rawurlencode($date),
        'evidence_bundle' => '/ops/evidence-bundle.php?date=' . rawurlencode($date),
        'dev_accelerator' => '/ops/dev-accelerator.php',
        'bolt_visibility' => '/ops/bolt-api-visibility.php',
        'readiness' => '/ops/readiness.php',
        'preflight_json' => '/bolt_edxeix_preflight.php?limit=30',
    ],
];

$markdown = ber_build_markdown($payload);

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

if (in_array($format, ['md', 'markdown', 'txt', 'text'], true)) {
    header('Content-Type: text/plain; charset=utf-8');
    if ($download) {
        header('Content-Disposition: attachment; filename="bolt-evidence-report-' . $date . '.md"');
    }
    echo $markdown;
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Evidence Report Export | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#475569;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--slate:#334155;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1500px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.hero{border-left:7px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:30px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);border:0;cursor:pointer;font-size:14px}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}.btn.dark{background:var(--slate)}label{display:block;font-size:13px;font-weight:700;color:var(--slate);margin:10px 0 5px}input{width:100%;padding:10px;border:1px solid var(--line);border-radius:8px;background:#fff;color:var(--ink)}.form-grid{display:grid;grid-template-columns:220px 160px auto;gap:12px;align-items:end}.table-wrap{overflow:auto;border:1px solid var(--line);border-radius:10px}table{width:100%;border-collapse:collapse;min-width:900px;background:#fff}th,td{border-bottom:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f1f5f9;color:#334155}.copybox{width:100%;min-height:520px;font-family:Consolas,Menlo,monospace;font-size:13px;line-height:1.4;white-space:pre;overflow:auto}.small{font-size:13px;color:var(--muted)}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.badline{color:#991b1b}.goodline{color:#166534}.warnline{color:#b45309}@media(max-width:1100px){.grid,.two,.form-grid{grid-template-columns:1fr 1fr}}@media(max-width:760px){.grid,.two,.form-grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/dev-accelerator.php">Dev Accelerator</a>
    <a href="/ops/evidence-bundle.php">Evidence Bundle</a>
    <a href="/ops/evidence-report.php">Evidence Report</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/future-test.php">Future Test</a>
    <a href="/ops/mappings.php">Mappings</a>
    <a href="/ops/jobs.php">Jobs</a>
    <a href="/ops/bolt-api-visibility.php">Bolt Visibility</a>
</nav>

<main class="wrap">
    <section class="safety">
        <strong>READ-ONLY MARKDOWN EXPORT.</strong>
        This page reads readiness state and existing sanitized Bolt visibility timeline entries only. It does not call Bolt, EDXEIX, or write data.
    </section>

    <section class="card hero <?= ber_h($bundleType) ?>">
        <h1>Bolt Evidence Report Export</h1>
        <p><?= ber_h($bundleText) ?></p>
        <div>
            <?= ber_badge($bundleVerdict, $bundleType) ?>
            <?= ber_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= ber_badge('NO BOLT CALLS HERE', 'good') ?>
            <?= ber_badge('MARKDOWN READY', 'neutral') ?>
        </div>
        <div class="grid" style="margin-top:14px">
            <div class="metric"><strong><?= ber_h((string)$evidence['total_snapshots']) ?></strong><span>Snapshots for <?= ber_h($date) ?></span></div>
            <div class="metric"><strong><?= ber_h((string)$evidence['max_orders_seen']) ?></strong><span>Max orders seen</span></div>
            <div class="metric"><strong><?= ber_h((string)$evidence['max_sample_count']) ?></strong><span>Max samples</span></div>
            <div class="metric"><strong><?= ber_h((string)$evidence['max_local_recent_count']) ?></strong><span>Max local rows</span></div>
        </div>
        <div class="actions">
            <a class="btn good" href="/ops/evidence-report.php?format=md&date=<?= ber_h(rawurlencode($date)) ?>">Open Markdown</a>
            <a class="btn" href="/ops/evidence-report.php?format=json&date=<?= ber_h(rawurlencode($date)) ?>">Open JSON</a>
            <a class="btn dark" href="/ops/evidence-report.php?format=md&download=1&date=<?= ber_h(rawurlencode($date)) ?>">Download .md</a>
            <a class="btn warn" href="/ops/evidence-bundle.php?date=<?= ber_h(rawurlencode($date)) ?>">Open Evidence Bundle</a>
            <a class="btn dark" href="/ops/dev-accelerator.php">Open Dev Accelerator</a>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Inspect another report date</h2>
            <form method="get" action="/ops/evidence-report.php">
                <div class="form-grid">
                    <div>
                        <label for="date">Date</label>
                        <input id="date" name="date" type="date" value="<?= ber_h($date) ?>">
                    </div>
                    <div>
                        <label for="limit">Snapshot limit</label>
                        <input id="limit" name="limit" type="number" min="1" max="1000" value="<?= ber_h((string)$limit) ?>">
                    </div>
                    <div>
                        <button class="btn" type="submit">Load Report</button>
                    </div>
                </div>
            </form>
            <p class="small">Private source file: <code><?= ber_h((string)$snapshotFile) ?></code></p>
            <?php if ($snapshotReadError !== null): ?>
                <p class="badline"><strong>Snapshot read warning:</strong> <?= ber_h($snapshotReadError) ?></p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Readiness passport</h2>
            <p>Verdict: <?= ber_badge((string)($audit['verdict'] ?? 'UNKNOWN'), $readyForFutureTest ? 'good' : 'warn') ?></p>
            <p>Ready for future test <?= ber_bool_badge($readyForFutureTest) ?></p>
            <p>Real candidate ready <?= ber_bool_badge($realCandidateReady) ?></p>
            <p>Driver mappings: <strong><?= ber_h(($drivers['mapped'] ?? 0) . '/' . ($drivers['total'] ?? 0)) ?></strong></p>
            <p>Vehicle mappings: <strong><?= ber_h(($vehicles['mapped'] ?? 0) . '/' . ($vehicles['total'] ?? 0)) ?></strong></p>
            <p>No live attempts <?= ber_bool_badge($noLiveAttempts) ?></p>
        </div>
    </section>

    <section class="card">
        <h2>Stage coverage</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Stage</th><th>Count</th><th>Latest</th><th>Max orders</th><th>Max samples</th><th>Max local rows</th><th>Driver</th><th>Vehicle</th><th>Order</th></tr></thead>
                <tbody>
                <?php foreach ($evidence['stages'] as $stage => $row): ?>
                    <tr>
                        <td><strong><?= ber_h($stage) ?></strong></td>
                        <td><?= ber_h((string)$row['count']) ?></td>
                        <td><code><?= ber_h((string)($row['latest_at'] ?? '')) ?></code></td>
                        <td><?= ber_h((string)$row['max_orders_seen']) ?></td>
                        <td><?= ber_h((string)$row['max_sample_count']) ?></td>
                        <td><?= ber_h((string)$row['max_local_recent_count']) ?></td>
                        <td><?= ber_bool_badge((bool)$row['driver_match']) ?></td>
                        <td><?= ber_bool_badge((bool)$row['vehicle_match']) ?></td>
                        <td><?= ber_bool_badge((bool)$row['order_match']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <h2>Copy/paste Markdown report</h2>
        <p class="small">Select all text below and paste it into the chat after the real ride test. It contains no raw secrets or raw Bolt payloads.</p>
        <textarea class="copybox" readonly><?= ber_h($markdown) ?></textarea>
    </section>
</main>
</body>
</html>
