<?php
/**
 * gov.cabnet.app — Ops EDXEIX create-form token diagnostic v3.2.33.
 * Read-only GET diagnostic. No EDXEIX POST.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_pre_ride_one_shot_transport_trace_lib.php';

function edxf_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function edxf_badge(bool $ok): string
{
    return '<span class="pill ' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'YES' : 'NO') . '</span>';
}
function edxf_list($items): string
{
    if (!is_array($items) || !$items) { return '<p class="muted">None.</p>'; }
    $html = '<ul>';
    foreach ($items as $item) { $html .= '<li>' . edxf_h($item) . '</li>'; }
    return $html . '</ul>';
}

try {
    $diag = gov_prtx_form_token_diagnostic();
    $ok = !empty($diag['ok']);
    $result = [
        'ok' => true,
        'version' => 'v3.2.33-edxeix-create-form-token-diagnostic',
        'classification' => [
            'code' => $ok ? 'EDXEIX_CREATE_FORM_TOKEN_READY' : 'EDXEIX_CREATE_FORM_TOKEN_NOT_READY',
            'message' => $ok
                ? 'EDXEIX create form was fetched and a hidden form token was detected. No POST was performed.'
                : 'EDXEIX create form token diagnostic is not ready. Review warnings/status/redirects. No POST was performed.',
        ],
        'safety' => [
            'edxeix_post' => false,
            'aade_call' => false,
            'queue_job' => false,
            'normalized_booking_write' => false,
            'live_config_write' => false,
            'raw_cookie_printed' => false,
            'raw_csrf_printed' => false,
            'raw_body_printed' => false,
        ],
        'diagnostic' => $diag,
    ];
} catch (Throwable $e) {
    $result = [
        'ok' => false,
        'version' => 'v3.2.33-edxeix-create-form-token-diagnostic',
        'classification' => [
            'code' => 'EDXEIX_CREATE_FORM_TOKEN_DIAGNOSTIC_ERROR',
            'message' => $e->getMessage(),
        ],
        'diagnostic' => [],
    ];
    $diag = [];
    $ok = false;
}
$summary = is_array($diag['form_summary'] ?? null) ? $diag['form_summary'] : [];
$steps = is_array($diag['steps'] ?? null) ? $diag['steps'] : [];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EDXEIX Create Form Token Diagnostic</title>
<style>
:root{--ink:#071225;--muted:#60708a;--line:#d9e2ef;--danger:#b91c1c;--dangerBg:#fff1f2;--ok:#047857;--okBg:#dcfce7;--warn:#92400e;--warnBg:#fffbeb}*{box-sizing:border-box}body{margin:0;background:#f3f6fb;color:var(--ink);font-family:Arial,Helvetica,sans-serif;line-height:1.45}.wrap{max-width:1180px;margin:48px auto;padding:0 16px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;margin:16px 0;box-shadow:0 8px 24px rgba(15,23,42,.04)}.hero{border-color:#fecaca;background:var(--dangerBg)}.hero.ok{border-color:#bbf7d0;background:#f0fdf4}h1{margin:0 0 12px;font-size:28px}h2{margin:0 0 12px;font-size:20px}.muted{color:var(--muted)}.pill{display:inline-block;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700}.pill.ok{background:var(--okBg);color:var(--ok)}.pill.bad{background:#fee2e2;color:var(--danger)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.kv{border:1px solid var(--line);border-radius:12px;padding:12px;background:#fff}.kv .k{font-size:12px;text-transform:uppercase;color:var(--muted);letter-spacing:.04em}.kv .v{margin-top:6px;font-weight:700;word-break:break-word}.btn{display:inline-block;border:0;border-radius:9px;background:#0f172a;color:#fff;padding:10px 14px;font-weight:700;text-decoration:none}.btn.secondary{background:#e5e7eb;color:#111827}.code{white-space:pre-wrap;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:14px;overflow:auto;font-family:Consolas,monospace;font-size:13px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);text-align:left;padding:8px;vertical-align:top}@media(max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.wrap{margin:20px auto}}@media(max-width:560px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <section class="card hero <?= $ok ? 'ok' : '' ?>">
    <h1>EDXEIX Create Form Token Diagnostic</h1>
    <p class="muted">v3.2.34 — read-only GET of the EDXEIX create form. No EDXEIX POST, no AADE call, no queue job, no normalized booking write, no live config write.</p>
    <p><strong>Classification:</strong> <span class="pill <?= $ok ? 'ok' : 'bad' ?>"><?= edxf_h($result['classification']['code'] ?? 'UNKNOWN') ?></span></p>
    <p><?= edxf_h($result['classification']['message'] ?? '') ?></p>
    <p><a class="btn" href="/ops/edxeix-create-form-token-diagnostic.php">Refresh diagnostic</a> <a class="btn secondary" href="/ops/edxeix-browser-create-form-proof.php">Browser form proof</a> <a class="btn secondary" href="/ops/pre-ride-one-shot-transport-trace.php">Transport trace page</a></p>
  </section>

  <section class="card">
    <h2>Token and form status</h2>
    <div class="grid">
      <div class="kv"><div class="k">Performed</div><div class="v"><?= edxf_badge(!empty($diag['performed'])) ?></div></div>
      <div class="kv"><div class="k">HTTP status</div><div class="v"><?= edxf_h($diag['status'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Token present</div><div class="v"><?= edxf_badge(!empty($diag['token_present'])) ?></div></div>
      <div class="kv"><div class="k">Form present</div><div class="v"><?= edxf_badge(!empty($summary['form_present'])) ?></div></div>
      <div class="kv"><div class="k">Token hash 16</div><div class="v"><?= edxf_h($diag['token_hash_16'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Session CSRF hash 16</div><div class="v"><?= edxf_h($diag['session_csrf_hash_16'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Token matches session CSRF</div><div class="v"><?= edxf_badge(!empty($diag['token_matches_session_csrf'])) ?></div></div>
      <div class="kv"><div class="k">Redirect count</div><div class="v"><?= edxf_h($diag['redirect_count'] ?? '') ?></div></div>
    </div>
  </section>

  <section class="card">
    <h2>URLs</h2>
    <div class="grid">
      <div class="kv"><div class="k">Submit URL</div><div class="v"><?= edxf_h($diag['url'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Create URL</div><div class="v"><?= edxf_h($diag['create_url'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Final URL</div><div class="v"><?= edxf_h($diag['final_url'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Form action</div><div class="v"><?= edxf_h($summary['form_action_safe'] ?? '') ?></div></div>
    </div>
  </section>

  <?php if (!empty($diag['warnings'])): ?>
  <section class="card hero">
    <h2>Warnings</h2>
    <?= edxf_list($diag['warnings']) ?>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Form fields summary</h2>
    <div class="grid">
      <div class="kv"><div class="k">Form method</div><div class="v"><?= edxf_h($summary['form_method'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Input count</div><div class="v"><?= edxf_h($summary['input_count'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Select count</div><div class="v"><?= edxf_h($summary['select_count'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Textarea count</div><div class="v"><?= edxf_h($summary['textarea_count'] ?? '') ?></div></div>
    </div>
    <h3>Expected fields missing</h3>
    <?= edxf_list($summary['required_expected_fields_missing'] ?? []) ?>
  </section>

  <section class="card">
    <h2>Redirect steps</h2>
    <table class="table">
      <thead><tr><th>#</th><th>Status</th><th>URL</th><th>Location</th><th>Title / Signals</th></tr></thead>
      <tbody>
      <?php foreach ($steps as $idx => $step): $fp = is_array($step['body_fingerprint'] ?? null) ? $step['body_fingerprint'] : []; ?>
        <tr>
          <td><?= (int)$idx + 1 ?></td>
          <td><?= edxf_h($step['status'] ?? '') ?></td>
          <td><?= edxf_h($step['url'] ?? '') ?></td>
          <td><?= edxf_h($step['location'] ?? '') ?></td>
          <td><?= edxf_h($fp['title'] ?? '') ?><br><span class="muted"><?= edxf_h(json_encode($fp['signals'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h2>Full sanitized JSON</h2>
    <pre class="code"><?= edxf_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre>
  </section>
</div>
</body>
</html>
