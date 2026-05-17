<?php
/**
 * gov.cabnet.app — Mapping Workbench V3
 *
 * Verified Bolt driver + active vehicle + EDXEIX lessor mapping workflow.
 * GET is read-only. POST is guarded and limited to local mapping fields and audit rows.
 * This page does not call Bolt, does not call EDXEIX, does not call AADE, and does not create jobs.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';
$shellFile = __DIR__ . '/_shell.php';
if (is_file($shellFile)) { require_once $shellFile; }
$mappingNavFile = __DIR__ . '/_mapping_nav.php';
if (is_file($mappingNavFile)) { require_once $mappingNavFile; }

function mw3_h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function mw3_badge(string $label, string $tone = 'neutral'): string
{
    if (function_exists('opsui_badge')) { return opsui_badge($label, $tone); }
    $tone = in_array($tone, ['good','warn','bad','neutral','info'], true) ? $tone : 'neutral';
    return '<span class="badge ' . mw3_h($tone) . '">' . mw3_h($label) . '</span>';
}
function mw3_value(array $row, array $keys, mixed $default = ''): mixed
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') { return $row[$key]; }
    }
    return $default;
}
function mw3_has_col(array $columns, string $column): bool { return isset($columns[$column]); }
function mw3_first_col(array $columns, array $candidates): ?string
{
    foreach ($candidates as $candidate) { if (mw3_has_col($columns, $candidate)) { return $candidate; } }
    return null;
}
function mw3_request(string $key, string $default = '', int $maxLen = 255): string
{
    $value = $_GET[$key] ?? $_POST[$key] ?? $default;
    $value = is_scalar($value) ? trim((string)$value) : $default;
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLen, 'UTF-8') : substr($value, 0, $maxLen);
}
function mw3_post_text(string $key, int $maxLen = 2000000): string
{
    $value = $_POST[$key] ?? '';
    if (!is_scalar($value)) { return ''; }
    $value = trim((string)$value);
    return strlen($value) > $maxLen ? substr($value, 0, $maxLen) : $value;
}
function mw3_limit(): int
{
    $raw = $_GET['limit'] ?? $_POST['limit'] ?? '160';
    $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['default' => 160]]);
    return max(1, min(500, (int)$value));
}
function mw3_positive_id(string $value, string $label): int
{
    $value = trim($value);
    if (!preg_match('/^[1-9][0-9]{0,18}$/', $value)) { throw new RuntimeException($label . ' must be a positive numeric value.'); }
    return (int)$value;
}
function mw3_name_norm(string $value): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = trim($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    $value = str_replace(['||','|','.',',',';',':','-','_','/','\\','(',')','"',"'", "\xc2\xa0"], ' ', $value);
    $value = str_replace(['ί','ϊ','ΐ'], 'ι', $value);
    $value = str_replace(['ή'], 'η', $value);
    $value = str_replace(['ύ','ϋ','ΰ'], 'υ', $value);
    $value = str_replace(['ό'], 'ο', $value);
    $value = str_replace(['ά'], 'α', $value);
    $value = str_replace(['έ'], 'ε', $value);
    $value = str_replace(['ώ'], 'ω', $value);
    $value = str_replace(['ς'], 'σ', $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
    return trim($value);
}
function mw3_plate_norm(string $plate): string
{
    $plate = html_entity_decode(strip_tags($plate), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $plate = trim($plate);
    $plate = function_exists('mb_strtoupper') ? mb_strtoupper($plate, 'UTF-8') : strtoupper($plate);
    $plate = strtr($plate, ['Α'=>'A','Β'=>'B','Ε'=>'E','Ζ'=>'Z','Η'=>'H','Ι'=>'I','Κ'=>'K','Μ'=>'M','Ν'=>'N','Ο'=>'O','Ρ'=>'P','Τ'=>'T','Υ'=>'Y','Χ'=>'X']);
    return preg_replace('/[^A-Z0-9]/', '', $plate) ?? $plate;
}
function mw3_plate_from_label(string $label): string
{
    $tokens = preg_split('/\s+/u', trim($label)) ?: [];
    foreach ($tokens as $token) {
        $norm = mw3_plate_norm((string)$token);
        if (preg_match('/^[A-Z]{2,3}[0-9]{3,4}$/', $norm)) { return $norm; }
    }
    return mw3_plate_norm($label);
}
function mw3_table_rows(mysqli $db, string $table, string $view, string $query, int $limit): array
{
    if (!gov_bridge_table_exists($db, $table)) { return []; }
    $columns = gov_bridge_table_columns($db, $table);
    if (!$columns) { return []; }
    $where = [];
    $params = [];
    $isDriver = $table === 'mapping_drivers';
    $edxCol = $isDriver ? mw3_first_col($columns, ['edxeix_driver_id','driver_id']) : mw3_first_col($columns, ['edxeix_vehicle_id','vehicle_id']);
    $lessorCol = mw3_has_col($columns, 'edxeix_lessor_id') ? 'edxeix_lessor_id' : null;
    if ($view === 'needs_driver' && $isDriver && $edxCol) { $where[] = '(' . gov_bridge_quote_identifier($edxCol) . ' IS NULL OR ' . gov_bridge_quote_identifier($edxCol) . " = '' OR " . gov_bridge_quote_identifier($edxCol) . ' = 0)'; }
    if ($view === 'needs_vehicle' && !$isDriver && $edxCol) { $where[] = '(' . gov_bridge_quote_identifier($edxCol) . ' IS NULL OR ' . gov_bridge_quote_identifier($edxCol) . " = '' OR " . gov_bridge_quote_identifier($edxCol) . ' = 0)'; }
    if ($view === 'needs_lessor' && $lessorCol) { $where[] = '(' . gov_bridge_quote_identifier($lessorCol) . ' IS NULL OR ' . gov_bridge_quote_identifier($lessorCol) . " = '' OR " . gov_bridge_quote_identifier($lessorCol) . ' = 0)'; }
    if ($view === 'mapped' && $edxCol) { $where[] = '(' . gov_bridge_quote_identifier($edxCol) . ' IS NOT NULL AND ' . gov_bridge_quote_identifier($edxCol) . " <> '' AND " . gov_bridge_quote_identifier($edxCol) . ' <> 0)'; }
    if ($view === 'unmapped' && $edxCol) { $where[] = 'NOT (' . gov_bridge_quote_identifier($edxCol) . ' IS NOT NULL AND ' . gov_bridge_quote_identifier($edxCol) . " <> '' AND " . gov_bridge_quote_identifier($edxCol) . ' <> 0)'; }
    if ($query !== '') {
        $candidates = $isDriver
            ? ['external_driver_name','driver_name','external_driver_id','driver_external_id','driver_uuid','driver_phone','active_vehicle_uuid','active_vehicle_plate','source_system']
            : ['plate','vehicle_plate','external_vehicle_name','vehicle_name','vehicle_model','external_vehicle_id','vehicle_external_id','vehicle_uuid','source_system'];
        $parts = [];
        foreach ($candidates as $candidate) {
            if (mw3_has_col($columns, $candidate)) { $parts[] = gov_bridge_quote_identifier($candidate) . ' LIKE ?'; $params[] = '%' . $query . '%'; }
        }
        if ($parts) { $where[] = '(' . implode(' OR ', $parts) . ')'; }
    }
    $order = mw3_first_col($columns, ['last_seen_at','updated_at','created_at','id']) ?: array_key_first($columns) ?: 'id';
    $sql = 'SELECT * FROM ' . gov_bridge_quote_identifier($table) . ($where ? (' WHERE ' . implode(' AND ', $where)) : '') . ' ORDER BY ' . gov_bridge_quote_identifier($order) . ' DESC LIMIT ' . (int)$limit;
    return gov_bridge_fetch_all($db, $sql, $params);
}
function mw3_table_stats(mysqli $db, string $table, array $edxCandidates): array
{
    $out = ['exists'=>false,'total'=>0,'mapped'=>0,'unmapped'=>0,'needs_lessor'=>0,'active'=>0,'mapped_percent'=>0];
    if (!gov_bridge_table_exists($db, $table)) { return $out; }
    $columns = gov_bridge_table_columns($db, $table);
    $out['exists'] = true;
    $out['total'] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table))['c'] ?? 0);
    $edx = mw3_first_col($columns, $edxCandidates);
    if ($edx) {
        $out['mapped'] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table) . ' WHERE ' . gov_bridge_quote_identifier($edx) . ' IS NOT NULL AND ' . gov_bridge_quote_identifier($edx) . " <> '' AND " . gov_bridge_quote_identifier($edx) . ' <> 0')['c'] ?? 0);
    }
    if (mw3_has_col($columns, 'edxeix_lessor_id')) {
        $out['needs_lessor'] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table) . ' WHERE edxeix_lessor_id IS NULL OR edxeix_lessor_id = 0')['c'] ?? 0);
    }
    if (mw3_has_col($columns, 'is_active')) {
        $out['active'] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table) . ' WHERE is_active = 1')['c'] ?? 0);
    }
    $out['unmapped'] = max(0, $out['total'] - $out['mapped']);
    $out['mapped_percent'] = $out['total'] > 0 ? round(($out['mapped'] / $out['total']) * 100, 1) : 0;
    return $out;
}
function mw3_snapshot_ready(mysqli $db): array
{
    $tables = ['edxeix_export_lessors','edxeix_export_drivers','edxeix_export_vehicles','edxeix_export_starting_points'];
    $missing = [];
    foreach ($tables as $table) { if (!gov_bridge_table_exists($db, $table)) { $missing[] = $table; } }
    $out = ['ready'=>empty($missing),'missing'=>$missing,'lessors'=>0,'drivers'=>0,'vehicles'=>0,'starting_points'=>0,'last_seen_at'=>''];
    if (!$out['ready']) { return $out; }
    foreach (['lessors'=>'edxeix_export_lessors','drivers'=>'edxeix_export_drivers','vehicles'=>'edxeix_export_vehicles','starting_points'=>'edxeix_export_starting_points'] as $key => $table) {
        $out[$key] = (int)(gov_bridge_fetch_one($db, 'SELECT COUNT(*) AS c FROM ' . gov_bridge_quote_identifier($table))['c'] ?? 0);
        $last = gov_bridge_fetch_one($db, 'SELECT MAX(last_seen_at) AS m FROM ' . gov_bridge_quote_identifier($table));
        if (!empty($last['m']) && (string)$last['m'] > (string)$out['last_seen_at']) { $out['last_seen_at'] = (string)$last['m']; }
    }
    return $out;
}
function mw3_load_snapshot(mysqli $db): array
{
    if (!mw3_snapshot_ready($db)['ready']) { return ['lessors'=>[], 'drivers'=>[], 'vehicles'=>[], 'drivers_by_lessor'=>[], 'vehicles_by_lessor'=>[]]; }
    $lessors = gov_bridge_fetch_all($db, 'SELECT * FROM edxeix_export_lessors ORDER BY lessor_label ASC LIMIT 10000');
    $drivers = gov_bridge_fetch_all($db, 'SELECT * FROM edxeix_export_drivers ORDER BY driver_label ASC LIMIT 30000');
    $vehicles = gov_bridge_fetch_all($db, 'SELECT * FROM edxeix_export_vehicles ORDER BY plate_norm ASC, vehicle_label ASC LIMIT 30000');
    $driversByLessor = [];
    $vehiclesByLessor = [];
    foreach ($drivers as $row) { $driversByLessor[(string)$row['lessor_id']][] = $row; }
    foreach ($vehicles as $row) { $vehiclesByLessor[(string)$row['lessor_id']][] = $row; }
    return ['lessors'=>$lessors, 'drivers'=>$drivers, 'vehicles'=>$vehicles, 'drivers_by_lessor'=>$driversByLessor, 'vehicles_by_lessor'=>$vehiclesByLessor];
}
function mw3_best_driver_suggestion(array $snapshot, string $driverName, string $lessorId = ''): array
{
    $rows = $lessorId !== '' && isset($snapshot['drivers_by_lessor'][$lessorId]) ? $snapshot['drivers_by_lessor'][$lessorId] : ($snapshot['drivers'] ?? []);
    $target = mw3_name_norm($driverName);
    $targetTokens = array_values(array_filter(explode(' ', $target), static fn($v) => strlen($v) >= 3));
    $best = ['id'=>'','label'=>'','lessor_id'=>'','score'=>0,'reason'=>''];
    foreach ($rows as $row) {
        $label = (string)($row['driver_label'] ?? '');
        $candidate = mw3_name_norm($label);
        $score = 0; $reason = '';
        if ($candidate !== '' && $candidate === $target) { $score = 100; $reason = 'exact normalized name'; }
        elseif ($candidate !== '' && (str_contains($candidate, $target) || str_contains($target, $candidate))) { $score = 86; $reason = 'contained normalized name'; }
        else {
            $hits = 0;
            foreach ($targetTokens as $token) { if (str_contains($candidate, $token)) { $hits++; } }
            if ($hits >= 2) { $score = 72; $reason = 'two-token name match'; }
            elseif ($hits === 1) { $score = 35; $reason = 'single-token weak match'; }
        }
        if ($score > $best['score']) {
            $best = ['id'=>(string)($row['edxeix_driver_id'] ?? ''),'label'=>$label,'lessor_id'=>(string)($row['lessor_id'] ?? ''),'score'=>$score,'reason'=>$reason];
        }
    }
    return $best;
}
function mw3_best_vehicle_suggestion(array $snapshot, string $plate, string $lessorId = ''): array
{
    $rows = $lessorId !== '' && isset($snapshot['vehicles_by_lessor'][$lessorId]) ? $snapshot['vehicles_by_lessor'][$lessorId] : ($snapshot['vehicles'] ?? []);
    $target = mw3_plate_norm($plate);
    $best = ['id'=>'','label'=>'','lessor_id'=>'','score'=>0,'reason'=>''];
    foreach ($rows as $row) {
        $plateNorm = (string)($row['plate_norm'] ?? '');
        if ($plateNorm === '') { $plateNorm = mw3_plate_from_label((string)($row['vehicle_label'] ?? '')); }
        $score = 0; $reason = '';
        if ($target !== '' && $plateNorm === $target) { $score = 100; $reason = 'exact normalized plate'; }
        elseif ($target !== '' && (str_contains($plateNorm, $target) || str_contains($target, $plateNorm))) { $score = 75; $reason = 'contained normalized plate'; }
        if ($score > $best['score']) {
            $best = ['id'=>(string)($row['edxeix_vehicle_id'] ?? ''),'label'=>(string)($row['vehicle_label'] ?? ''),'lessor_id'=>(string)($row['lessor_id'] ?? ''),'score'=>$score,'reason'=>$reason];
        }
    }
    return $best;
}
function mw3_snapshot_has(mysqli $db, string $type, int $lessorId, int $edxeixId): bool
{
    $table = $type === 'driver' ? 'edxeix_export_drivers' : ($type === 'vehicle' ? 'edxeix_export_vehicles' : 'edxeix_export_lessors');
    if (!gov_bridge_table_exists($db, $table)) { return false; }
    if ($type === 'driver') {
        return (bool)gov_bridge_fetch_one($db, 'SELECT edxeix_driver_id FROM edxeix_export_drivers WHERE lessor_id = ? AND edxeix_driver_id = ? LIMIT 1', [(string)$lessorId, (string)$edxeixId]);
    }
    if ($type === 'vehicle') {
        return (bool)gov_bridge_fetch_one($db, 'SELECT edxeix_vehicle_id FROM edxeix_export_vehicles WHERE lessor_id = ? AND edxeix_vehicle_id = ? LIMIT 1', [(string)$lessorId, (string)$edxeixId]);
    }
    return (bool)gov_bridge_fetch_one($db, 'SELECT lessor_id FROM edxeix_export_lessors WHERE lessor_id = ? LIMIT 1', [(string)$lessorId]);
}
function mw3_audit(mysqli $db, string $table, int $rowId, string $field, string $old, string $new, string $reason): void
{
    if (!gov_bridge_table_exists($db, 'mapping_update_audit')) { return; }
    gov_bridge_insert_row($db, 'mapping_update_audit', [
        'table_name' => $table,
        'row_id' => (string)$rowId,
        'field_name' => $field,
        'old_value' => $old,
        'new_value' => $new,
        'changed_by' => 'ops/mapping-workbench-v3.php',
        'remote_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'user_agent' => function_exists('mb_substr') ? mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255, 'UTF-8') : substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        'reason' => $reason,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}
function mw3_update_verified_pair(mysqli $db): array
{
    $confirm = mw3_request('confirm', '', 80);
    if ($confirm !== 'UPDATE VERIFIED MAPPING') { throw new RuntimeException('Confirmation phrase must be exactly: UPDATE VERIFIED MAPPING'); }
    $driverRowId = (int)filter_var($_POST['driver_row_id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    $vehicleRowId = (int)filter_var($_POST['vehicle_row_id'] ?? '0', FILTER_VALIDATE_INT, ['options' => ['default' => 0]]);
    if ($driverRowId <= 0 && $vehicleRowId <= 0) { throw new RuntimeException('Select at least one mapping row to update.'); }
    $lessorId = mw3_positive_id(mw3_request('edxeix_lessor_id', '', 30), 'EDXEIX lessor ID');
    $driverId = $driverRowId > 0 ? mw3_positive_id(mw3_request('edxeix_driver_id', '', 30), 'EDXEIX driver ID') : 0;
    $vehicleId = $vehicleRowId > 0 ? mw3_positive_id(mw3_request('edxeix_vehicle_id', '', 30), 'EDXEIX vehicle ID') : 0;
    $override = mw3_request('override_confirm', '', 80) === 'OVERRIDE SNAPSHOT VALIDATION';
    $snapshot = mw3_snapshot_ready($db);
    $problems = [];
    if ($snapshot['ready']) {
        if (!mw3_snapshot_has($db, 'lessor', $lessorId, $lessorId)) { $problems[] = 'lessor ID not found in latest EDXEIX snapshot'; }
        if ($driverRowId > 0 && !mw3_snapshot_has($db, 'driver', $lessorId, $driverId)) { $problems[] = 'driver ID not found under this lessor in latest EDXEIX snapshot'; }
        if ($vehicleRowId > 0 && !mw3_snapshot_has($db, 'vehicle', $lessorId, $vehicleId)) { $problems[] = 'vehicle ID not found under this lessor in latest EDXEIX snapshot'; }
    }
    if ($problems && !$override) { throw new RuntimeException('Snapshot validation blocked update: ' . implode('; ', $problems) . '. Type OVERRIDE SNAPSHOT VALIDATION only after independent manual verification.'); }

    $updated = [];
    $db->begin_transaction();
    try {
        $now = date('Y-m-d H:i:s');
        if ($driverRowId > 0) {
            $columns = gov_bridge_table_columns($db, 'mapping_drivers');
            if (!mw3_has_col($columns, 'edxeix_driver_id')) { throw new RuntimeException('mapping_drivers.edxeix_driver_id is missing.'); }
            if (!mw3_has_col($columns, 'edxeix_lessor_id')) { throw new RuntimeException('mapping_drivers.edxeix_lessor_id is missing. Run the V3 migration first.'); }
            $before = gov_bridge_fetch_one($db, 'SELECT * FROM mapping_drivers WHERE id = ? LIMIT 1', [(string)$driverRowId]);
            if (!$before) { throw new RuntimeException('Driver row not found.'); }
            $row = ['edxeix_driver_id' => (string)$driverId, 'edxeix_lessor_id' => (string)$lessorId];
            if (mw3_has_col($columns, 'updated_at')) { $row['updated_at'] = $now; }
            gov_bridge_update_row($db, 'mapping_drivers', $row, 'id = ?', [(string)$driverRowId]);
            mw3_audit($db, 'mapping_drivers', $driverRowId, 'edxeix_driver_id', (string)($before['edxeix_driver_id'] ?? ''), (string)$driverId, 'Mapping Workbench V3 verified update.');
            mw3_audit($db, 'mapping_drivers', $driverRowId, 'edxeix_lessor_id', (string)($before['edxeix_lessor_id'] ?? ''), (string)$lessorId, 'Mapping Workbench V3 verified update.');
            $updated[] = 'driver row #' . $driverRowId . ' → driver ' . $driverId . ', lessor ' . $lessorId;
        }
        if ($vehicleRowId > 0) {
            $columns = gov_bridge_table_columns($db, 'mapping_vehicles');
            if (!mw3_has_col($columns, 'edxeix_vehicle_id')) { throw new RuntimeException('mapping_vehicles.edxeix_vehicle_id is missing.'); }
            if (!mw3_has_col($columns, 'edxeix_lessor_id')) { throw new RuntimeException('mapping_vehicles.edxeix_lessor_id is missing. Run the V3 migration first.'); }
            $before = gov_bridge_fetch_one($db, 'SELECT * FROM mapping_vehicles WHERE id = ? LIMIT 1', [(string)$vehicleRowId]);
            if (!$before) { throw new RuntimeException('Vehicle row not found.'); }
            $row = ['edxeix_vehicle_id' => (string)$vehicleId, 'edxeix_lessor_id' => (string)$lessorId];
            if (mw3_has_col($columns, 'updated_at')) { $row['updated_at'] = $now; }
            gov_bridge_update_row($db, 'mapping_vehicles', $row, 'id = ?', [(string)$vehicleRowId]);
            mw3_audit($db, 'mapping_vehicles', $vehicleRowId, 'edxeix_vehicle_id', (string)($before['edxeix_vehicle_id'] ?? ''), (string)$vehicleId, 'Mapping Workbench V3 verified update.');
            mw3_audit($db, 'mapping_vehicles', $vehicleRowId, 'edxeix_lessor_id', (string)($before['edxeix_lessor_id'] ?? ''), (string)$lessorId, 'Mapping Workbench V3 verified update.');
            $updated[] = 'vehicle row #' . $vehicleRowId . ' → vehicle ' . $vehicleId . ', lessor ' . $lessorId;
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
    return ['ok'=>true, 'type'=>'good', 'message'=>'Updated verified mapping: ' . implode('; ', $updated) . '. No Bolt/EDXEIX/AADE calls were made.'];
}
function mw3_pick_from_array(array $row, array $keys, string $default = ''): string
{
    foreach ($keys as $key) { if (isset($row[$key]) && trim((string)$row[$key]) !== '') { return trim((string)$row[$key]); } }
    return $default;
}
function mw3_import_snapshot(mysqli $db): array
{
    if (mw3_request('confirm', '', 80) !== 'IMPORT EDXEIX SNAPSHOT') { throw new RuntimeException('Confirmation phrase must be exactly: IMPORT EDXEIX SNAPSHOT'); }
    $status = mw3_snapshot_ready($db);
    if (!$status['ready']) { throw new RuntimeException('Snapshot tables are missing. Run gov.cabnet.app_sql/2026_05_17_mapping_workbench_v3.sql first.'); }
    $json = mw3_post_text('edxeix_snapshot_json');
    if ($json === '') { throw new RuntimeException('Paste JSON exported by the EDXEIX console scraper.'); }
    $payload = json_decode($json, true);
    if (!is_array($payload)) { throw new RuntimeException('Invalid JSON: ' . json_last_error_msg()); }
    $generatedAt = date('Y-m-d H:i:s');
    if (!empty($payload['generated_at']) && strtotime((string)$payload['generated_at']) !== false) { $generatedAt = date('Y-m-d H:i:s', strtotime((string)$payload['generated_at'])); }
    $sourceUrl = function_exists('mb_substr') ? mb_substr(trim((string)($payload['source_url'] ?? '')), 0, 500, 'UTF-8') : substr(trim((string)($payload['source_url'] ?? '')), 0, 500);
    $lessorMap = [];
    $drivers = [];
    $vehicles = [];
    $startingPoints = [];
    foreach ((array)($payload['lessors'] ?? []) as $lessor) {
        if (!is_array($lessor)) { continue; }
        $lessorId = mw3_positive_id(mw3_pick_from_array($lessor, ['lessor_id','id','value']), 'Lessor ID');
        $label = mw3_pick_from_array($lessor, ['lessor_label','label','text','name'], 'EDXEIX lessor ' . $lessorId);
        $lessorMap[$lessorId] = ['label'=>$label,'drivers'=>0,'vehicles'=>0,'starting_points'=>0];
        foreach ((array)($lessor['drivers'] ?? []) as $row) { if (is_array($row)) { $row['lessor_id'] = $lessorId; $drivers[] = $row; } }
        foreach ((array)($lessor['vehicles'] ?? []) as $row) { if (is_array($row)) { $row['lessor_id'] = $lessorId; $vehicles[] = $row; } }
        $spRows = $lessor['starting_points'] ?? ($lessor['startingPoints'] ?? []);
        foreach ((array)$spRows as $row) { if (is_array($row)) { $row['lessor_id'] = $lessorId; $startingPoints[] = $row; } }
    }
    foreach ((array)($payload['drivers'] ?? []) as $row) { if (is_array($row)) { $drivers[] = $row; } }
    foreach ((array)($payload['vehicles'] ?? []) as $row) { if (is_array($row)) { $vehicles[] = $row; } }
    $topSp = $payload['starting_points'] ?? ($payload['startingPoints'] ?? []);
    foreach ((array)$topSp as $row) { if (is_array($row)) { $startingPoints[] = $row; } }
    $driverCount = $vehicleCount = $spCount = 0;
    foreach ($drivers as $row) {
        $lessorId = mw3_positive_id(mw3_pick_from_array($row, ['lessor_id','lessorId']), 'Driver lessor ID');
        $driverId = mw3_positive_id(mw3_pick_from_array($row, ['edxeix_driver_id','driver_id','id','value']), 'Driver ID');
        $label = mw3_pick_from_array($row, ['driver_label','label','text','name'], 'EDXEIX driver ' . $driverId);
        $lessorMap[$lessorId] = $lessorMap[$lessorId] ?? ['label'=>'EDXEIX lessor ' . $lessorId,'drivers'=>0,'vehicles'=>0,'starting_points'=>0];
        $lessorMap[$lessorId]['drivers']++;
        $stmt = $db->prepare('INSERT INTO edxeix_export_drivers (lessor_id, edxeix_driver_id, driver_label, last_seen_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE driver_label = VALUES(driver_label), last_seen_at = NOW(), updated_at = NOW()');
        gov_bridge_bind_params($stmt, 'iis', [$lessorId, $driverId, $label]); $stmt->execute(); $driverCount++;
    }
    foreach ($vehicles as $row) {
        $lessorId = mw3_positive_id(mw3_pick_from_array($row, ['lessor_id','lessorId']), 'Vehicle lessor ID');
        $vehicleId = mw3_positive_id(mw3_pick_from_array($row, ['edxeix_vehicle_id','vehicle_id','id','value']), 'Vehicle ID');
        $label = mw3_pick_from_array($row, ['vehicle_label','label','text','name'], 'EDXEIX vehicle ' . $vehicleId);
        $plateNorm = mw3_pick_from_array($row, ['plate_norm','plateNorm'], mw3_plate_from_label($label));
        $lessorMap[$lessorId] = $lessorMap[$lessorId] ?? ['label'=>'EDXEIX lessor ' . $lessorId,'drivers'=>0,'vehicles'=>0,'starting_points'=>0];
        $lessorMap[$lessorId]['vehicles']++;
        $stmt = $db->prepare('INSERT INTO edxeix_export_vehicles (lessor_id, edxeix_vehicle_id, vehicle_label, plate_norm, last_seen_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE vehicle_label = VALUES(vehicle_label), plate_norm = VALUES(plate_norm), last_seen_at = NOW(), updated_at = NOW()');
        gov_bridge_bind_params($stmt, 'iiss', [$lessorId, $vehicleId, $label, $plateNorm]); $stmt->execute(); $vehicleCount++;
    }
    foreach ($startingPoints as $row) {
        $lessorId = mw3_positive_id(mw3_pick_from_array($row, ['lessor_id','lessorId']), 'Starting point lessor ID');
        $spId = mw3_positive_id(mw3_pick_from_array($row, ['edxeix_starting_point_id','starting_point_id','id','value']), 'Starting point ID');
        $label = mw3_pick_from_array($row, ['starting_point_label','label','text','name'], 'EDXEIX starting point ' . $spId);
        $lessorMap[$lessorId] = $lessorMap[$lessorId] ?? ['label'=>'EDXEIX lessor ' . $lessorId,'drivers'=>0,'vehicles'=>0,'starting_points'=>0];
        $lessorMap[$lessorId]['starting_points']++;
        $stmt = $db->prepare('INSERT INTO edxeix_export_starting_points (lessor_id, edxeix_starting_point_id, starting_point_label, last_seen_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE starting_point_label = VALUES(starting_point_label), last_seen_at = NOW(), updated_at = NOW()');
        gov_bridge_bind_params($stmt, 'iis', [$lessorId, $spId, $label]); $stmt->execute(); $spCount++;
    }
    foreach ($lessorMap as $lessorId => $meta) {
        $stmt = $db->prepare('INSERT INTO edxeix_export_lessors (lessor_id, lessor_label, driver_count, vehicle_count, starting_point_count, export_generated_at, source_url, last_seen_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE lessor_label = VALUES(lessor_label), driver_count = VALUES(driver_count), vehicle_count = VALUES(vehicle_count), starting_point_count = VALUES(starting_point_count), export_generated_at = VALUES(export_generated_at), source_url = VALUES(source_url), last_seen_at = NOW(), updated_at = NOW()');
        gov_bridge_bind_params($stmt, 'isiiiss', [(int)$lessorId, (string)$meta['label'], (int)$meta['drivers'], (int)$meta['vehicles'], (int)$meta['starting_points'], $generatedAt, $sourceUrl]); $stmt->execute();
    }
    return ['ok'=>true,'type'=>'good','message'=>'Imported EDXEIX snapshot: ' . count($lessorMap) . ' lessors, ' . $driverCount . ' drivers, ' . $vehicleCount . ' vehicles, ' . $spCount . ' starting points. No live mappings were changed.'];
}
function mw3_console_script(): string
{
    return <<<'JS'
(async function govCabnetEdxeixSnapshotV3(){
  'use strict';
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const clean = v => String(v == null ? '' : v).replace(/\s+/g,' ').trim();
  const plateNorm = v => clean(v).toUpperCase().replace(/Α/g,'A').replace(/Β/g,'B').replace(/Ε/g,'E').replace(/Ζ/g,'Z').replace(/Η/g,'H').replace(/Ι/g,'I').replace(/Κ/g,'K').replace(/Μ/g,'M').replace(/Ν/g,'N').replace(/Ο/g,'O').replace(/Ρ/g,'P').replace(/Τ/g,'T').replace(/Υ/g,'Y').replace(/Χ/g,'X').replace(/[^A-Z0-9]/g,'');
  const realOptions = s => Array.from((s && s.options) || []).filter(o => clean(o.value) && clean(o.textContent || o.label) && !/παρακαλούμε|παρακαλουμε|please select|select|επιλέξτε|επιλεξτε/i.test(clean(o.textContent || o.label)));
  const selectByName = (doc, names) => { for (const n of names) { const el = doc.querySelector('select[name="'+n+'"],select#'+n); if (el) return el; } return null; };
  const optionRows = (s, idKey, labelKey) => realOptions(s).map(o => ({[idKey]: clean(o.value), [labelKey]: clean(o.textContent || o.label)}));
  const lessorSelect = selectByName(document, ['lessor']);
  const lessors = optionRows(lessorSelect, 'lessor_id', 'lessor_label');
  if (!lessors.length) { alert('No lessor dropdown found. Open the EDXEIX lease agreement create page first.'); return; }
  const origin = location.origin;
  const out = {source:'gov-cabnet-edxeix-snapshot-v3', generated_at:new Date().toISOString(), source_url:location.href, lessors:[]};
  for (const lessor of lessors) {
    const url = origin + '/dashboard/lease-agreement/create?lessor=' + encodeURIComponent(lessor.lessor_id);
    let doc = document;
    if (clean(new URLSearchParams(location.search).get('lessor')) !== clean(lessor.lessor_id)) {
      const html = await fetch(url, {credentials:'include'}).then(r => r.text());
      doc = new DOMParser().parseFromString(html, 'text/html');
      await sleep(100);
    }
    const drivers = optionRows(selectByName(doc, ['driver']), 'edxeix_driver_id', 'driver_label');
    const vehicles = optionRows(selectByName(doc, ['vehicle']), 'edxeix_vehicle_id', 'vehicle_label').map(v => ({...v, plate_norm: plateNorm(v.vehicle_label)}));
    const starting_points = optionRows(selectByName(doc, ['starting_point_id','starting_point']), 'edxeix_starting_point_id', 'starting_point_label');
    out.lessors.push({...lessor, drivers, vehicles, starting_points, counts:{drivers:drivers.length, vehicles:vehicles.length, starting_points:starting_points.length}});
  }
  const json = JSON.stringify(out, null, 2);
  console.log(json);
  try { await navigator.clipboard.writeText(json); } catch(e) {}
  const blob = new Blob([json], {type:'application/json'});
  const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'edxeix-snapshot-v3-' + new Date().toISOString().replace(/[:.]/g,'-') + '.json'; document.body.appendChild(a); a.click(); a.remove();
  alert('EDXEIX snapshot exported. JSON was downloaded, copied to clipboard when allowed, and printed in Console.');
})();
JS;
}

$state = [
    'ok'=>false, 'error'=>null, 'update_result'=>null, 'generated_at'=>date('Y-m-d H:i:s'),
    'view'=>'needs_map', 'q'=>'', 'limit'=>160, 'drivers'=>[], 'vehicles'=>[], 'workbench'=>[],
    'driver_stats'=>[], 'vehicle_stats'=>[], 'snapshot'=>['ready'=>false,'missing'=>[]],
];
try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) { date_default_timezone_set((string)$config['app']['timezone']); $state['generated_at'] = date('Y-m-d H:i:s'); }
    $db = gov_bridge_db();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = mw3_request('action', '', 80);
        if ($action === 'update_verified_pair') { $state['update_result'] = mw3_update_verified_pair($db); }
        elseif ($action === 'import_snapshot') { $state['update_result'] = mw3_import_snapshot($db); }
    }
    $view = strtolower(mw3_request('view', 'needs_map', 40));
    if (!in_array($view, ['all','mapped','unmapped','needs_driver','needs_vehicle','needs_lessor','needs_map','conflict','suggestions'], true)) { $view = 'needs_map'; }
    $query = mw3_request('q', '', 120);
    $limit = mw3_limit();
    $state['view'] = $view; $state['q'] = $query; $state['limit'] = $limit;
    $state['driver_stats'] = mw3_table_stats($db, 'mapping_drivers', ['edxeix_driver_id','driver_id']);
    $state['vehicle_stats'] = mw3_table_stats($db, 'mapping_vehicles', ['edxeix_vehicle_id','vehicle_id']);
    $state['snapshot'] = mw3_snapshot_ready($db);
    $snapshot = mw3_load_snapshot($db);
    $driversView = in_array($view, ['needs_vehicle'], true) ? 'all' : ($view === 'needs_map' ? 'all' : $view);
    $vehiclesView = in_array($view, ['needs_driver'], true) ? 'all' : ($view === 'needs_map' ? 'all' : $view);
    $drivers = mw3_table_rows($db, 'mapping_drivers', $driversView, $query, $limit);
    $vehicles = mw3_table_rows($db, 'mapping_vehicles', $vehiclesView, $query, max($limit, 500));
    $vehicleByUuid = [];
    $vehicleByPlate = [];
    foreach ($vehicles as $vehicle) {
        $uuid = (string)mw3_value($vehicle, ['external_vehicle_id','vehicle_external_id','vehicle_uuid'], '');
        $plate = mw3_plate_norm((string)mw3_value($vehicle, ['plate','vehicle_plate'], ''));
        if ($uuid !== '') { $vehicleByUuid[$uuid] = $vehicle; }
        if ($plate !== '') { $vehicleByPlate[$plate] = $vehicle; }
    }
    $workbench = [];
    foreach ($drivers as $driver) {
        $driverId = (string)mw3_value($driver, ['edxeix_driver_id','driver_id'], '');
        $driverLessor = (string)mw3_value($driver, ['edxeix_lessor_id'], '');
        $activeUuid = (string)mw3_value($driver, ['active_vehicle_uuid'], '');
        $activePlate = (string)mw3_value($driver, ['active_vehicle_plate'], '');
        $vehicle = $activeUuid !== '' && isset($vehicleByUuid[$activeUuid]) ? $vehicleByUuid[$activeUuid] : ($vehicleByPlate[mw3_plate_norm($activePlate)] ?? []);
        $vehicleId = $vehicle ? (string)mw3_value($vehicle, ['edxeix_vehicle_id','vehicle_id'], '') : '';
        $vehicleLessor = $vehicle ? (string)mw3_value($vehicle, ['edxeix_lessor_id'], '') : '';
        $suggestLessor = $vehicleLessor !== '' ? $vehicleLessor : $driverLessor;
        $driverSuggestion = $driverId === '' ? mw3_best_driver_suggestion($snapshot, (string)mw3_value($driver, ['external_driver_name','driver_name'], ''), $suggestLessor) : ['id'=>$driverId,'label'=>'current mapping','lessor_id'=>$driverLessor,'score'=>100,'reason'=>'current mapping'];
        $suggestLessor = $suggestLessor !== '' ? $suggestLessor : (string)($driverSuggestion['lessor_id'] ?? '');
        $vehicleSuggestion = ($vehicle && $vehicleId === '') ? mw3_best_vehicle_suggestion($snapshot, (string)mw3_value($vehicle, ['plate','vehicle_plate'], $activePlate), $suggestLessor) : ['id'=>$vehicleId,'label'=>'current mapping','lessor_id'=>$vehicleLessor,'score'=>$vehicle ? 100 : 0,'reason'=>$vehicle ? 'current mapping' : 'no vehicle row'];
        $suggestLessor = $suggestLessor !== '' ? $suggestLessor : (string)($vehicleSuggestion['lessor_id'] ?? '');
        $conflict = ($driverLessor !== '' && $vehicleLessor !== '' && $driverLessor !== $vehicleLessor);
        $needsMap = ($driverId === '' || ($vehicle && $vehicleId === '') || $driverLessor === '' || ($vehicle && $vehicleLessor === ''));
        $hasSuggestion = (($driverSuggestion['score'] ?? 0) >= 70 || ($vehicleSuggestion['score'] ?? 0) >= 70);
        if ($view === 'needs_map' && !$needsMap) { continue; }
        if ($view === 'conflict' && !$conflict) { continue; }
        if ($view === 'suggestions' && !$hasSuggestion) { continue; }
        $workbench[] = [
            'driver'=>$driver, 'vehicle'=>$vehicle, 'driver_id'=>$driverId, 'vehicle_id'=>$vehicleId,
            'driver_lessor'=>$driverLessor, 'vehicle_lessor'=>$vehicleLessor, 'suggest_lessor'=>$suggestLessor,
            'driver_suggestion'=>$driverSuggestion, 'vehicle_suggestion'=>$vehicleSuggestion, 'conflict'=>$conflict, 'needs_map'=>$needsMap,
        ];
    }
    $state['drivers'] = $drivers; $state['vehicles'] = $vehicles; $state['workbench'] = $workbench; $state['ok'] = true;
} catch (Throwable $e) { $state['error'] = $e->getMessage(); }

if (mw3_request('format', '', 20) === 'json') {
    gov_bridge_json_response([
        'ok'=>$state['ok'], 'script'=>'ops/mapping-workbench-v3.php', 'generated_at'=>$state['generated_at'],
        'read_only'=>$_SERVER['REQUEST_METHOD'] !== 'POST', 'json_sanitized'=>true, 'raw_payload_json_included'=>false,
        'view'=>$state['view'], 'query'=>$state['q'], 'limit'=>$state['limit'],
        'driver_stats'=>$state['driver_stats'], 'vehicle_stats'=>$state['vehicle_stats'], 'snapshot'=>$state['snapshot'],
        'workbench_count'=>count($state['workbench']),
        'workbench'=>array_map(static function(array $item): array {
            $d=$item['driver']; $v=$item['vehicle'];
            return [
                'driver_row_id'=>(int)mw3_value($d,['id'],0),
                'driver_name'=>(string)mw3_value($d,['external_driver_name','driver_name'],''),
                'driver_uuid'=>(string)mw3_value($d,['external_driver_id','driver_external_id','driver_uuid'],''),
                'edxeix_driver_id'=>$item['driver_id'], 'driver_lessor_id'=>$item['driver_lessor'],
                'active_vehicle_plate'=>(string)mw3_value($d,['active_vehicle_plate'],''),
                'vehicle_row_id'=>$v ? (int)mw3_value($v,['id'],0) : 0,
                'vehicle_plate'=>$v ? (string)mw3_value($v,['plate','vehicle_plate'],'') : '',
                'edxeix_vehicle_id'=>$item['vehicle_id'], 'vehicle_lessor_id'=>$item['vehicle_lessor'],
                'suggest_lessor_id'=>$item['suggest_lessor'], 'driver_suggestion'=>$item['driver_suggestion'], 'vehicle_suggestion'=>$item['vehicle_suggestion'],
                'conflict'=>$item['conflict'], 'needs_map'=>$item['needs_map'],
            ];
        }, $state['workbench']),
        'error'=>$state['error'],
        'note'=>'Sanitized Mapping Workbench V3 JSON. No raw payloads, cookies, sessions, or credentials are included.',
    ]);
    exit;
}

$consoleScript = mw3_console_script();
if (function_exists('opsui_shell_begin')) {
    opsui_shell_begin([
        'title'=>'Mapping Workbench V3',
        'page_title'=>'Mapping Workbench V3',
        'active_section'=>'Mapping Governance',
        'subtitle'=>'Verified driver + active vehicle + EDXEIX lessor workflow',
        'breadcrumbs'=>'Αρχική / Mapping / Workbench V3',
        'safe_notice'=>'GET is read-only. POST is guarded and limited to local mapping fields plus audit rows. This page does not call Bolt, EDXEIX, or AADE and does not create jobs.',
        'force_safe_notice'=>true,
    ]);
} else {
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Mapping Workbench V3</title></head><body>';
}
?>
<style>
.mw3-hero{border-left:5px solid #2f9e44}.mw3-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.mw3-two{display:grid;grid-template-columns:1fr 1fr;gap:14px}.mw3-workbench{display:grid;gap:14px}.mw3-row{border:1px solid #d8dde7;border-radius:8px;background:#fff;box-shadow:0 6px 18px rgba(26,33,52,.05);padding:14px}.mw3-row.conflict{border-left:5px solid #b42318}.mw3-row.needs{border-left:5px solid #d4922d}.mw3-row.ready{border-left:5px solid #2f9e44}.mw3-pair{display:grid;grid-template-columns:1fr 1fr 1.2fr;gap:12px}.mw3-box{background:#f8fbff;border:1px solid #e1e7f0;border-radius:7px;padding:11px}.mw3-box h3{margin:0 0 8px;font-size:16px}.mw3-mono{font-family:Consolas,Menlo,monospace;font-size:12px;word-break:break-all;background:#eef2ff;border-radius:4px;padding:2px 4px}.mw3-form{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin-top:10px}.mw3-form input,.mw3-form textarea,.mw3-form select{width:100%;padding:8px 10px;border:1px solid #d8dde7;border-radius:6px}.mw3-form label{font-size:12px;font-weight:700;color:#27385f}.mw3-form .wide{grid-column:span 2}.mw3-form .full{grid-column:1/-1}.mw3-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.mw3-actions a,.mw3-btn{display:inline-block;background:#4f5ea7;color:#fff;text-decoration:none;border:0;border-radius:5px;padding:9px 11px;font-weight:700;font-size:13px;cursor:pointer}.mw3-btn.green,.mw3-actions a.green{background:#2f9e44}.mw3-btn.orange,.mw3-actions a.orange{background:#c96f00}.mw3-btn.dark,.mw3-actions a.dark{background:#334155}.mw3-note{background:#fff7ed;border:1px solid #fed7aa;border-radius:7px;padding:10px;color:#9a3412}.mw3-good{color:#166534}.mw3-warn{color:#b45309}.mw3-bad{color:#991b1b}.mw3-table-wrap{overflow:auto;border:1px solid #d8dde7;border-radius:8px}.mw3-table{width:100%;border-collapse:collapse;min-width:1100px}.mw3-table th,.mw3-table td{padding:9px 10px;border-bottom:1px solid #e1e7f0;text-align:left;vertical-align:top}.mw3-table th{font-size:12px;text-transform:uppercase;background:#f8fafc}.mw3-codebox{min-height:220px;width:100%;font-family:Consolas,Menlo,monospace;font-size:12px;background:#0b1220;color:#dbeafe;border-radius:8px;border:1px solid #111827;padding:12px}.mw3-filters{display:grid;grid-template-columns:1fr 180px 120px auto;gap:10px;align-items:end}.mw3-filters input,.mw3-filters select{width:100%;padding:9px;border:1px solid #d8dde7;border-radius:6px}@media(max-width:1100px){.mw3-grid,.mw3-two,.mw3-pair,.mw3-form,.mw3-filters{grid-template-columns:1fr}.mw3-form .wide{grid-column:auto}}
</style>
<?php if (function_exists('gov_mapping_nav_render')) { gov_mapping_nav_render('/ops/mapping-workbench-v3.php'); } ?>
<section class="card hero mw3-hero">
  <h1>Mapping Workbench V3</h1>
  <p>Grouped workflow for mapping a Bolt driver together with the active Bolt vehicle and EDXEIX company/lessor. Use it after importing the EDXEIX dropdown snapshot, then update only verified IDs.</p>
  <div><?= mw3_badge('NO BOLT CALL', 'good') ?> <?= mw3_badge('NO EDXEIX SUBMIT', 'good') ?> <?= mw3_badge('NO AADE CALL', 'good') ?> <?= mw3_badge('AUDITED POST ONLY', 'info') ?></div>
  <?php if ($state['error']): ?><p class="mw3-bad"><strong>Error:</strong> <?= mw3_h($state['error']) ?></p><?php endif; ?>
  <?php if ($state['update_result']): ?><p class="<?= mw3_h($state['update_result']['type'] === 'good' ? 'mw3-good' : 'mw3-warn') ?>"><strong><?= mw3_h($state['update_result']['message']) ?></strong></p><?php endif; ?>
  <div class="mw3-grid" style="margin-top:14px">
    <div class="metric"><strong><?= mw3_h(($state['driver_stats']['mapped'] ?? 0) . '/' . ($state['driver_stats']['total'] ?? 0)) ?></strong><span>Drivers mapped</span></div>
    <div class="metric"><strong><?= mw3_h(($state['driver_stats']['mapped_percent'] ?? 0) . '%') ?></strong><span>Driver coverage</span></div>
    <div class="metric"><strong><?= mw3_h(($state['vehicle_stats']['mapped'] ?? 0) . '/' . ($state['vehicle_stats']['total'] ?? 0)) ?></strong><span>Vehicles mapped</span></div>
    <div class="metric"><strong><?= mw3_h(($state['vehicle_stats']['mapped_percent'] ?? 0) . '%') ?></strong><span>Vehicle coverage</span></div>
  </div>
  <div class="mw3-actions">
    <a class="green" href="/ops/mapping-workbench-v3.php?view=needs_map">Needs Map</a>
    <a class="orange" href="/ops/mapping-workbench-v3.php?view=suggestions">Suggestions</a>
    <a class="dark" href="/ops/mapping-workbench-v3.php?view=conflict">Conflicts</a>
    <a class="dark" href="/ops/mapping-workbench-v3.php?format=json&view=<?= mw3_h($state['view']) ?>&limit=<?= (int)$state['limit'] ?>">Sanitized JSON</a>
  </div>
</section>
<section class="card">
  <h2>Snapshot status + EDXEIX scraper</h2>
  <?php $snap = $state['snapshot']; ?>
  <?php if (!$snap['ready']): ?><p class="mw3-warn"><strong>Snapshot tables missing:</strong> <?= mw3_h(implode(', ', $snap['missing'] ?? [])) ?>. Run the included SQL migration first.</p><?php endif; ?>
  <div class="mw3-grid">
    <div class="metric"><strong><?= mw3_h((string)($snap['lessors'] ?? 0)) ?></strong><span>Lessors stored</span></div>
    <div class="metric"><strong><?= mw3_h((string)($snap['drivers'] ?? 0)) ?></strong><span>Drivers stored</span></div>
    <div class="metric"><strong><?= mw3_h((string)($snap['vehicles'] ?? 0)) ?></strong><span>Vehicles stored</span></div>
    <div class="metric"><strong><?= mw3_h((string)($snap['last_seen_at'] ?? '')) ?></strong><span>Latest snapshot</span></div>
  </div>
  <div class="mw3-two" style="margin-top:14px">
    <div>
      <h3>1. Copy EDXEIX console scraper</h3>
      <p class="mw3-note">Run this inside the logged-in EDXEIX lease-agreement create page. It exports dropdown IDs/labels only. No cookies, tokens, passwords, or sessions are exported.</p>
      <textarea class="mw3-codebox" id="mw3_console_script" spellcheck="false"><?= mw3_h($consoleScript) ?></textarea>
      <div class="mw3-actions"><button class="mw3-btn dark" type="button" onclick="navigator.clipboard.writeText(document.getElementById('mw3_console_script').value)">Copy scraper</button></div>
    </div>
    <div>
      <h3>2. Import EDXEIX snapshot JSON</h3>
      <form method="post">
        <input type="hidden" name="action" value="import_snapshot">
        <textarea name="edxeix_snapshot_json" class="mw3-codebox" placeholder="Paste edxeix-snapshot-v3 JSON here"></textarea>
        <label style="display:block;margin-top:10px;font-weight:700">Confirmation phrase</label>
        <input name="confirm" placeholder="IMPORT EDXEIX SNAPSHOT" required style="width:100%;padding:9px;border:1px solid #d8dde7;border-radius:6px">
        <div class="mw3-actions"><button class="mw3-btn green" type="submit" <?= $snap['ready'] ? '' : 'disabled' ?>>Import snapshot</button></div>
      </form>
    </div>
  </div>
</section>
<section class="card">
  <h2>Filters</h2>
  <form method="get" class="mw3-filters">
    <div><label>Search</label><input name="q" value="<?= mw3_h($state['q']) ?>" placeholder="driver, UUID, phone, plate"></div>
    <div><label>View</label><select name="view">
      <?php foreach (['needs_map'=>'Needs any mapping','suggestions'=>'Snapshot suggestions','conflict'=>'Conflicts','needs_driver'=>'Needs driver ID','needs_vehicle'=>'Needs vehicle ID','needs_lessor'=>'Needs lessor ID','unmapped'=>'Unmapped ID rows','mapped'=>'Mapped ID rows','all'=>'All rows'] as $value=>$label): ?>
      <option value="<?= mw3_h($value) ?>" <?= $state['view']===$value?'selected':'' ?>><?= mw3_h($label) ?></option>
      <?php endforeach; ?>
    </select></div>
    <div><label>Limit</label><input type="number" name="limit" min="1" max="500" value="<?= (int)$state['limit'] ?>"></div>
    <div><button class="mw3-btn" type="submit">Apply</button></div>
  </form>
</section>
<section class="card">
  <h2>Grouped driver + active vehicle workbench</h2>
  <p class="small">Use the suggested IDs only after visual confirmation against EDXEIX. Normal updates are blocked if the ID is not present in the latest imported snapshot under the selected lessor.</p>
  <?php if (!$state['workbench']): ?><p>No rows match the current filter.</p><?php endif; ?>
  <div class="mw3-workbench">
  <?php foreach ($state['workbench'] as $item):
      $d=$item['driver']; $v=$item['vehicle'];
      $driverRowId=(int)mw3_value($d,['id'],0); $vehicleRowId=$v ? (int)mw3_value($v,['id'],0) : 0;
      $driverName=(string)mw3_value($d,['external_driver_name','driver_name'],'');
      $activePlate=(string)mw3_value($d,['active_vehicle_plate'],'');
      $vehiclePlate=$v ? (string)mw3_value($v,['plate','vehicle_plate'],$activePlate) : $activePlate;
      $suggestDriver=(string)($item['driver_suggestion']['id'] ?: $item['driver_id']);
      $suggestVehicle=(string)($item['vehicle_suggestion']['id'] ?: $item['vehicle_id']);
      $suggestLessor=(string)($item['suggest_lessor'] ?: $item['driver_lessor'] ?: $item['vehicle_lessor']);
      $rowClass=$item['conflict'] ? 'conflict' : ($item['needs_map'] ? 'needs' : 'ready');
  ?>
    <article class="mw3-row <?= mw3_h($rowClass) ?>">
      <div class="mw3-pair">
        <div class="mw3-box"><h3>Driver</h3><strong><?= mw3_h($driverName) ?></strong><br><span class="mw3-mono"><?= mw3_h(mw3_value($d,['external_driver_id','driver_external_id','driver_uuid'],'')) ?></span><br>Current driver ID: <?= $item['driver_id'] !== '' ? mw3_badge($item['driver_id'],'good') : mw3_badge('missing','warn') ?><br>Driver lessor: <?= $item['driver_lessor'] !== '' ? mw3_badge($item['driver_lessor'],'info') : mw3_badge('missing','warn') ?></div>
        <div class="mw3-box"><h3>Active vehicle</h3><strong><?= mw3_h($vehiclePlate ?: 'No active vehicle') ?></strong><br><span class="mw3-mono"><?= mw3_h($v ? mw3_value($v,['external_vehicle_id','vehicle_external_id','vehicle_uuid'],'') : mw3_value($d,['active_vehicle_uuid'],'')) ?></span><br>Current vehicle ID: <?= $item['vehicle_id'] !== '' ? mw3_badge($item['vehicle_id'],'good') : mw3_badge($v ? 'missing' : 'no row','warn') ?><br>Vehicle lessor: <?= $item['vehicle_lessor'] !== '' ? mw3_badge($item['vehicle_lessor'],'info') : mw3_badge($v ? 'missing' : 'no row','warn') ?></div>
        <div class="mw3-box"><h3>Snapshot suggestion</h3>
          Driver: <?= ($item['driver_suggestion']['score'] ?? 0) >= 70 ? mw3_badge($item['driver_suggestion']['id'] . ' ' . $item['driver_suggestion']['label'], 'good') : mw3_badge('no strong driver suggestion','neutral') ?><br>
          Vehicle: <?= ($item['vehicle_suggestion']['score'] ?? 0) >= 70 ? mw3_badge($item['vehicle_suggestion']['id'] . ' ' . $item['vehicle_suggestion']['label'], 'good') : mw3_badge('no strong vehicle suggestion','neutral') ?><br>
          Lessor: <?= $suggestLessor !== '' ? mw3_badge($suggestLessor,'info') : mw3_badge('missing','warn') ?><br>
          <?php if ($item['conflict']): ?><strong class="mw3-bad">Conflict: driver lessor and vehicle lessor differ.</strong><?php endif; ?>
        </div>
      </div>
      <form method="post" class="mw3-form">
        <input type="hidden" name="action" value="update_verified_pair">
        <input type="hidden" name="driver_row_id" value="<?= (int)$driverRowId ?>">
        <input type="hidden" name="vehicle_row_id" value="<?= (int)$vehicleRowId ?>">
        <div><label>EDXEIX lessor ID</label><input name="edxeix_lessor_id" value="<?= mw3_h($suggestLessor) ?>" required></div>
        <div><label>EDXEIX driver ID</label><input name="edxeix_driver_id" value="<?= mw3_h($suggestDriver) ?>" <?= $driverRowId > 0 ? 'required' : 'disabled' ?>></div>
        <div><label>EDXEIX vehicle ID</label><input name="edxeix_vehicle_id" value="<?= mw3_h($suggestVehicle) ?>" <?= $vehicleRowId > 0 ? 'required' : 'disabled' ?>></div>
        <div><label>Confirmation</label><input name="confirm" placeholder="UPDATE VERIFIED MAPPING" required></div>
        <div class="full"><label>Override phrase, only if snapshot validation is wrong</label><input name="override_confirm" placeholder="OVERRIDE SNAPSHOT VALIDATION"></div>
        <div class="full"><button class="mw3-btn green" type="submit">Update verified mapping</button></div>
      </form>
    </article>
  <?php endforeach; ?>
  </div>
</section>
<?php if (function_exists('opsui_shell_end')) { opsui_shell_end(); } else { echo '</body></html>'; } ?>
