<?php
/**
 * gov.cabnet.app — Ops pre-ride transport rehearsal packet v3.2.29.
 * Read-only. No EDXEIX transport.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_transport_rehearsal_lib.php';

function prt_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function prt_badge($value, bool $good = false): string
{
    return '<span class="badge ' . ($good ? 'good' : 'bad') . '">' . prt_h($value) . '</span>';
}

$candidateId = isset($_GET['candidate_id']) ? max(0, (int)$_GET['candidate_id']) : 0;
$options = $candidateId > 0 ? ['candidate_id' => $candidateId] : ['latest_ready' => true];
try {
    $result = gov_prt_run($options);
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'classification' => ['code' => 'PRE_RIDE_TRANSPORT_REHEARSAL_ERROR', 'message' => $e->getMessage()],
        'transport_performed' => false,
        'ready_for_later_supervised_transport_patch' => false,
        'rehearsal_blockers' => [$e->getMessage()],
    ];
}

$ready = !empty($result['ready_for_later_supervised_transport_patch']);
$packet = is_array($result['operator_rehearsal_packet'] ?? null) ? $result['operator_rehearsal_packet'] : [];
$live = is_array($result['live_gate_summary'] ?? null) ? $result['live_gate_summary'] : [];
$payload = is_array($packet['payload_preview'] ?? null) ? $packet['payload_preview'] : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pre-Ride Transport Rehearsal</title>
<style>
:root{--ink:#07142f;--muted:#5b6680;--line:#dfe5ef;--ok:#dcfce7;--bad:#fee2e2;--warn:#fff7ed;--brand:#07142f;--bg:#f6f8fb;}
body{margin:0;background:var(--bg);font-family:Arial,Helvetica,sans-serif;color:var(--ink);}main{max-width:1160px;margin:32px auto;padding:0 16px 48px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:20px;margin:16px 0;box-shadow:0 8px 24px rgba(7,20,47,.04)}.hero{border-color:<?= $ready ? '#86efac' : '#fca5a5' ?>;background:<?= $ready ? '#f0fdf4' : '#fff7f7' ?>}h1{margin:0 0 14px;font-size:28px}.muted{color:var(--muted)}.badge{display:inline-block;border-radius:999px;padding:6px 10px;font-weight:700;font-size:12px}.good{background:var(--ok);color:#166534}.bad{background:var(--bad);color:#991b1b}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.tile{border:1px solid var(--line);border-radius:12px;padding:14px;background:#fff}.label{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em}.value{margin-top:6px;font-weight:700;word-break:break-word}pre{white-space:pre-wrap;word-break:break-word;background:#0b1220;color:#e5e7eb;border-radius:12px;padding:14px;overflow:auto}.btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#e5e7eb;color:#111827;text-decoration:none;font-weight:700;margin:4px}.btn.primary{background:var(--brand);color:#fff}.danger{background:var(--bad);border:1px solid #fca5a5}.okbox{background:var(--ok);border:1px solid #86efac}.warn{background:var(--warn);border:1px solid #fdba74}@media(max-width:850px){.grid{grid-template-columns:1fr 1fr}}@media(max-width:520px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<main>
<section class="card hero">
<h1>Pre-Ride Transport Rehearsal Packet</h1>
<p class="muted">v3.2.29 — read-only rehearsal. No EDXEIX transport, no AADE call, no queue job, no live config write.</p>
<p><strong>Classification:</strong> <?= prt_badge($result['classification']['code'] ?? 'UNKNOWN', $ready) ?></p>
<p><?= prt_h($result['classification']['message'] ?? '') ?></p>
</section>

<section class="card">
<h2>Select source</h2>
<form method="get">
<label>Candidate ID <input type="number" name="candidate_id" value="<?= prt_h((string)$candidateId) ?>" min="0" style="padding:9px;border:1px solid var(--line);border-radius:8px;width:120px"></label>
<button class="btn primary" type="submit">Load candidate</button>
<a class="btn" href="pre-ride-transport-rehearsal.php">Latest captured ready</a>
<a class="btn" href="pre-ride-readiness-watch.php?auto_refresh=30">Readiness watch</a>
</form>
</section>

<section class="card">
<h2>Safety status</h2>
<div class="grid">
<div class="tile"><div class="label">Ready for later transport patch</div><div class="value"><?= prt_badge($ready ? 'YES' : 'NO', $ready) ?></div></div>
<div class="tile"><div class="label">Transport performed</div><div class="value"><?= prt_badge(!empty($result['transport_performed']) ? 'YES' : 'NO', empty($result['transport_performed'])) ?></div></div>
<div class="tile"><div class="label">Live submit enabled</div><div class="value"><?= prt_badge(!empty($live['live_submit_enabled']) ? 'YES' : 'NO', empty($live['live_submit_enabled'])) ?></div></div>
<div class="tile"><div class="label">HTTP submit enabled</div><div class="value"><?= prt_badge(!empty($live['http_submit_enabled']) ? 'YES' : 'NO', empty($live['http_submit_enabled'])) ?></div></div>
<div class="tile"><div class="label">EDXEIX session ready</div><div class="value"><?= prt_badge(!empty($live['session_ready']) ? 'YES' : 'NO', !empty($live['session_ready'])) ?></div></div>
<div class="tile"><div class="label">Submit URL configured</div><div class="value"><?= prt_badge(!empty($live['submit_url_configured']) ? 'YES' : 'NO', !empty($live['submit_url_configured'])) ?></div></div>
<div class="tile"><div class="label">Config written</div><div class="value"><?= prt_badge(!empty($result['config_written']) ? 'YES' : 'NO', empty($result['config_written'])) ?></div></div>
<div class="tile"><div class="label">Duplicate success</div><div class="value"><?= prt_badge(!empty($result['readiness_packet']['duplicate_check']['duplicate_success_detected']) ? 'YES' : 'NO', empty($result['readiness_packet']['duplicate_check']['duplicate_success_detected'])) ?></div></div>
</div>
</section>

<?php if (!empty($result['rehearsal_blockers'])): ?>
<section class="card danger"><h2>Rehearsal blockers</h2><ul><?php foreach ($result['rehearsal_blockers'] as $b): ?><li><?= prt_h($b) ?></li><?php endforeach; ?></ul></section>
<?php endif; ?>

<?php if ($packet): ?>
<section class="card <?= $ready ? 'okbox' : '' ?>">
<h2>Operator rehearsal packet</h2>
<div class="grid">
<?php foreach (['rehearsal_id','candidate_id','pickup_datetime','future_guard_expires_at','minutes_until_pickup','lessor_id','driver_id','vehicle_id','starting_point_id','vehicle_plate','price_amount','price_currency'] as $key): ?>
<div class="tile"><div class="label"><?= prt_h(str_replace('_',' ',$key)) ?></div><div class="value"><?= prt_h((string)($packet[$key] ?? '')) ?></div></div>
<?php endforeach; ?>
</div>
</section>
<section class="card"><h2>Payload preview</h2><pre><?= prt_h(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></section>
<section class="card"><h2>Operator checklist</h2><ol><?php foreach (($packet['operator_checklist'] ?? []) as $item): ?><li><?= prt_h($item) ?></li><?php endforeach; ?></ol></section>
<?php endif; ?>

<section class="card warn">
<h2>Next patch approval phrase</h2>
<p>This page cannot submit. To move to a real one-shot transport trace, use this exact approval phrase:</p>
<pre><?= prt_h((string)($result['approval_phrase_for_next_patch'] ?? '')) ?></pre>
</section>

<section class="card"><h2>Full JSON</h2><pre><?= prt_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></section>
</main>
</body>
</html>
