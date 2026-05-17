<?php
/**
 * gov.cabnet.app — Pre-ride readiness watch page.
 * v3.2.28
 *
 * No EDXEIX transport. Optional metadata capture requires POST.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

$auth = __DIR__ . '/_auth.php';
if (is_file($auth)) {
    require_once $auth;
}

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_readiness_watch_lib.php';

function prw_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prw_yes_no($value): string
{
    return !empty($value) ? 'YES' : 'NO';
}

function prw_badge(bool $ok): string
{
    return $ok ? 'good' : 'bad';
}

$captureReady = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
    && isset($_POST['capture_ready'])
    && (string)$_POST['capture_ready'] === '1';
$debugSource = isset($_GET['debug_source']) && (string)$_GET['debug_source'] === '1';
$autoRefresh = isset($_GET['auto_refresh']) ? max(10, min(120, (int)$_GET['auto_refresh'])) : 0;

$result = null;
$error = null;
try {
    $result = gov_prw_run([
        'capture_ready' => $captureReady,
        'debug_source' => $debugSource,
        'include_latest_ready' => true,
    ]);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$classification = is_array($result) ? ($result['classification'] ?? []) : [];
$ready = !empty($result['ready_for_operator_review']);
$latest = is_array($result['latest_mail_candidate_report']['candidate'] ?? null) ? $result['latest_mail_candidate_report']['candidate'] : [];
$packet = is_array($result['one_shot_readiness_packet']['operator_packet'] ?? null) ? $result['one_shot_readiness_packet']['operator_packet'] : [];
$readiness = is_array($result['one_shot_readiness_packet'] ?? null) ? $result['one_shot_readiness_packet'] : [];
$blockers = is_array($readiness['readiness_blockers'] ?? null) ? $readiness['readiness_blockers'] : [];

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($autoRefresh > 0): ?><meta http-equiv="refresh" content="<?= (int)$autoRefresh ?>"><?php endif; ?>
    <title>Pre-Ride Readiness Watch</title>
    <style>
        :root{--ink:#0f172a;--muted:#64748b;--line:#e2e8f0;--good:#dcfce7;--bad:#fee2e2;--warn:#fef9c3;--card:#fff;--bg:#f8fafc;--brand:#111827}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}.wrap{max-width:1180px;margin:32px auto;padding:0 16px}.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:18px;margin:16px 0;box-shadow:0 8px 24px rgba(15,23,42,.04)}.hero{border-color:<?= $ready ? '#86efac' : '#fecaca' ?>;background:<?= $ready ? '#f0fdf4' : '#fff7f7' ?>}.muted{color:var(--muted)}.pill{display:inline-block;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700}.good{background:var(--good);color:#166534}.bad{background:var(--bad);color:#991b1b}.warn{background:var(--warn);color:#854d0e}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.kv{border:1px solid var(--line);border-radius:12px;padding:12px;background:#fff}.label{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted)}.value{font-weight:700;margin-top:4px;word-break:break-word}button,.btn{display:inline-block;border:0;border-radius:10px;background:var(--brand);color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}.btn.secondary{background:#e5e7eb;color:#111827}.btn.warn{background:#ca8a04;color:#fff}.actions{display:flex;gap:8px;flex-wrap:wrap}.danger-text{color:#991b1b;font-weight:700}pre{white-space:pre-wrap;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:14px;overflow:auto;max-height:520px}.small{font-size:13px}ul{margin:8px 0 0 22px}
    </style>
</head>
<body>
<div class="wrap">
    <div class="card hero">
        <h1>Pre-Ride Readiness Watch</h1>
        <p class="muted">v3.2.28 — watches latest pre-ride candidate readiness. No EDXEIX transport, no AADE call, no queue job.</p>
        <?php if ($error): ?>
            <p><strong>Classification:</strong> <span class="pill bad">WATCH_ERROR</span></p>
            <p class="danger-text"><?= prw_h($error) ?></p>
        <?php else: ?>
            <p><strong>Classification:</strong> <span class="pill <?= $ready ? 'good' : 'bad' ?>"><?= prw_h($classification['code'] ?? 'UNKNOWN') ?></span></p>
            <p><?= prw_h($classification['message'] ?? '') ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Actions</h2>
        <div class="actions">
            <a class="btn secondary" href="pre-ride-readiness-watch.php">Refresh dry-run</a>
            <a class="btn secondary" href="pre-ride-readiness-watch.php?auto_refresh=30">Auto-refresh 30s</a>
            <a class="btn secondary" href="pre-ride-readiness-watch.php?debug_source=1">Debug source structure</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Capture sanitized metadata only if the latest pre-ride candidate is ready? No EDXEIX submit will be performed.');">
                <input type="hidden" name="capture_ready" value="1">
                <button type="submit">Capture ready metadata</button>
            </form>
        </div>
        <p class="muted small">Capture writes only sanitized metadata to <code>edxeix_pre_ride_candidates</code>. It does not submit.</p>
    </div>

    <div class="card">
        <h2>Safety status</h2>
        <div class="grid">
            <div class="kv"><div class="label">Ready for operator review</div><div class="value pill <?= prw_badge($ready) ?>"><?= prw_yes_no($ready) ?></div></div>
            <div class="kv"><div class="label">Latest mail candidate ready</div><div class="value pill <?= prw_badge(!empty($result['candidate_ready_in_latest_mail'])) ?>"><?= prw_yes_no($result['candidate_ready_in_latest_mail'] ?? false) ?></div></div>
            <div class="kv"><div class="label">Captured candidate ID</div><div class="value"><?= prw_h($result['captured_candidate_id'] ?? ($packet['candidate_id'] ?? '-')) ?></div></div>
            <div class="kv"><div class="label">Transport performed</div><div class="value pill bad">NO</div></div>
            <div class="kv"><div class="label">Session ready</div><div class="value pill <?= prw_badge(!empty($readiness['live_gate_summary']['session_ready'])) ?>"><?= prw_yes_no($readiness['live_gate_summary']['session_ready'] ?? false) ?></div></div>
            <div class="kv"><div class="label">Duplicate success</div><div class="value pill <?= !empty($readiness['duplicate_check']['duplicate_success_detected']) ? 'bad' : 'good' ?>"><?= prw_yes_no($readiness['duplicate_check']['duplicate_success_detected'] ?? false) ?></div></div>
        </div>
    </div>

    <?php if ($blockers): ?>
    <div class="card hero">
        <h2>Readiness blockers</h2>
        <ul><?php foreach ($blockers as $b): ?><li><?= prw_h($b) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Latest pre-ride candidate</h2>
        <div class="grid">
            <div class="kv"><div class="label">Status</div><div class="value"><?= prw_h($latest['readiness_status'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Pickup</div><div class="value"><?= prw_h($latest['pickup_datetime'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Driver</div><div class="value"><?= prw_h($latest['driver_name'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Vehicle</div><div class="value"><?= prw_h($latest['vehicle_plate'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Pickup address</div><div class="value"><?= prw_h($latest['pickup_address'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Drop-off</div><div class="value"><?= prw_h($latest['dropoff_address'] ?? '-') ?></div></div>
        </div>
    </div>

    <?php if ($packet): ?>
    <div class="card">
        <h2>One-shot readiness packet</h2>
        <div class="grid">
            <div class="kv"><div class="label">Packet ID</div><div class="value"><?= prw_h($packet['packet_id'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Minutes until pickup</div><div class="value"><?= prw_h($packet['minutes_until_pickup'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Lessor</div><div class="value"><?= prw_h($packet['lessor_id'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Driver ID</div><div class="value"><?= prw_h($packet['driver_id'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Vehicle ID</div><div class="value"><?= prw_h($packet['vehicle_id'] ?? '-') ?></div></div>
            <div class="kv"><div class="label">Payload hash</div><div class="value"><?= prw_h($packet['payload_hash_16'] ?? '-') ?></div></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Raw JSON</h2>
        <pre><?= prw_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
    </div>
</div>
</body>
</html>
