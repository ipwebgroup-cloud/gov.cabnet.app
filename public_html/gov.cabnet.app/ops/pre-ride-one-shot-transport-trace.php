<?php
/**
 * gov.cabnet.app — Ops supervised pre-ride one-shot EDXEIX transport trace v3.2.31.
 * Default GET is dry-run. Actual HTTP POST requires typed confirmation and hash lock.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php';

function prtx_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prtx_bool_badge(bool $ok): string
{
    return '<span class="pill ' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'YES' : 'NO') . '</span>';
}

function prtx_array_lines($value): string
{
    if (!is_array($value) || !$value) {
        return '<p class="muted">None.</p>';
    }
    $html = '<ul>';
    foreach ($value as $item) {
        $html .= '<li>' . prtx_h($item) . '</li>';
    }
    return $html . '</ul>';
}

$candidateId = (int)($_REQUEST['candidate_id'] ?? 0);
$transportRequested = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['submit_transport']);
$options = [
    'candidate_id' => $candidateId,
    'transport' => $transportRequested,
    'follow_redirects' => true,
    'expected_payload_hash' => (string)($_POST['expected_payload_hash'] ?? ''),
    'confirmation_phrase' => (string)($_POST['confirmation_phrase'] ?? ''),
];

try {
    $result = gov_prtx_run($options);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'classification' => [
            'code' => 'PRE_RIDE_TRANSPORT_TRACE_ERROR',
            'message' => $e->getMessage(),
        ],
        'transport_performed' => false,
        'transport_blockers' => ['exception'],
    ];
}

$classCode = (string)($result['classification']['code'] ?? 'UNKNOWN');
$classMsg = (string)($result['classification']['message'] ?? '');
$packet = is_array($result['operator_transport_packet'] ?? null) ? $result['operator_transport_packet'] : [];
$payload = is_array($packet['payload_preview'] ?? null) ? $packet['payload_preview'] : [];
$trace = is_array($result['trace'] ?? null) ? $result['trace'] : null;
$armable = $classCode === 'PRE_RIDE_TRANSPORT_TRACE_ARMABLE' && empty($result['transport_blockers']);
$performed = !empty($result['transport_performed']);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pre-Ride One-Shot Transport Trace</title>
<style>
:root{--ink:#071225;--muted:#60708a;--line:#d9e2ef;--soft:#f6f8fc;--danger:#b91c1c;--dangerBg:#fff1f2;--ok:#047857;--okBg:#dcfce7;--warn:#92400e;--warnBg:#fffbeb;--blue:#1d4ed8;--blueBg:#eff6ff;}
*{box-sizing:border-box}body{margin:0;background:#f3f6fb;color:var(--ink);font-family:Arial,Helvetica,sans-serif;line-height:1.45}.wrap{max-width:1180px;margin:48px auto;padding:0 16px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;margin:16px 0;box-shadow:0 8px 24px rgba(15,23,42,.04)}.hero{border-color:#fecaca;background:var(--dangerBg)}.hero.ok{border-color:#bbf7d0;background:#f0fdf4}.hero.warn{border-color:#fde68a;background:var(--warnBg)}h1{margin:0 0 12px;font-size:28px}h2{margin:0 0 12px;font-size:20px}.muted{color:var(--muted)}.pill{display:inline-block;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700}.pill.ok{background:var(--okBg);color:var(--ok)}.pill.bad{background:#fee2e2;color:var(--danger)}.pill.warn{background:var(--warnBg);color:var(--warn)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.kv{border:1px solid var(--line);border-radius:12px;padding:12px;background:#fff}.kv .k{font-size:12px;text-transform:uppercase;color:var(--muted);letter-spacing:.04em}.kv .v{margin-top:6px;font-weight:700;word-break:break-word}input,textarea{width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font:inherit}button,.btn{display:inline-block;border:0;border-radius:9px;background:#0f172a;color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}.btn.secondary{background:#e5e7eb;color:#111827}.btn.danger{background:#b91c1c}.btn.disabled,button:disabled{opacity:.45;cursor:not-allowed}.actions{display:flex;gap:8px;flex-wrap:wrap;align-items:end}.code{white-space:pre-wrap;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:14px;overflow:auto;font-family:Consolas,monospace;font-size:13px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);text-align:left;padding:8px;vertical-align:top}.notice{padding:12px;border-radius:12px;background:#fff7ed;border:1px solid #fed7aa;color:#7c2d12}@media(max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.wrap{margin:20px auto}}@media(max-width:560px){.grid{grid-template-columns:1fr}.actions{display:block}.actions>*{margin:5px 0}}
</style>
</head>
<body>
<div class="wrap">
  <section class="card hero <?= $performed ? 'warn' : ($armable ? 'ok' : '') ?>">
    <h1>Pre-Ride One-Shot EDXEIX Transport Trace</h1>
    <p class="muted">v3.2.32 — closure/retry-prevention and EDXEIX form-token diagnostic. Default is dry-run. No AADE call, no queue job, no normalized booking write, no live config write. No repeat POST while held.</p>
    <p><strong>Classification:</strong> <span class="pill <?= $performed ? 'warn' : ($armable ? 'ok' : 'bad') ?>"><?= prtx_h($classCode) ?></span></p>
    <p><?= prtx_h($classMsg) ?></p>
  </section>

  <section class="card">
    <h2>Select source</h2>
    <form method="get" class="actions">
      <label>Candidate ID<br><input type="number" name="candidate_id" min="1" value="<?= prtx_h($candidateId ?: ($packet['candidate_id'] ?? '')) ?>"></label>
      <button type="submit">Load dry-run packet</button>
      <a class="btn secondary" href="/ops/pre-ride-transport-rehearsal.php?candidate_id=<?= prtx_h($candidateId ?: ($packet['candidate_id'] ?? '')) ?>">Rehearsal</a>
      <a class="btn secondary" href="/ops/pre-ride-readiness-watch.php?auto_refresh=30">Readiness watch</a>
      <a class="btn secondary" href="/ops/pre-ride-candidate-closure.php?candidate_id=<?= prtx_h($candidateId ?: ($packet['candidate_id'] ?? '')) ?>">Closure / V0 manual mark</a>
    </form>
  </section>

  <section class="card">
    <h2>Safety status</h2>
    <div class="grid">
      <div class="kv"><div class="k">Armable</div><div class="v"><?= prtx_bool_badge($armable) ?></div></div>
      <div class="kv"><div class="k">Transport requested</div><div class="v"><?= prtx_bool_badge(!empty($result['transport_requested'])) ?></div></div>
      <div class="kv"><div class="k">Transport performed</div><div class="v"><?= prtx_bool_badge($performed) ?></div></div>
      <div class="kv"><div class="k">Config written</div><div class="v"><?= prtx_bool_badge(!empty($result['config_written'])) ?></div></div>
      <div class="kv"><div class="k">Live submit enabled</div><div class="v"><?= prtx_bool_badge(!empty($result['live_gate_summary']['live_submit_enabled'])) ?></div></div>
      <div class="kv"><div class="k">HTTP submit enabled</div><div class="v"><?= prtx_bool_badge(!empty($result['live_gate_summary']['http_submit_enabled'])) ?></div></div>
      <div class="kv"><div class="k">EDXEIX session ready</div><div class="v"><?= prtx_bool_badge(!empty($result['live_gate_summary']['session_ready'])) ?></div></div>
      <div class="kv"><div class="k">Minutes until pickup</div><div class="v"><?= prtx_h($packet['minutes_until_pickup'] ?? '') ?></div></div>
    </div>
  </section>

  <?php if (!empty($result['transport_blockers'])): ?>
  <section class="card hero">
    <h2>Transport blockers</h2>
    <?= prtx_array_lines($result['transport_blockers']) ?>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Operator packet</h2>
    <div class="grid">
      <div class="kv"><div class="k">Transport ID</div><div class="v"><?= prtx_h($packet['transport_id'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Candidate ID</div><div class="v"><?= prtx_h($packet['candidate_id'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Payload hash</div><div class="v"><?= prtx_h($packet['payload_hash'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Pickup</div><div class="v"><?= prtx_h($packet['pickup_datetime'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Lessor</div><div class="v"><?= prtx_h($packet['lessor_id'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Driver</div><div class="v"><?= prtx_h(($packet['driver_id'] ?? '') . ' / ' . ($packet['driver_name'] ?? '')) ?></div></div>
      <div class="kv"><div class="k">Vehicle</div><div class="v"><?= prtx_h(($packet['vehicle_id'] ?? '') . ' / ' . ($packet['vehicle_plate'] ?? '')) ?></div></div>
      <div class="kv"><div class="k">Price</div><div class="v"><?= prtx_h(($packet['price_amount'] ?? '') . ' ' . ($packet['price_currency'] ?? '')) ?></div></div>
    </div>
    <h3>Payload preview</h3>
    <table class="table"><tbody>
      <?php foreach ($payload as $k => $v): ?>
      <tr><th><?= prtx_h($k) ?></th><td><?= prtx_h($v) ?></td></tr>
      <?php endforeach; ?>
    </tbody></table>
  </section>

  <section class="card <?= $armable && !$performed ? 'hero warn' : '' ?>">
    <h2>Supervised one-shot POST</h2>
    <div class="notice">
      v3.2.31 is a safety hold after the 419 session/CSRF test. Use this page for diagnostics only. Mark V0 manual submissions through the closure page, then wait for the next fresh-token transport patch before any new POST.
    </div>
    <form method="post" style="margin-top:12px">
      <input type="hidden" name="candidate_id" value="<?= prtx_h($packet['candidate_id'] ?? $candidateId) ?>">
      <input type="hidden" name="expected_payload_hash" value="<?= prtx_h($packet['payload_hash'] ?? '') ?>">
      <label>Type exact confirmation phrase:<br>
        <textarea name="confirmation_phrase" rows="2" placeholder="<?= prtx_h(gov_prtx_confirmation_phrase()) ?>"></textarea>
      </label>
      <p class="muted">Required phrase: <code><?= prtx_h(gov_prtx_confirmation_phrase()) ?></code></p>
      <button class="btn danger" type="submit" name="submit_transport" value="1" <?= $armable && !$performed ? '' : 'disabled' ?>>POST disabled in v3.2.31 safety hold</button>
    </form>
  </section>

  <?php if ($trace): ?>
  <section class="card hero warn">
    <h2>Transport trace result — verification required</h2>
    <p>One HTTP POST trace was performed. This is not proof of saved contract until confirmed in the EDXEIX portal/list.</p>
    <pre class="code"><?= prtx_h(json_encode($trace, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Full sanitized JSON</h2>
    <pre class="code"><?= prtx_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
  </section>
</div>
</body>
</html>
