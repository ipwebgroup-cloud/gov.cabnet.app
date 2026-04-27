<?php
/**
 * gov.cabnet.app — Bolt Completed-Order Visibility Analysis v2.5
 *
 * Reads sanitized Bolt visibility JSONL evidence only.
 * Does not call Bolt, EDXEIX, DB, job staging, or mapping update logic.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

date_default_timezone_set('Europe/Athens');

function bcv_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function bcv_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . bcv_h($type) . '">' . bcv_h($text) . '</span>'; }
function bcv_metric($value, string $label): string { return '<div class="metric"><strong>' . bcv_h((string)$value) . '</strong><span>' . bcv_h($label) . '</span></div>'; }
function bcv_first(array $row, array $keys, $default = null) { foreach ($keys as $key) { if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') return $row[$key]; } return $default; }
function bcv_int(array $row, array $keys): int { $v = bcv_first($row, $keys, 0); return is_numeric($v) ? (int)$v : 0; }
function bcv_bool(array $row, array $keys): bool { $v = bcv_first($row, $keys, false); if (is_bool($v)) return $v; if (is_numeric($v)) return (int)$v > 0; return in_array(strtolower(trim((string)$v)), ['1','true','yes','y','match','matched'], true); }
function bcv_local(?string $value): string { if (!$value) return ''; try { return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('Europe/Athens'))->format('Y-m-d H:i:s T'); } catch (Throwable $e) { return (string)$value; } }
function bcv_stage(array $row): string {
    foreach (['stage','snapshot_stage','capture_stage'] as $key) { if (!empty($row[$key])) return (string)$row[$key]; }
    $label = strtolower((string)bcv_first($row, ['label','snapshot_label'], ''));
    if (strpos($label, 'accepted') !== false || strpos($label, 'assigned') !== false) return 'accepted-assigned';
    if (strpos($label, 'pickup') !== false || strpos($label, 'waiting') !== false) return 'pickup-waiting';
    if (strpos($label, 'started') !== false) return 'trip-started';
    if (strpos($label, 'completed') !== false) return 'completed';
    if (strpos($label, 'watch') !== false) return 'auto-watch';
    return 'manual-other';
}
function bcv_read_jsonl(string $file): array {
    $rows = [];
    if (!is_file($file) || !is_readable($file)) return $rows;
    $handle = fopen($file, 'rb');
    if (!$handle) return $rows;
    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') continue;
        $decoded = json_decode($line, true);
        if (is_array($decoded)) $rows[] = $decoded;
    }
    fclose($handle);
    return $rows;
}
function bcv_stage_summary(array $rows): array {
    $names = ['accepted-assigned','pickup-waiting','trip-started','completed','auto-watch','manual-other'];
    $out = [];
    foreach ($names as $name) {
        $out[$name] = ['count'=>0,'latest_raw'=>'','latest_local'=>'','max_orders'=>0,'max_samples'=>0,'max_local_rows'=>0,'driver_match'=>false,'vehicle_match'=>false,'order_match'=>false];
    }
    foreach ($rows as $row) {
        $stage = bcv_stage($row);
        if (!isset($out[$stage])) $stage = 'manual-other';
        $orders = bcv_int($row, ['orders_seen','order_count','orders_count','visible_order_count']);
        $samples = bcv_int($row, ['sample_count','sanitized_sample_count','samples_seen']);
        $locals = bcv_int($row, ['local_recent_count','local_rows','local_recent_rows']);
        $captured = (string)bcv_first($row, ['captured_at','generated_at','created_at'], '');
        $out[$stage]['count']++;
        $out[$stage]['max_orders'] = max($out[$stage]['max_orders'], $orders);
        $out[$stage]['max_samples'] = max($out[$stage]['max_samples'], $samples);
        $out[$stage]['max_local_rows'] = max($out[$stage]['max_local_rows'], $locals);
        $out[$stage]['driver_match'] = $out[$stage]['driver_match'] || bcv_bool($row, ['watch_driver_match','driver_match','watched_driver_match']);
        $out[$stage]['vehicle_match'] = $out[$stage]['vehicle_match'] || bcv_bool($row, ['watch_vehicle_match','vehicle_match','watched_vehicle_match']);
        $out[$stage]['order_match'] = $out[$stage]['order_match'] || bcv_bool($row, ['watch_order_match','order_match','watched_order_match']);
        if ($captured !== '') {
            $old = strtotime($out[$stage]['latest_raw']) ?: 0;
            $new = strtotime($captured) ?: 0;
            if ($new >= $old) { $out[$stage]['latest_raw'] = $captured; $out[$stage]['latest_local'] = bcv_local($captured); }
        }
    }
    return $out;
}
function bcv_classify(array $summary): array {
    $pre = (int)$summary['accepted-assigned']['max_orders'] + (int)$summary['pickup-waiting']['max_orders'] + (int)$summary['trip-started']['max_orders'];
    $post = (int)$summary['completed']['max_orders'] + (int)$summary['auto-watch']['max_orders'];
    $driver = false; $vehicle = false; $order = false; $max = 0;
    foreach ($summary as $s) { $driver = $driver || $s['driver_match']; $vehicle = $vehicle || $s['vehicle_match']; $order = $order || $s['order_match']; $max = max($max, (int)$s['max_orders']); }
    if ($pre > 0) {
        return ['code'=>'PRE_COMPLETION_VISIBILITY_PRESENT','type'=>'good','text'=>'Bolt exposed at least one visible order before completion.','suitability'=>'Potentially usable for pre-departure EDXEIX workflow, subject to preflight and timing validation.','pre'=>$pre,'post'=>$post,'driver'=>$driver,'vehicle'=>$vehicle,'order'=>$order,'max'=>$max];
    }
    if ($post > 0) {
        return ['code'=>'COMPLETION_ONLY_VISIBILITY','type'=>'warn','text'=>'Bolt did not expose the watched ride during accepted/pickup/started stages in this test; visibility appeared after completion.','suitability'=>'Current visibility path is not sufficient for pre-departure EDXEIX submission unless another Bolt endpoint exposes active/future trips.','pre'=>$pre,'post'=>$post,'driver'=>$driver,'vehicle'=>$vehicle,'order'=>$order,'max'=>$max];
    }
    if ($driver || $vehicle) {
        return ['code'=>'WATCH_MATCH_WITHOUT_ORDER_VISIBILITY','type'=>'warn','text'=>'Watched driver/vehicle identifiers matched, but no visible order appeared in the captured timeline.','suitability'=>'Not sufficient for EDXEIX submission.','pre'=>$pre,'post'=>$post,'driver'=>$driver,'vehicle'=>$vehicle,'order'=>$order,'max'=>$max];
    }
    return ['code'=>'NO_WATCH_VISIBILITY','type'=>'bad','text'=>'No watched order, driver, or vehicle visibility was confirmed in the captured timeline.','suitability'=>'Not sufficient for EDXEIX submission.','pre'=>$pre,'post'=>$post,'driver'=>$driver,'vehicle'=>$vehicle,'order'=>$order,'max'=>$max];
}

$date = preg_replace('/[^0-9\-]/', '', (string)($_GET['date'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$file = '/home/cabnet/gov.cabnet.app_app/storage/artifacts/bolt-api-visibility/' . $date . '.jsonl';
$rows = bcv_read_jsonl($file);
$summary = bcv_stage_summary($rows);
$class = bcv_classify($summary);
$first = ''; $latest = '';
foreach ($rows as $row) {
    $captured = (string)bcv_first($row, ['captured_at','generated_at','created_at'], '');
    $ts = $captured !== '' ? (strtotime($captured) ?: 0) : 0;
    if ($ts > 0) { if ($first === '' || $ts < (strtotime($first) ?: PHP_INT_MAX)) $first = $captured; if ($latest === '' || $ts > (strtotime($latest) ?: 0)) $latest = $captured; }
}
$payload = ['ok'=>true,'script'=>'ops/completed-visibility.php','generated_at'=>date('c'),'date'=>$date,'safety_contract'=>['calls_bolt'=>false,'calls_edxeix'=>false,'reads_database'=>false,'writes_database'=>false,'stages_jobs'=>false,'updates_mappings'=>false,'live_edxeix_submission'=>'disabled_not_used','source'=>'sanitized Bolt visibility JSONL snapshots only'],'source_file'=>$file,'total_snapshots'=>count($rows),'first_captured_at_local'=>bcv_local($first),'latest_captured_at_local'=>bcv_local($latest),'classification'=>$class,'stages'=>$summary,'links'=>['html'=>'/ops/completed-visibility.php','json'=>'/ops/completed-visibility.php?format=json&date=' . rawurlencode($date),'evidence_bundle'=>'/ops/evidence-bundle.php?date=' . rawurlencode($date),'evidence_report'=>'/ops/evidence-report.php?format=md&date=' . rawurlencode($date),'preflight_review'=>'/ops/preflight-review.php','route_index'=>'/ops/route-index.php']];
if ($format === 'json') { header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL; exit; }
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Completed-Order Visibility Analysis | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.5">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand"><div class="gov-brand-crest">ΕΔ</div><div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div></div>
    <div class="gov-top-links"><a href="/ops/home.php">Αρχική</a><a href="/ops/test-session.php">Test Session</a><a href="/ops/evidence-bundle.php">Evidence</a><a href="/ops/preflight-review.php">Preflight Review</a><a class="gov-logout" href="/ops/route-index.php">Route Index</a></div>
</div>
<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Visibility Analysis</h3><p>Completed-order exposure review</p>
        <div class="gov-side-group"><div class="gov-side-group-title">Evidence</div><a class="gov-side-link active" href="/ops/completed-visibility.php">Completed Visibility</a><a class="gov-side-link" href="/ops/evidence-bundle.php">Evidence Bundle</a><a class="gov-side-link" href="/ops/evidence-report.php?format=md">Evidence Report</a><a class="gov-side-link" href="/ops/bolt-api-visibility.php">Bolt Visibility</a><div class="gov-side-group-title">Decision</div><a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a><a class="gov-side-link" href="/ops/test-session.php">Test Session</a><a class="gov-side-link" href="/ops/route-index.php">Route Index</a></div>
        <div class="gov-side-note">Reads sanitized evidence only. No Bolt, EDXEIX, DB, job, or mapping action.</div>
    </aside>
    <div class="gov-content">
        <div class="gov-page-header"><div><h1 class="gov-page-title">Ανάλυση ορατότητας μετά την ολοκλήρωση</h1><div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Ανάλυση ορατότητας μετά την ολοκλήρωση</div></div><div class="gov-tabs"><a class="gov-tab active" href="/ops/completed-visibility.php">Καρτέλα</a><a class="gov-tab" href="/ops/completed-visibility.php?format=json&date=<?= bcv_h($date) ?>">JSON</a><a class="gov-tab" href="/ops/evidence-report.php?format=md&date=<?= bcv_h($date) ?>">Report</a><a class="gov-tab" href="/ops/preflight-review.php">Preflight</a></div></div>
        <main class="wrap wrap-shell">
            <section class="safety"><strong>READ-ONLY VISIBILITY ANALYSIS.</strong> This page reads sanitized Bolt visibility snapshots only. It does not call Bolt, EDXEIX, database, or job staging.</section>
            <section class="card hero <?= bcv_h($class['type']) ?>">
                <h1>Bolt Completed-Order Visibility Analysis</h1>
                <p><?= bcv_h($class['text']) ?></p>
                <div><?= bcv_badge($class['code'], $class['type']) ?> <?= bcv_badge('LIVE SUBMIT OFF', 'good') ?> <?= bcv_badge('NO BACKEND ACTIONS', 'good') ?></div>
                <div class="grid" style="margin-top:14px"><?= bcv_metric(count($rows), 'Total snapshots') ?><?= bcv_metric((string)$class['pre'], 'Pre-completion orders seen') ?><?= bcv_metric((string)$class['post'], 'Completion/auto-watch orders seen') ?><?= bcv_metric((string)$class['max'], 'Max orders seen') ?></div>
                <div class="actions"><a class="btn" href="/ops/completed-visibility.php?format=json&date=<?= bcv_h($date) ?>">Open JSON</a><a class="btn dark" href="/ops/evidence-report.php?format=md&date=<?= bcv_h($date) ?>">Open Evidence Markdown</a><a class="btn warn" href="/ops/preflight-review.php">Open Preflight Review</a></div>
            </section>
            <section class="two"><div class="card"><h2>Decision summary</h2><div class="kv"><div class="k">Analysis date</div><div><strong><?= bcv_h($date) ?></strong></div><div class="k">First capture local</div><div><?= bcv_h(bcv_local($first)) ?></div><div class="k">Latest capture local</div><div><?= bcv_h(bcv_local($latest)) ?></div><div class="k">Driver match</div><div><?= $class['driver'] ? bcv_badge('YES','good') : bcv_badge('NO','bad') ?></div><div class="k">Vehicle match</div><div><?= $class['vehicle'] ? bcv_badge('YES','good') : bcv_badge('NO','bad') ?></div><div class="k">Order match</div><div><?= $class['order'] ? bcv_badge('YES','good') : bcv_badge('NO','warn') ?></div></div></div><div class="card"><h2>EDXEIX suitability</h2><p><strong><?= bcv_h($class['suitability']) ?></strong></p><p>This does not mean the bridge failed. It means the current Bolt visibility path did not provide a future-safe candidate before the trip started/completed.</p><p class="badline"><strong>Do not stage jobs or submit live from a completed/historical Bolt order.</strong></p></div></section>
            <section class="card"><h2>Stage visibility coverage</h2><div class="table-wrap"><table><thead><tr><th>Stage</th><th>Count</th><th>Latest local time</th><th>Max orders</th><th>Max samples</th><th>Max local rows</th><th>Driver</th><th>Vehicle</th><th>Order</th></tr></thead><tbody><?php foreach ($summary as $stageName => $stage): ?><tr><td><strong><?= bcv_h($stageName) ?></strong></td><td><?= bcv_h($stage['count']) ?></td><td><?= bcv_h($stage['latest_local']) ?></td><td><?= bcv_h($stage['max_orders']) ?></td><td><?= bcv_h($stage['max_samples']) ?></td><td><?= bcv_h($stage['max_local_rows']) ?></td><td><?= $stage['driver_match'] ? bcv_badge('YES','good') : bcv_badge('NO','neutral') ?></td><td><?= $stage['vehicle_match'] ? bcv_badge('YES','good') : bcv_badge('NO','neutral') ?></td><td><?= $stage['order_match'] ? bcv_badge('YES','good') : bcv_badge('NO','warn') ?></td></tr><?php endforeach; ?></tbody></table></div></section>
            <section class="card"><h2>Recommended implementation conclusion</h2><ol class="timeline"><li>Keep live EDXEIX submission disabled.</li><li>Do not create local EDXEIX jobs from completed, terminal, cancelled, historical, or not-future-safe Bolt rows.</li><li>Document that this test indicates completion-only visibility for the current Bolt path.</li><li>Investigate whether Bolt provides another endpoint, webhook, or feed for future/active assigned trips before the EDXEIX deadline.</li><li>If no earlier visibility exists, use the bridge for audit/evidence only, not automatic pre-departure EDXEIX submission.</li></ol><p><code><?= bcv_h($file) ?></code></p></section>
        </main>
    </div>
</div>
</body>
</html>
