<?php
/**
 * gov.cabnet.app — Pre-Ride One-Shot Readiness Packet v3.2.27
 *
 * Safety: read-only readiness page. No EDXEIX transport, no AADE call, no queue job.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

$auth = __DIR__ . '/_auth.php';
if (is_file($auth)) { require_once $auth; }

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php';

function pror_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function pror_bool_badge(bool $value): string
{
    return $value ? '<span class="badge good">YES</span>' : '<span class="badge bad">NO</span>';
}

$candidateId = isset($_GET['candidate_id']) ? max(0, (int)$_GET['candidate_id']) : 0;
$latestMail = gov_pror_bool($_GET['latest_mail'] ?? '0');
$latestReady = gov_pror_bool($_GET['latest_ready'] ?? ($candidateId > 0 || $latestMail ? '0' : '1'));

try {
    $result = gov_pror_run([
        'candidate_id' => $candidateId,
        'latest_ready' => $latestReady,
        'latest_mail' => $latestMail,
    ]);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'classification' => [
            'code' => 'PRE_RIDE_ONE_SHOT_READINESS_EXCEPTION',
            'message' => $e->getMessage(),
        ],
        'transport_performed' => false,
    ];
}

$class = is_array($result['classification'] ?? null) ? $result['classification'] : [];
$packet = is_array($result['operator_packet'] ?? null) ? $result['operator_packet'] : [];
$candidate = is_array($result['candidate'] ?? null) ? $result['candidate'] : [];
$blockers = is_array($result['readiness_blockers'] ?? null) ? $result['readiness_blockers'] : [];
$live = is_array($result['live_gate_summary'] ?? null) ? $result['live_gate_summary'] : [];
$duplicate = is_array($result['duplicate_check'] ?? null) ? $result['duplicate_check'] : [];
$payload = is_array($packet['payload_preview'] ?? null) ? $packet['payload_preview'] : [];
$code = (string)($class['code'] ?? 'UNKNOWN');
$ready = !empty($result['ready_for_supervised_one_shot']);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pre-Ride One-Shot Readiness — gov.cabnet.app</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f6f7fb;color:#111827;margin:0;padding:24px}.wrap{max-width:1180px;margin:0 auto}.card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:18px;margin:0 0 16px;box-shadow:0 8px 20px rgba(15,23,42,.05)}h1{font-size:24px;margin:0 0 8px}h2{font-size:18px;margin:0 0 12px}.muted{color:#6b7280}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.metric{border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fafafa}.metric span{display:block;color:#6b7280;font-size:12px;text-transform:uppercase;letter-spacing:.04em}.metric strong{font-size:16px;word-break:break-word}.badge{display:inline-block;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700}.good{background:#dcfce7;color:#166534}.bad{background:#fee2e2;color:#991b1b}.warn{background:#fef3c7;color:#92400e}.info{background:#dbeafe;color:#1d4ed8}pre{white-space:pre-wrap;word-break:break-word;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:14px;overflow:auto}.btn{display:inline-block;border:0;border-radius:10px;background:#111827;color:#fff;padding:10px 14px;text-decoration:none;cursor:pointer}.btn.alt{background:#e5e7eb;color:#111827}input[type=number]{padding:9px;border:1px solid #d1d5db;border-radius:10px;max-width:140px}.danger{border-color:#fecaca;background:#fff7f7}.success{border-color:#bbf7d0;background:#f0fdf4}.warning{border-color:#fde68a;background:#fffbeb}ul{margin-top:8px}.small{font-size:13px}</style>
</head>
<body>
<div class="wrap">
  <div class="card <?= $ready ? 'success' : ($blockers ? 'danger' : 'warning') ?>">
    <h1>Pre-Ride One-Shot Readiness Packet</h1>
    <p class="muted">v3.2.27 — Read-only packet. No EDXEIX transport, no AADE call, no queue job.</p>
    <p><strong>Classification:</strong> <span class="badge <?= $ready ? 'good' : 'bad' ?>"><?= pror_h($code) ?></span></p>
    <p><?= pror_h((string)($class['message'] ?? '')) ?></p>
  </div>

  <div class="card">
    <h2>Select source</h2>
    <form method="get">
      <label>Candidate ID <input type="number" name="candidate_id" value="<?= pror_h((string)$candidateId) ?>" min="0"></label>
      <button class="btn" type="submit">Load candidate</button>
      <a class="btn alt" href="?latest_ready=1">Latest captured ready</a>
      <a class="btn alt" href="?latest_mail=1">Latest Maildir dry-run</a>
    </form>
  </div>

  <div class="card">
    <h2>Safety status</h2>
    <div class="grid">
      <div class="metric"><span>Ready for supervised one-shot</span><strong><?= pror_bool_badge($ready) ?></strong></div>
      <div class="metric"><span>Transport performed</span><strong><?= pror_bool_badge(!empty($result['transport_performed'])) ?></strong></div>
      <div class="metric"><span>Live submit enabled</span><strong><?= pror_bool_badge(!empty($live['live_submit_enabled'])) ?></strong></div>
      <div class="metric"><span>HTTP submit enabled</span><strong><?= pror_bool_badge(!empty($live['http_submit_enabled'])) ?></strong></div>
      <div class="metric"><span>EDXEIX session ready</span><strong><?= pror_bool_badge(!empty($live['session_ready'])) ?></strong></div>
      <div class="metric"><span>Duplicate success detected</span><strong><?= pror_bool_badge(!empty($duplicate['duplicate_success_detected'])) ?></strong></div>
    </div>
  </div>

  <?php if ($blockers): ?>
  <div class="card danger">
    <h2>Readiness blockers</h2>
    <ul><?php foreach ($blockers as $blocker): ?><li><?= pror_h((string)$blocker) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <?php if ($packet): ?>
  <div class="card">
    <h2>Operator packet</h2>
    <div class="grid">
      <div class="metric"><span>Packet ID</span><strong><?= pror_h($packet['packet_id'] ?? '') ?></strong></div>
      <div class="metric"><span>Candidate ID</span><strong><?= pror_h($packet['candidate_id'] ?? '') ?></strong></div>
      <div class="metric"><span>Pickup</span><strong><?= pror_h($packet['pickup_datetime'] ?? '') ?></strong></div>
      <div class="metric"><span>Minutes until pickup</span><strong><?= pror_h($packet['minutes_until_pickup'] ?? '') ?></strong></div>
      <div class="metric"><span>Driver</span><strong><?= pror_h($packet['driver_name'] ?? '') ?></strong></div>
      <div class="metric"><span>Vehicle</span><strong><?= pror_h($packet['vehicle_plate'] ?? '') ?></strong></div>
      <div class="metric"><span>Lessor ID</span><strong><?= pror_h($packet['lessor_id'] ?? '') ?></strong></div>
      <div class="metric"><span>Driver ID</span><strong><?= pror_h($packet['driver_id'] ?? '') ?></strong></div>
      <div class="metric"><span>Vehicle ID</span><strong><?= pror_h($packet['vehicle_id'] ?? '') ?></strong></div>
      <div class="metric"><span>Payload hash</span><strong><?= pror_h($packet['payload_hash_16'] ?? '') ?></strong></div>
    </div>
  </div>

  <div class="card">
    <h2>Payload preview</h2>
    <pre><?= pror_h(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Next action</h2>
    <p><?= pror_h((string)($result['next_action'] ?? '')) ?></p>
    <p class="muted small">This page intentionally cannot submit to EDXEIX. The next patch must be explicitly approved before adding any supervised transport trace.</p>
  </div>
</div>
</body>
</html>
