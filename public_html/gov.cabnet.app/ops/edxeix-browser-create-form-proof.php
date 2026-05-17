<?php
/**
 * gov.cabnet.app — Ops browser create-form proof validator v3.2.34.
 * Paste sanitized proof JSON copied from the logged-in EDXEIX browser page.
 * No EDXEIX request, no POST to EDXEIX, no DB write.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/edxeix_browser_form_proof_lib.php';

function bfp_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function bfp_badge(bool $ok): string { return '<span class="pill ' . ($ok ? 'ok' : 'bad') . '">' . ($ok ? 'YES' : 'NO') . '</span>'; }
function bfp_list($items): string {
    if (!is_array($items) || !$items) { return '<p class="muted">None.</p>'; }
    $html = '<ul>';
    foreach ($items as $item) { $html .= '<li>' . bfp_h($item) . '</li>'; }
    return $html . '</ul>';
}

$proofJson = trim((string)($_POST['proof_json'] ?? ''));
$result = null;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try { $result = gov_bfp_validate_json($proofJson); }
    catch (Throwable $e) { $error = $e->getMessage(); }
}
$ready = is_array($result) && !empty($result['ready_for_browser_assisted_next_step']);
$summary = is_array($result['proof_summary'] ?? null) ? $result['proof_summary'] : [];
$snippetPath = '/home/cabnet/docs/EDXEIX_BROWSER_CREATE_FORM_PROOF_SNIPPET_v3.2.34.js';
$snippet = is_readable($snippetPath) ? (string)file_get_contents($snippetPath) : '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>EDXEIX Browser Create-Form Proof</title>
<style>
:root{--ink:#071225;--muted:#60708a;--line:#d9e2ef;--danger:#b91c1c;--dangerBg:#fff1f2;--ok:#047857;--okBg:#dcfce7;--warn:#92400e;--warnBg:#fffbeb}*{box-sizing:border-box}body{margin:0;background:#f3f6fb;color:var(--ink);font-family:Arial,Helvetica,sans-serif;line-height:1.45}.wrap{max-width:1180px;margin:48px auto;padding:0 16px}.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:18px;margin:16px 0;box-shadow:0 8px 24px rgba(15,23,42,.04)}.hero{border-color:#fecaca;background:var(--dangerBg)}.hero.ok{border-color:#bbf7d0;background:#f0fdf4}.hero.warn{border-color:#fde68a;background:#fffbeb}h1{margin:0 0 12px;font-size:28px}h2{margin:0 0 12px;font-size:20px}.muted{color:var(--muted)}.pill{display:inline-block;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:700}.pill.ok{background:var(--okBg);color:var(--ok)}.pill.bad{background:#fee2e2;color:var(--danger)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.kv{border:1px solid var(--line);border-radius:12px;padding:12px;background:#fff}.kv .k{font-size:12px;text-transform:uppercase;color:var(--muted);letter-spacing:.04em}.kv .v{margin-top:6px;font-weight:700;word-break:break-word}.btn{display:inline-block;border:0;border-radius:9px;background:#0f172a;color:#fff;padding:10px 14px;font-weight:700;text-decoration:none;cursor:pointer}.btn.secondary{background:#e5e7eb;color:#111827}.code,textarea{white-space:pre-wrap;background:#0f172a;color:#e5e7eb;border-radius:12px;padding:14px;overflow:auto;font-family:Consolas,monospace;font-size:13px;width:100%;min-height:220px}.paste{background:#fff;color:#111827;border:1px solid var(--line)}@media(max-width:900px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.wrap{margin:20px auto}}@media(max-width:560px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <section class="card hero <?= $ready ? 'ok' : 'warn' ?>">
    <h1>EDXEIX Browser Create-Form Proof</h1>
    <p class="muted">v3.2.34 — paste sanitized proof from the logged-in EDXEIX browser create page. No EDXEIX POST, no AADE call, no queue job, no DB write, no V0 production change.</p>
    <?php if (is_array($result)): ?>
      <p><strong>Classification:</strong> <span class="pill <?= $ready ? 'ok' : 'bad' ?>"><?= bfp_h($result['classification']['code'] ?? 'UNKNOWN') ?></span></p>
      <p><?= bfp_h($result['classification']['message'] ?? '') ?></p>
    <?php elseif ($error !== ''): ?>
      <p><strong>Error:</strong> <span class="pill bad"><?= bfp_h($error) ?></span></p>
    <?php else: ?>
      <p><strong>Status:</strong> waiting for pasted sanitized browser proof.</p>
    <?php endif; ?>
    <p><a class="btn secondary" href="/ops/edxeix-create-form-token-diagnostic.php">Server token diagnostic</a> <a class="btn secondary" href="/ops/pre-ride-one-shot-transport-trace.php">Transport trace page</a></p>
  </section>

  <section class="card">
    <h2>Step 1 — run this in the logged-in EDXEIX create page</h2>
    <p>Open <strong>https://edxeix.yme.gov.gr/dashboard/lease-agreement/create</strong> in the browser where you are already logged in. Open DevTools Console, paste this snippet, press Enter, then paste the copied JSON below.</p>
    <pre class="code"><?= bfp_h($snippet) ?></pre>
  </section>

  <section class="card">
    <h2>Step 2 — paste sanitized proof JSON</h2>
    <form method="post">
      <textarea class="paste" name="proof_json" placeholder="Paste sanitized proof JSON here. Do not paste cookies, raw tokens, raw HTML, or screenshots."><?= bfp_h($proofJson) ?></textarea>
      <p><button class="btn" type="submit">Validate browser proof</button></p>
    </form>
  </section>

  <?php if (is_array($result)): ?>
  <section class="card">
    <h2>Proof status</h2>
    <div class="grid">
      <div class="kv"><div class="k">Ready for next browser-assisted step</div><div class="v"><?= bfp_badge($ready) ?></div></div>
      <div class="kv"><div class="k">Form present</div><div class="v"><?= bfp_badge(!empty($summary['form_present'])) ?></div></div>
      <div class="kv"><div class="k">Token present</div><div class="v"><?= bfp_badge(!empty($summary['token_present'])) ?></div></div>
      <div class="kv"><div class="k">Token hash 16</div><div class="v"><?= bfp_h($summary['token_hash_16'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Page host</div><div class="v"><?= bfp_h($summary['page_host'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Page path</div><div class="v"><?= bfp_h($summary['page_path'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Form method</div><div class="v"><?= bfp_h($summary['form_method'] ?? '') ?></div></div>
      <div class="kv"><div class="k">Form action</div><div class="v"><?= bfp_h($summary['form_action_safe'] ?? '') ?></div></div>
    </div>
  </section>
  <?php if (!empty($result['blockers'])): ?><section class="card hero"><h2>Blockers</h2><?= bfp_list($result['blockers']) ?></section><?php endif; ?>
  <?php if (!empty($result['warnings'])): ?><section class="card hero warn"><h2>Warnings</h2><?= bfp_list($result['warnings']) ?></section><?php endif; ?>
  <section class="card"><h2>Expected fields</h2><h3>Present</h3><?= bfp_list($summary['expected_fields_present'] ?? []) ?><h3>Missing</h3><?= bfp_list($summary['expected_fields_missing'] ?? []) ?></section>
  <section class="card"><h2>Sanitized validation JSON</h2><pre class="code"><?= bfp_h(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></section>
  <?php endif; ?>
</div>
</body>
</html>
