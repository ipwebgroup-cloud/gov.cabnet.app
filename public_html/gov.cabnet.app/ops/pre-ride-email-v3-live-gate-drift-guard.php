<?php

declare(strict_types=1);

/**
 * V3 Live Gate Drift Guard Ops page.
 *
 * Read-only browser view. This page does not execute terminal commands and
 * does not write files. It only inspects the live-submit gate config and
 * known adapter files from the private app path.
 */

const V3GD_WEB_VERSION = 'v3.0.74-v3-live-gate-drift-guard-ops';
const V3GD_WEB_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.';
const V3GD_GATE_CONFIG_PATH = '/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';

function v3gd_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3gd_ops_auth(): void
{
    $candidates = [
        __DIR__ . '/_auth.php',
        __DIR__ . '/auth.php',
        __DIR__ . '/ops-auth.php',
        __DIR__ . '/includes/auth.php',
        __DIR__ . '/includes/ops-auth.php',
        dirname(__DIR__) . '/includes/ops-auth.php',
        dirname(__DIR__) . '/includes/auth.php',
        dirname(__DIR__) . '/ops/includes/ops-auth.php',
        dirname(__DIR__) . '/ops/includes/auth.php',
    ];

    foreach ($candidates as $file) {
        if (is_file($file) && is_readable($file)) {
            require_once $file;
            return;
        }
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name('gov_cabnet_ops_session');
        @session_start();
    }

    $authenticated = false;
    foreach (['ops_user', 'ops_user_id', 'ops_admin', 'admin_user', 'admin_id', 'user_id', 'logged_in', 'authenticated'] as $key) {
        if (!empty($_SESSION[$key])) {
            $authenticated = true;
            break;
        }
    }

    if (!$authenticated) {
        $login = __DIR__ . '/login.php';
        if (is_file($login)) {
            $next = $_SERVER['REQUEST_URI'] ?? '/ops/pre-ride-email-v3-live-gate-drift-guard.php';
            header('Location: /ops/login.php?next=' . rawurlencode($next), true, 302);
            exit;
        }

        http_response_code(500);
        echo 'Ops auth include missing.';
        exit;
    }
}

v3gd_ops_auth();

function v3gd_bool(mixed $value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int)$value === 1;
    }
    if (is_string($value)) {
        $v = strtolower(trim($value));
        if (in_array($v, ['1', 'true', 'yes', 'on', 'enabled', 'live'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true)) {
            return false;
        }
    }
    return $default;
}

function v3gd_yesno(mixed $value): string
{
    return v3gd_bool($value) ? 'yes' : 'no';
}

function v3gd_badge(mixed $value, string $yes = 'yes', string $no = 'no'): string
{
    $b = v3gd_bool($value);
    return '<span class="badge ' . ($b ? 'good' : 'bad') . '">' . v3gd_h($b ? $yes : $no) . '</span>';
}

function v3gd_text_badge(string $text, string $kind = 'neutral'): string
{
    return '<span class="badge ' . v3gd_h($kind) . '">' . v3gd_h($text) . '</span>';
}

/** @param array<string,mixed> $source */
function v3gd_pick(array $source, array $keys, mixed $default = null): mixed
{
    foreach ($keys as $key) {
        $parts = explode('.', (string)$key);
        $cur = $source;
        $found = true;
        foreach ($parts as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                $found = false;
                break;
            }
            $cur = $cur[$part];
        }
        if ($found) {
            return $cur;
        }
    }
    return $default;
}

/** @return array<string,mixed> */
function v3gd_safe_file(string $path): array
{
    return [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
    ];
}

/** @return array<string,mixed> */
function v3gd_load_gate_config(string $path): array
{
    $out = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'returned_array' => false,
        'error' => '',
        'safe' => [
            'enabled' => false,
            'mode' => 'missing',
            'adapter' => 'missing',
            'hard_enable_live_submit' => false,
            'acknowledgement_phrase_present' => false,
        ],
    ];

    if (!is_file($path)) {
        $out['error'] = 'gate config file missing';
        return $out;
    }
    if (!is_readable($path)) {
        $out['error'] = 'gate config file not readable';
        return $out;
    }

    try {
        /** @noinspection PhpIncludeInspection */
        $config = include $path;
    } catch (Throwable $e) {
        $out['error'] = 'gate config include failed: ' . $e->getMessage();
        return $out;
    }

    $out['loaded'] = true;
    if (!is_array($config)) {
        $out['error'] = 'gate config did not return an array';
        return $out;
    }

    $out['returned_array'] = true;

    $enabledRaw = v3gd_pick($config, ['enabled', 'live_submit.enabled', 'pre_ride_email_v3.enabled', 'v3_live_submit.enabled'], false);
    $modeRaw = v3gd_pick($config, ['mode', 'live_submit.mode', 'pre_ride_email_v3.mode', 'v3_live_submit.mode'], 'missing');
    $adapterRaw = v3gd_pick($config, ['adapter', 'live_submit.adapter', 'pre_ride_email_v3.adapter', 'v3_live_submit.adapter'], 'missing');
    $hardRaw = v3gd_pick($config, ['hard_enable_live_submit', 'hard_enabled', 'live_submit.hard_enable_live_submit', 'pre_ride_email_v3.hard_enable_live_submit', 'v3_live_submit.hard_enable_live_submit'], false);
    $ackRaw = v3gd_pick($config, ['required_acknowledgement_phrase', 'acknowledgement_phrase', 'required_ack_phrase', 'live_submit.required_acknowledgement_phrase', 'live_submit.acknowledgement_phrase', 'pre_ride_email_v3.required_acknowledgement_phrase', 'v3_live_submit.required_acknowledgement_phrase'], '');

    $out['safe'] = [
        'enabled' => v3gd_bool($enabledRaw),
        'mode' => strtolower(trim((string)$modeRaw)),
        'adapter' => strtolower(trim((string)$adapterRaw)),
        'hard_enable_live_submit' => v3gd_bool($hardRaw),
        'acknowledgement_phrase_present' => trim((string)$ackRaw) !== '',
    ];

    return $out;
}

/** @return array<string,mixed> */
function v3gd_adapter_files(string $appRoot): array
{
    $base = rtrim($appRoot, '/') . '/src/BoltMailV3';
    $files = [
        'interface' => $base . '/LiveSubmitAdapterV3.php',
        'disabled' => $base . '/DisabledLiveSubmitAdapterV3.php',
        'dry_run' => $base . '/DryRunLiveSubmitAdapterV3.php',
        'edxeix_live' => $base . '/EdxeixLiveSubmitAdapterV3.php',
    ];
    $out = [];
    foreach ($files as $key => $path) {
        $meta = v3gd_safe_file($path);
        $scan = [
            'contains_submit_method' => false,
            'contains_curl' => false,
            'contains_file_get_contents_http' => false,
            'contains_stream_context' => false,
            'contains_live_capable_true' => false,
            'contains_skeleton_not_implemented' => false,
        ];
        if (is_file($path) && is_readable($path)) {
            $raw = file_get_contents($path);
            if (is_string($raw)) {
                $scan['contains_submit_method'] = str_contains($raw, 'function submit(');
                $scan['contains_curl'] = stripos($raw, 'curl_') !== false;
                $scan['contains_file_get_contents_http'] = stripos($raw, 'file_get_contents(') !== false && stripos($raw, 'http') !== false;
                $scan['contains_stream_context'] = stripos($raw, 'stream_context_create') !== false;
                $scan['contains_live_capable_true'] = stripos($raw, 'isLiveCapable') !== false && (bool)preg_match('/isLiveCapable\s*\([^)]*\)\s*:\s*bool\s*\{[^}]*return\s+true\s*;/is', $raw);
                $scan['contains_skeleton_not_implemented'] = stripos($raw, 'skeleton_not_implemented') !== false || stripos($raw, 'not implemented') !== false;
            }
        }
        $out[$key] = array_merge($meta, ['scan' => $scan]);
    }
    return $out;
}

/** @return array<string,mixed> */
function v3gd_analyze_gate(array $config, array $adapterFiles): array
{
    $safe = is_array($config['safe'] ?? null) ? $config['safe'] : [];
    $enabled = v3gd_bool($safe['enabled'] ?? false);
    $mode = strtolower(trim((string)($safe['mode'] ?? 'missing')));
    $adapter = strtolower(trim((string)($safe['adapter'] ?? 'missing')));
    $hard = v3gd_bool($safe['hard_enable_live_submit'] ?? false);
    $ack = v3gd_bool($safe['acknowledgement_phrase_present'] ?? false);

    $blocks = [];
    if (!$enabled) {
        $blocks[] = 'master_gate: enabled is false';
    }
    if ($mode !== 'live') {
        $blocks[] = 'master_gate: mode is not live';
    }
    if ($adapter !== 'edxeix_live') {
        $blocks[] = 'master_gate: adapter is not edxeix_live';
    }
    if (!$hard) {
        $blocks[] = 'master_gate: hard_enable_live_submit is false';
    }
    if (!$ack) {
        $blocks[] = 'master_gate: acknowledgement phrase is not present';
    }

    $expectedClosed = !$enabled && in_array($mode, ['disabled', 'missing', ''], true) && in_array($adapter, ['disabled', 'missing', ''], true) && !$hard;
    $partialLiveSignals = [];
    if ($enabled) {
        $partialLiveSignals[] = 'enabled=true';
    }
    if ($mode === 'live') {
        $partialLiveSignals[] = 'mode=live';
    }
    if ($adapter === 'edxeix_live') {
        $partialLiveSignals[] = 'adapter=edxeix_live';
    }
    if ($hard) {
        $partialLiveSignals[] = 'hard_enable_live_submit=true';
    }

    $edxeixScan = $adapterFiles['edxeix_live']['scan'] ?? [];
    $adapterLooksLiveCapable = !empty($edxeixScan['contains_live_capable_true']) || !empty($edxeixScan['contains_curl']) || !empty($edxeixScan['contains_file_get_contents_http']) || !empty($edxeixScan['contains_stream_context']);
    $liveRisk = count($partialLiveSignals) >= 2 || ($adapterLooksLiveCapable && count($partialLiveSignals) >= 1);
    $fullLiveSwitchLooksOpen = $enabled && $mode === 'live' && $adapter === 'edxeix_live' && $hard && $ack;

    $driftBlocks = [];
    if (!$expectedClosed) {
        $driftBlocks[] = 'safe_closed_expected: gate config differs from disabled pre-live posture';
    }
    if ($liveRisk) {
        $driftBlocks[] = 'live_risk: partial or full live-submit switch signals detected';
    }
    if ($fullLiveSwitchLooksOpen) {
        $driftBlocks[] = 'critical: all master gate live switch fields appear open';
    }
    if ($adapterLooksLiveCapable && $adapter !== 'disabled') {
        $driftBlocks[] = 'adapter_review: real-live adapter appears capable or network-aware';
    }

    return [
        'enabled' => $enabled,
        'mode' => $mode,
        'adapter' => $adapter,
        'hard_enable_live_submit' => $hard,
        'acknowledgement_phrase_present' => $ack,
        'expected_closed_pre_live' => $expectedClosed,
        'master_gate_live_blocks' => $blocks,
        'partial_live_signals' => $partialLiveSignals,
        'adapter_looks_live_capable' => $adapterLooksLiveCapable,
        'full_live_switch_looks_open' => $fullLiveSwitchLooksOpen,
        'live_risk_detected' => $liveRisk,
        'drift_detected' => $driftBlocks !== [],
        'drift_blocks' => $driftBlocks,
    ];
}

/** @return list<array<string,mixed>> */
function v3gd_latest_files(string $pattern, int $limit = 5): array
{
    $files = glob($pattern);
    $files = is_array($files) ? $files : [];
    usort($files, static fn(string $a, string $b): int => (int)filemtime($b) <=> (int)filemtime($a));
    $out = [];
    foreach (array_slice($files, 0, $limit) as $file) {
        $out[] = [
            'file' => basename($file),
            'path' => $file,
            'size' => is_file($file) ? (int)filesize($file) : 0,
            'modified_at' => is_file($file) ? date('Y-m-d H:i:s', (int)filemtime($file)) : '',
        ];
    }
    return $out;
}

function v3gd_app_root(): string
{
    $candidates = [
        getenv('GOVCABNET_APP_ROOT') ?: '',
        '/home/cabnet/gov.cabnet.app_app',
        dirname(__DIR__, 3) . '/gov.cabnet.app_app',
    ];
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_dir($candidate)) {
            return rtrim($candidate, '/');
        }
    }
    return '/home/cabnet/gov.cabnet.app_app';
}

$appRoot = v3gd_app_root();
$config = v3gd_load_gate_config(V3GD_GATE_CONFIG_PATH);
$adapterFiles = v3gd_adapter_files($appRoot);
$gate = v3gd_analyze_gate($config, $adapterFiles);
$finalBlocks = [];
if (empty($config['loaded']) || empty($config['returned_array'])) {
    $finalBlocks[] = 'config: ' . (string)($config['error'] ?? 'not loaded');
}
foreach ($gate['drift_blocks'] as $block) {
    $finalBlocks[] = (string)$block;
}
$ok = $finalBlocks === [];
$bundleDir = $appRoot . '/storage/artifacts/v3_pre_live_proof_bundles';
$packageDir = $appRoot . '/storage/artifacts/v3_live_submit_packages';
$latestBundles = v3gd_latest_files($bundleDir . '/bundle_*_summary.json', 6);
$latestPackages = v3gd_latest_files($packageDir . '/queue_*_edxeix_fields.json', 6);
$cliPath = $appRoot . '/cli/pre_ride_email_v3_live_gate_drift_guard.php';
$cliCommand = 'su -s /bin/bash cabnet -c "/usr/local/bin/php ' . $cliPath . ' --json"';

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>V3 Live Gate Drift Guard</title>
    <style>
        :root{--bg:#eef3fb;--panel:#fff;--ink:#071d49;--muted:#405481;--line:#ced8ea;--nav:#2f3a66;--green:#22a652;--red:#d94848;--amber:#d98b00;--blue:#4459bd;}
        *{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:14px/1.45 system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif}.wrap{display:flex;min-height:100vh}.side{width:270px;background:var(--nav);color:#fff;padding:22px 16px;flex:0 0 270px}.side h1{font-size:18px;margin:0 0 12px}.side p{font-size:13px;margin:0 0 20px;color:#dbe4ff}.side a{display:block;color:#fff;text-decoration:none;padding:9px 10px;border-radius:8px;margin:4px 0}.side a.active,.side a:hover{background:rgba(255,255,255,.14)}.main{padding:26px;max-width:1280px;width:100%}.hero,.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;box-shadow:0 8px 20px rgba(4,20,60,.06)}.hero{padding:22px 24px;margin-bottom:16px;border-left:6px solid <?= $ok ? '#22a652' : '#d98b00' ?>}.hero h2{font-size:30px;line-height:1.15;margin:6px 0 8px}.sub{color:var(--muted)}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin:16px 0}.card{padding:16px}.metric{font-size:30px;font-weight:800;line-height:1}.label{font-size:12px;color:var(--muted);margin-top:3px}.badge{display:inline-block;border-radius:999px;padding:4px 10px;font-size:12px;font-weight:700;margin:3px 4px 3px 0}.good{background:#ddf7e7;color:#006b28}.bad{background:#fde2df;color:#9a1515}.warn{background:#fff3cd;color:#8a5a00}.neutral{background:#e8edfa;color:#26376d}.blue{background:#e3e8ff;color:#2636a2}.btn{display:inline-block;text-decoration:none;border-radius:9px;padding:10px 14px;color:#fff;background:var(--blue);font-weight:700;margin:8px 8px 0 0}.btn.green{background:var(--green)}.btn.dark{background:#13204e}.two{display:grid;grid-template-columns:1fr 1fr;gap:12px}.table{width:100%;border-collapse:collapse}.table th,.table td{border-bottom:1px solid var(--line);padding:9px;text-align:left;vertical-align:top}.table th{background:#f3f6fb;color:#31466f}.mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12px;white-space:pre-wrap}.alert{border:1px solid #fac47a;background:#fff8ed;border-radius:12px;padding:14px;margin:14px 0;color:#7a3f00}.okbox{border:1px solid #9be0b0;background:#effbf2;border-radius:12px;padding:14px;margin:14px 0;color:#075d25}.muted{color:var(--muted)}@media(max-width:980px){.wrap{display:block}.side{width:auto}.grid,.two{grid-template-columns:1fr}.main{padding:14px}}
    </style>
</head>
<body>
<div class="wrap">
    <aside class="side">
        <h1>V3 Automation</h1>
        <p>Read-only live gate drift guard. Live submit remains disabled unless explicitly approved.</p>
        <a class="active" href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Gate Drift Guard</a>
        <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Pre-Live Switchboard</a>
        <a href="/ops/pre-ride-email-v3-proof-ledger.php">Proof Ledger</a>
        <a href="/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php">Proof Bundle Export</a>
        <a href="/ops/pre-ride-email-v3-adapter-payload-consistency.php">Payload Consistency</a>
        <a href="/ops/pre-ride-email-v3-adapter-row-simulation.php">Adapter Simulation</a>
        <a href="/ops/pre-ride-email-v3-live-adapter-kill-switch-check.php">Kill-Switch Check</a>
    </aside>
    <main class="main">
        <section class="hero">
            <?= $ok ? v3gd_text_badge('EXPECTED CLOSED POSTURE', 'good') : v3gd_text_badge('DRIFT / REVIEW REQUIRED', 'warn') ?>
            <?= !empty($gate['live_risk_detected']) ? v3gd_text_badge('LIVE RISK SIGNAL', 'bad') : v3gd_text_badge('NO LIVE RISK SIGNAL', 'good') ?>
            <?= !empty($gate['full_live_switch_looks_open']) ? v3gd_text_badge('FULL LIVE SWITCH OPEN', 'bad') : v3gd_text_badge('FULL LIVE SWITCH CLOSED', 'good') ?>
            <h2>V3 Live Gate Drift Guard</h2>
            <div class="sub"><?= v3gd_h(V3GD_WEB_SAFETY) ?></div>
            <a class="btn green" href="/ops/pre-ride-email-v3-live-gate-drift-guard.php">Refresh Guard</a>
            <a class="btn" href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Open Switchboard</a>
            <a class="btn dark" href="/ops/pre-ride-email-v3-proof-ledger.php">Open Proof Ledger</a>
        </section>

        <?php if ($ok): ?>
            <div class="okbox"><strong>Safe state:</strong> the master gate still matches the expected disabled pre-live posture.</div>
        <?php else: ?>
            <div class="alert"><strong>Review required:</strong> one or more gate/config fields differ from the expected disabled pre-live posture. This page did not change anything.</div>
        <?php endif; ?>

        <section class="grid">
            <div class="card"><div class="metric"><?= v3gd_h((string)($gate['mode'] ?? '-')) ?></div><div class="label">Gate mode</div></div>
            <div class="card"><div class="metric"><?= v3gd_h((string)($gate['adapter'] ?? '-')) ?></div><div class="label">Configured adapter</div></div>
            <div class="card"><div class="metric"><?= !empty($gate['expected_closed_pre_live']) ? 'yes' : 'no' ?></div><div class="label">Expected closed posture</div></div>
            <div class="card"><div class="metric"><?= !empty($gate['live_risk_detected']) ? 'yes' : 'no' ?></div><div class="label">Live risk detected</div></div>
        </section>

        <section class="two">
            <div class="card">
                <h3>Master Gate Fields</h3>
                <table class="table">
                    <tr><th>Config path</th><td class="mono"><?= v3gd_h((string)($config['path'] ?? '')) ?></td></tr>
                    <tr><th>Exists / readable</th><td><?= v3gd_badge($config['exists'] ?? false, 'exists', 'missing') ?> <?= v3gd_badge($config['readable'] ?? false, 'readable', 'not readable') ?></td></tr>
                    <tr><th>Loaded</th><td><?= v3gd_badge($config['loaded'] ?? false) ?> <?= v3gd_badge($config['returned_array'] ?? false, 'array', 'not array') ?></td></tr>
                    <tr><th>Enabled</th><td><?= v3gd_badge($gate['enabled'] ?? false, 'yes', 'no') ?></td></tr>
                    <tr><th>Mode</th><td><?= v3gd_h((string)($gate['mode'] ?? '-')) ?></td></tr>
                    <tr><th>Adapter</th><td><?= v3gd_h((string)($gate['adapter'] ?? '-')) ?></td></tr>
                    <tr><th>Hard enable</th><td><?= v3gd_badge($gate['hard_enable_live_submit'] ?? false, 'yes', 'no') ?></td></tr>
                    <tr><th>Ack phrase</th><td><?= v3gd_badge($gate['acknowledgement_phrase_present'] ?? false, 'present', 'absent') ?></td></tr>
                </table>
            </div>
            <div class="card">
                <h3>Safety Analysis</h3>
                <table class="table">
                    <tr><th>Expected disabled pre-live</th><td><?= v3gd_badge($gate['expected_closed_pre_live'] ?? false) ?></td></tr>
                    <tr><th>Adapter network-aware/live-capable scan</th><td><?= v3gd_badge($gate['adapter_looks_live_capable'] ?? false, 'yes', 'no') ?></td></tr>
                    <tr><th>Full live switch appears open</th><td><?= v3gd_badge($gate['full_live_switch_looks_open'] ?? false, 'yes', 'no') ?></td></tr>
                    <tr><th>Live risk signal</th><td><?= v3gd_badge($gate['live_risk_detected'] ?? false, 'yes', 'no') ?></td></tr>
                    <tr><th>Version</th><td class="mono"><?= v3gd_h(V3GD_WEB_VERSION) ?></td></tr>
                </table>
                <h4>Terminal check</h4>
                <div class="mono" style="background:#0a1027;color:#fff;border-radius:10px;padding:12px"><?= v3gd_h($cliCommand) ?></div>
            </div>
        </section>

        <?php if (!empty($gate['partial_live_signals'])): ?>
            <section class="alert">
                <strong>Partial live signals detected</strong>
                <ul><?php foreach ($gate['partial_live_signals'] as $signal): ?><li><?= v3gd_h((string)$signal) ?></li><?php endforeach; ?></ul>
            </section>
        <?php endif; ?>

        <section class="two" style="margin-top:12px">
            <div class="card">
                <h3>Master Gate Live Blockers</h3>
                <?php if (!empty($gate['master_gate_live_blocks'])): ?>
                    <ul><?php foreach ($gate['master_gate_live_blocks'] as $block): ?><li><?= v3gd_h((string)$block) ?></li><?php endforeach; ?></ul>
                <?php else: ?>
                    <p class="muted">No master gate blockers detected. This would require immediate review before any live path.</p>
                <?php endif; ?>
            </div>
            <div class="card">
                <h3>Final Blocks</h3>
                <?php if ($finalBlocks): ?>
                    <ul><?php foreach ($finalBlocks as $block): ?><li><?= v3gd_h($block) ?></li><?php endforeach; ?></ul>
                <?php else: ?>
                    <p class="muted">No drift blocks. Gate is closed as expected.</p>
                <?php endif; ?>
            </div>
        </section>

        <section class="card" style="margin-top:12px">
            <h3>Adapter File Scan</h3>
            <table class="table">
                <thead><tr><th>Adapter</th><th>File</th><th>Exists</th><th>Readable</th><th>Network/live scan</th></tr></thead>
                <tbody>
                <?php foreach ($adapterFiles as $key => $meta): $scan = is_array($meta['scan'] ?? null) ? $meta['scan'] : []; ?>
                    <tr>
                        <td class="mono"><?= v3gd_h((string)$key) ?></td>
                        <td class="mono"><?= v3gd_h((string)($meta['path'] ?? '')) ?></td>
                        <td><?= v3gd_badge($meta['exists'] ?? false, 'yes', 'no') ?></td>
                        <td><?= v3gd_badge($meta['readable'] ?? false, 'yes', 'no') ?></td>
                        <td>
                            <?= v3gd_badge($scan['contains_live_capable_true'] ?? false, 'live_capable_true', 'not live_capable_true') ?>
                            <?= v3gd_badge($scan['contains_curl'] ?? false, 'curl', 'no curl') ?>
                            <?= v3gd_badge($scan['contains_file_get_contents_http'] ?? false, 'http read', 'no http read') ?>
                            <?= v3gd_badge($scan['contains_stream_context'] ?? false, 'stream context', 'no stream context') ?>
                            <?= v3gd_badge($scan['contains_skeleton_not_implemented'] ?? false, 'skeleton marker', 'no skeleton marker') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </section>

        <section class="two" style="margin-top:12px">
            <div class="card">
                <h3>Latest Proof Bundles</h3>
                <table class="table"><thead><tr><th>File</th><th>Size</th><th>Modified</th></tr></thead><tbody>
                <?php foreach ($latestBundles as $file): ?>
                    <tr><td class="mono"><?= v3gd_h((string)$file['file']) ?><br><span class="muted"><?= v3gd_h((string)$file['path']) ?></span></td><td><?= v3gd_h((string)$file['size']) ?></td><td><?= v3gd_h((string)$file['modified_at']) ?></td></tr>
                <?php endforeach; if (!$latestBundles): ?><tr><td colspan="3" class="muted">No proof bundle summaries found.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
            <div class="card">
                <h3>Latest Local Package Exports</h3>
                <table class="table"><thead><tr><th>File</th><th>Size</th><th>Modified</th></tr></thead><tbody>
                <?php foreach ($latestPackages as $file): ?>
                    <tr><td class="mono"><?= v3gd_h((string)$file['file']) ?><br><span class="muted"><?= v3gd_h((string)$file['path']) ?></span></td><td><?= v3gd_h((string)$file['size']) ?></td><td><?= v3gd_h((string)$file['modified_at']) ?></td></tr>
                <?php endforeach; if (!$latestPackages): ?><tr><td colspan="3" class="muted">No EDXEIX field package exports found.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
