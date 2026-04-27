<?php
/**
 * EDXEIX Session Refresh Checklist v3.0
 * Checklist-only: no Bolt call, no EDXEIX call, no POST, no DB/file writes,
 * no staging, no mapping update, no secret output, no live submission.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);
date_default_timezone_set('Europe/Athens');
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function badge(string $text, string $type='neutral'): string { return '<span class="badge badge-'.h($type).'">'.h($text).'</span>'; }
$steps = [
  ['Open Firefox with the EDXEIX extension installed','Use the same Firefox profile where the gov.cabnet.app EDXEIX session-capture extension is installed.','Do not share cookies, tokens, screenshots showing hidden values, or credentials.'],
  ['Log into EDXEIX normally','Authenticate through the official EDXEIX/TAXIS flow as usual. Complete any browser prompts.','Login happens only in the browser, not through the bridge.'],
  ['Open the lease-agreement creation form','Navigate to the page where a new chauffeur/lease agreement can be created.','Do not submit a real form from the browser during this refresh.'],
  ['Run the extension session refresh action','Use the extension action that saves the current EDXEIX session metadata to gov.cabnet.app.','Do not expose the saved session JSON contents in chat.'],
  ['Verify the session timestamp changed','Open the probe JSON pages and confirm edxeix_session.json has a fresh modified_at timestamp.','Cookie/token-like data showing YES is enough. Never show raw values.'],
  ['Run the GET-only target matrix','Open /ops/edxeix-target-matrix.php?probe=1&follow=1&format=json.','This is GET-only and must not POST or submit.'],
  ['Confirm the success classification','Expected improvement: EDXEIX_DASHBOARD, EDXEIX_FORM_CANDIDATE, or ideally LEASE_FORM_CANDIDATE.','If it remains LOGIN_OR_SESSION_PAGE, the session is still not authenticated for the form.'],
  ['Stop before live submission','After form access is confirmed, wait for a valid future-safe Bolt candidate and explicit final approval.','Never submit completed, historical, cancelled, terminal, or not-future-safe rows.'],
];
$payload = [
  'ok'=>true,
  'script'=>'ops/edxeix-session-refresh-checklist.php',
  'generated_at'=>date('c'),
  'safety_contract'=>[
    'calls_bolt'=>false,'calls_edxeix'=>false,'posts_to_edxeix'=>false,'reads_database'=>false,
    'writes_database'=>false,'writes_files'=>false,'stages_jobs'=>false,'updates_mappings'=>false,
    'prints_secrets'=>false,'live_edxeix_submission'=>'disabled_not_used','purpose'=>'operator checklist only'
  ],
  'known_blockers'=>[
    'authenticated_edxeix_form'=>'not_confirmed',
    'last_target_matrix'=>'LOGIN_OR_SESSION_PAGE',
    'eligible_future_safe_bolt_candidate'=>'not_present',
    'live_submit'=>'disabled'
  ],
  'success_criteria'=>[
    'edxeix_session_json_modified_at_is_fresh',
    'target_matrix_classification_is_not_only_LOGIN_OR_SESSION_PAGE',
    'preferred_classification_is_LEASE_FORM_CANDIDATE',
    'no_post_performed',
    'no_live_submit_performed'
  ],
  'verification_links'=>[
    'session_probe_json'=>'/ops/edxeix-session-probe.php?format=json',
    'submit_readiness_json'=>'/ops/edxeix-submit-readiness.php?format=json',
    'target_matrix_json'=>'/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json',
    'preflight_review'=>'/ops/preflight-review.php',
    'route_index'=>'/ops/route-index.php'
  ],
  'steps'=>$steps
];
if (strtolower((string)($_GET['format'] ?? 'html')) === 'json') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) . PHP_EOL;
  exit;
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>EDXEIX Session Refresh Checklist | gov.cabnet.app</title><link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=3.0"></head>
<body>
<div class="gov-topbar"><div class="gov-brand"><div class="gov-brand-crest">ΕΔ</div><div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div></div><div class="gov-top-links"><a href="/ops/home.php">Αρχική</a><a href="/ops/edxeix-target-matrix.php">Target Matrix</a><a href="/ops/edxeix-submit-readiness.php">Submit Readiness</a><a href="/ops/preflight-review.php">Preflight</a><a class="gov-logout" href="/ops/route-index.php">Route Index</a></div></div>
<div class="gov-shell"><aside class="gov-sidebar"><h3>Session Refresh</h3><p>Browser-extension session refresh checklist</p><div class="gov-side-group"><div class="gov-side-group-title">EDXEIX preparation</div><a class="gov-side-link active" href="/ops/edxeix-session-refresh-checklist.php">Session Refresh Checklist</a><a class="gov-side-link" href="/ops/edxeix-target-matrix.php">Target Matrix</a><a class="gov-side-link" href="/ops/edxeix-redirect-probe.php">Redirect Probe</a><a class="gov-side-link" href="/ops/edxeix-session-probe.php">Session/Form Probe</a><a class="gov-side-link" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a><div class="gov-side-group-title">Safety</div><a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a><a class="gov-side-link" href="/ops/route-index.php">Route Index</a></div><div class="gov-side-note">Checklist only. No EDXEIX call, no POST, no writes, no live submit.</div></aside>
<div class="gov-content"><div class="gov-page-header"><div><h1 class="gov-page-title">Οδηγός ανανέωσης συνεδρίας EDXEIX</h1><div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Οδηγός ανανέωσης συνεδρίας EDXEIX</div></div><div class="gov-tabs"><a class="gov-tab active" href="/ops/edxeix-session-refresh-checklist.php">Καρτέλα</a><a class="gov-tab" href="/ops/edxeix-session-refresh-checklist.php?format=json">JSON</a><a class="gov-tab" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Matrix JSON</a><a class="gov-tab" href="/ops/edxeix-submit-readiness.php">Readiness</a></div></div>
<main class="wrap wrap-shell">
<section class="safety"><strong>CHECKLIST ONLY.</strong> This page does not call Bolt, does not call EDXEIX, does not POST, does not read/write the database, does not stage jobs, and does not expose secrets.</section>
<section class="card hero warn"><h1>EDXEIX Session Refresh Checklist</h1><p>The target matrix currently resolves protected EDXEIX routes to <code>LOGIN_OR_SESSION_PAGE</code>. Refresh the browser-saved EDXEIX session while logged in and viewing the lease-agreement form, then rerun the GET-only matrix.</p><div><?= badge('LIVE SUBMIT OFF','good') ?> <?= badge('NO EDXEIX CALL HERE','good') ?> <?= badge('NO POST','good') ?> <?= badge('SESSION REFRESH NEEDED','warn') ?></div><div class="grid" style="margin-top:14px"><div class="metric"><strong>8</strong><span>Checklist steps</span></div><div class="metric"><strong>0</strong><span>Backend calls by this page</span></div><div class="metric"><strong>0</strong><span>Eligible candidates currently</span></div><div class="metric"><strong>OFF</strong><span>Live submit</span></div></div><div class="actions"><a class="btn warn" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Run Target Matrix JSON</a><a class="btn" href="/ops/edxeix-session-probe.php?format=json">Session Probe JSON</a><a class="btn dark" href="/ops/edxeix-submit-readiness.php?format=json">Submit Readiness JSON</a></div></section>
<section class="two"><div class="card"><h2>Current known blockers</h2><div class="kv"><div class="k">Authenticated EDXEIX form</div><div><?= badge('NOT CONFIRMED','warn') ?></div><div class="k">Target matrix result</div><div><code>LOGIN_OR_SESSION_PAGE</code></div><div class="k">Eligible Bolt candidate</div><div><?= badge('0','warn') ?></div><div class="k">Live submit</div><div><?= badge('DISABLED','good') ?></div></div></div><div class="card"><h2>Success criteria after refresh</h2><ul class="timeline"><li><code>edxeix_session.json</code> shows a fresh modified timestamp.</li><li>Target matrix no longer reports only <code>LOGIN_OR_SESSION_PAGE</code>.</li><li>Preferred result: <code>LEASE_FORM_CANDIDATE</code>.</li><li>No POST was performed.</li><li>Live submission remains disabled.</li></ul></div></section>
<section class="card"><h2>Step-by-step refresh procedure</h2><div class="table-wrap"><table><thead><tr><th>Step</th><th>Action</th><th>Details</th><th>Safety note</th></tr></thead><tbody><?php foreach ($steps as $i => $step): ?><tr><td><strong><?= h($i+1) ?></strong></td><td><strong><?= h($step[0]) ?></strong></td><td><?= h($step[1]) ?></td><td><?= h($step[2]) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h2>After refreshing the session</h2><ol class="timeline"><li>Open <strong>Session Probe JSON</strong> and confirm <code>edxeix_session.json</code> has a fresh timestamp.</li><li>Open <strong>Target Matrix JSON</strong> and inspect the decision code.</li><li>Success means the decision changes away from <code>EDXEIX_TARGETS_RESOLVE_TO_LOGIN_OR_SESSION_SHELL</code>.</li><li>Ideal success is <code>LEASE_FORM_TARGET_CONFIRMED_GET_ONLY</code>.</li><li>Only after that should we design the final submit handler, still behind eligibility checks and explicit approval.</li></ol><div class="actions"><a class="btn" href="/ops/edxeix-session-probe.php?format=json">Check Session Metadata</a><a class="btn warn" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Run Target Matrix JSON</a><a class="btn dark" href="/ops/edxeix-submit-readiness.php?format=json">Submit Readiness JSON</a><a class="btn good" href="/ops/preflight-review.php">Preflight Review</a></div></section>
<section class="card"><h2>Hard stop rules</h2><ul class="timeline"><li>Do not submit completed, historical, cancelled, terminal, or not-future-safe Bolt rows.</li><li>Do not create jobs from the current blocked rows.</li><li>Do not POST to EDXEIX until a real future-safe candidate exists and you explicitly approve the live-submit test.</li><li>Do not paste raw cookies, CSRF values, session JSON contents, or credentials into chat.</li></ul></section>
</main></div></div></body></html>
