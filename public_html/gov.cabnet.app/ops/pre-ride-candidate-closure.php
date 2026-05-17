<?php
/**
 * gov.cabnet.app — Ops mark pre-ride candidate manually submitted via V0/laptop.
 * v3.2.32
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_candidate_closure_lib.php';
require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_readiness_lib.php';

function prcl_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function prcl_badge(bool $ok): string { return '<span class="pill ' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'YES' : 'NO') . '</span>'; }

$candidateId = (int)($_REQUEST['candidate_id'] ?? 0);
$result = null;
$packet = null;
$closure = null;

try {
    $db = gov_bridge_db();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['mark_manual'])) {
        $confirm = trim((string)($_POST['confirmation_phrase'] ?? ''));
        if ($confirm !== 'I CONFIRM THIS CANDIDATE WAS MANUALLY SUBMITTED VIA V0') {
            throw new RuntimeException('Confirmation phrase mismatch. No closure was written.');
        }
        $result = gov_prcl_mark_manual($db, $candidateId, [
            'method' => (string)($_POST['method'] ?? 'v0_laptop_manual'),
            'submitted_by' => (string)($_POST['submitted_by'] ?? 'operator'),
            'submitted_at' => (string)($_POST['submitted_at'] ?? gov_prcl_now()),
            'note' => (string)($_POST['note'] ?? 'Manually submitted via V0/laptop. Server-side retry blocked.'),
        ]);
    }
    if ($candidateId > 0) {
        $packet = gov_pror_run(['candidate_id' => $candidateId]);
        $candidate = is_array($packet['candidate'] ?? null) ? $packet['candidate'] : [];
        $payload = is_array($packet['operator_packet']['payload_preview'] ?? null) ? $packet['operator_packet']['payload_preview'] : [];
        $closure = gov_prcl_closure_state($db, $candidate, $payload);
    }
} catch (Throwable $e) {
    $result = ['ok' => false, 'message' => $e->getMessage()];
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pre-Ride Candidate Closure</title>
<style>
:root{--ink:#071225;--muted:#60708a;--line:#d9e2ef;--soft:#f6f8fc;--danger:#b91c1c;--dangerBg:#fff1f2;--ok:#047857;--okBg:#dcfce7;--warn:#92400e;--warnBg:#fffbeb}*{box-sizing:border-box}body{margin:0;background:#f3f6fb;color:var(--ink);font-family:Arial,Helvetica,sans-serif;line-height:1.45}.wrap{max-width:1100px;margin:42px auto;padding:0 16px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;margin:16px 0;box-shadow:0 8px 24px rgba(15,23,42,.04)}.hero{background:var(--warnBg);border-color:#fde68a}.hero.ok{background:var(--okBg);border-color:#bbf7d0}.hero.bad{background:var(--dangerBg);border-color:#fecaca}h1{margin:0 0 12px;font-size:28px}h2{margin:0 0 12px;font-size:20px}.muted{color:var(--muted)}.pill{display:inline-block;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700}.pill.ok{background:var(--okBg);color:var(--ok)}.pill.bad{background:#fee2e2;color:var(--danger)}.grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.kv{border:1px solid var(--line);border-radius:12px;padding:12px;background:#fff}.kv .k{font-size:12px;text-transform:uppercase;color:var(--muted);letter-spacing:.04em}.kv .v{margin-top:6px;font-weight:700;word-break:break-word}input,textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font:inherit}button,.btn{display:inline-block;border:0;border-radius:9px;background:#0f172a;color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}.btn.secondary{background:#e5e7eb;color:#111827}.btn.danger{background:#b91c1c}.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:end}.code{white-space:pre-wrap;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:14px;overflow:auto;font-family:Consolas,monospace;font-size:13px}.notice{padding:12px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#7c2d12}@media(max-width:800px){.grid{grid-template-columns:1fr}.wrap{margin:20px auto}}
</style>
</head>
<body>
<div class="wrap">
<section class="card hero">
  <h1>Pre-Ride Candidate Closure / V0 Manual Submission Mark</h1>
  <p class="muted">v3.2.32 — marks a captured candidate as manually submitted through V0/laptop and blocks future server-side retry. No EDXEIX HTTP request is performed.</p>
</section>

<?php if (is_array($result)): ?>
<section class="card <?= !empty($result['ok']) ? 'hero ok' : 'hero bad' ?>">
  <h2>Result</h2>
  <p><strong><?= !empty($result['ok']) ? 'OK' : 'Blocked/Error' ?></strong> — <?= prcl_h($result['message'] ?? '') ?></p>
  <pre class="code"><?= prcl_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
</section>
<?php endif; ?>

<section class="card">
  <h2>Select candidate</h2>
  <form method="get" class="actions">
    <label>Candidate ID<br><input type="number" name="candidate_id" min="1" value="<?= prcl_h($candidateId ?: '') ?>"></label>
    <button type="submit">Load candidate</button>
    <a class="btn secondary" href="/ops/pre-ride-readiness-watch.php?auto_refresh=30">Readiness watch</a>
    <?php if ($candidateId > 0): ?><a class="btn secondary" href="/ops/pre-ride-one-shot-transport-trace.php?candidate_id=<?= prcl_h($candidateId) ?>">Transport trace diagnostic</a><?php endif; ?>
  </form>
</section>

<?php if (is_array($packet)): $op = is_array($packet['operator_packet'] ?? null) ? $packet['operator_packet'] : []; ?>
<section class="card">
  <h2>Candidate packet</h2>
  <div class="grid">
    <div class="kv"><div class="k">Ready for one-shot</div><div class="v"><?= prcl_badge(!empty($packet['ready_for_supervised_one_shot'])) ?></div></div>
    <div class="kv"><div class="k">Candidate ID</div><div class="v"><?= prcl_h($op['candidate_id'] ?? $candidateId) ?></div></div>
    <div class="kv"><div class="k">Payload hash</div><div class="v"><?= prcl_h($op['payload_hash'] ?? '') ?></div></div>
    <div class="kv"><div class="k">Pickup</div><div class="v"><?= prcl_h($op['pickup_datetime'] ?? '') ?></div></div>
    <div class="kv"><div class="k">Driver</div><div class="v"><?= prcl_h(($op['driver_id'] ?? '') . ' / ' . ($op['driver_name'] ?? '')) ?></div></div>
    <div class="kv"><div class="k">Vehicle</div><div class="v"><?= prcl_h(($op['vehicle_id'] ?? '') . ' / ' . ($op['vehicle_plate'] ?? '')) ?></div></div>
  </div>
</section>

<section class="card <?= !empty($closure['closed']) ? 'hero ok' : '' ?>">
  <h2>Closure state</h2>
  <pre class="code"><?= prcl_h(json_encode($closure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
</section>

<section class="card hero">
  <h2>Mark manually submitted via V0/laptop</h2>
  <div class="notice">Use this only after the contract was submitted through the existing V0 laptop/browser workflow. This prevents server retry/duplicate attempts for this candidate/source/payload.</div>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="candidate_id" value="<?= prcl_h($candidateId) ?>">
    <label>Method<br><input name="method" value="v0_laptop_manual"></label><br><br>
    <label>Submitted by<br><input name="submitted_by" value="Andreas"></label><br><br>
    <label>Submitted at<br><input name="submitted_at" value="<?= prcl_h(gov_prcl_now()) ?>"></label><br><small class="muted">May be left blank; v3.2.32 will default to current server time.</small><br><br>
    <label>Note<br><textarea name="note" rows="3">Manually submitted via V0 laptop/browser. Server-side retry blocked.</textarea></label><br><br>
    <label>Type exact confirmation phrase<br><textarea name="confirmation_phrase" rows="2" placeholder="I CONFIRM THIS CANDIDATE WAS MANUALLY SUBMITTED VIA V0"></textarea></label>
    <p class="muted">Required phrase: <code>I CONFIRM THIS CANDIDATE WAS MANUALLY SUBMITTED VIA V0</code></p>
    <button class="btn danger" name="mark_manual" value="1" type="submit">Mark manual V0 submission and block retry</button>
  </form>
</section>
<?php endif; ?>
</div>
</body>
</html>
