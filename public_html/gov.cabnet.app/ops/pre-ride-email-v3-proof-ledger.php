<?php

declare(strict_types=1);

/**
 * V3 Proof Ledger Ops page.
 *
 * Read-only Ops view for V3 pre-live proof bundles and package artifacts.
 * This page does not execute CLI commands and does not write files.
 */

const V3PL_WEB_VERSION = 'v3.0.73-v3-proof-ledger-ops';
const V3PL_WEB_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.';

function v3pl_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3pl_ops_auth(): void
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
            $next = $_SERVER['REQUEST_URI'] ?? '/ops/pre-ride-email-v3-proof-ledger.php';
            header('Location: /ops/login.php?next=' . rawurlencode($next), true, 302);
            exit;
        }

        http_response_code(500);
        echo 'Ops auth include missing.';
        exit;
    }
}

v3pl_ops_auth();

function v3pl_app_root(): string
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

/** @return list<string> */
function v3pl_glob(string $pattern): array
{
    $files = glob($pattern);
    if (!is_array($files)) {
        return [];
    }
    usort($files, static fn(string $a, string $b): int => (int)filemtime($b) <=> (int)filemtime($a));
    return array_values($files);
}

/** @return array<string,mixed>|null */
function v3pl_read_json(string $path): ?array
{
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }
    $json = json_decode($raw, true);
    return is_array($json) ? $json : null;
}

function v3pl_yesno(mixed $value): string
{
    if ($value === true || $value === 1 || $value === '1' || $value === 'yes') {
        return 'yes';
    }
    if ($value === false || $value === 0 || $value === '0' || $value === 'no') {
        return 'no';
    }
    if ($value === null || $value === '') {
        return '-';
    }
    return (string)$value;
}

function v3pl_badge(mixed $value, string $yesLabel = 'yes', string $noLabel = 'no'): string
{
    $text = v3pl_yesno($value);
    $class = 'neutral';
    if ($text === 'yes') {
        $class = 'good';
        $text = $yesLabel;
    } elseif ($text === 'no') {
        $class = 'bad';
        $text = $noLabel;
    }
    return '<span class="badge ' . $class . '">' . v3pl_h($text) . '</span>';
}

/** @return array<string,mixed> */
function v3pl_bundle(string $path): array
{
    $json = v3pl_read_json($path) ?? [];
    $summary = is_array($json['summary'] ?? null) ? $json['summary'] : [];
    $blocks = is_array($json['final_blocks_observed'] ?? null)
        ? $json['final_blocks_observed']
        : (is_array($json['final_blocks'] ?? null) ? $json['final_blocks'] : []);
    $txt = preg_replace('/_summary\.json$/', '_summary.txt', $path) ?: '';

    return [
        'file' => basename($path),
        'path' => $path,
        'txt_path' => is_file($txt) ? $txt : '',
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : '',
        'json_ok' => $json !== [],
        'ok' => $json['ok'] ?? null,
        'bundle_safe' => $json['bundle_safe'] ?? $summary['bundle_safe'] ?? null,
        'storage_ok' => $summary['storage_ok'] ?? null,
        'payload_consistency_ok' => $summary['payload_consistency_ok'] ?? null,
        'db_vs_artifact_match' => $summary['db_vs_artifact_match'] ?? null,
        'adapter_hash_match' => $summary['adapter_hash_match'] ?? null,
        'adapter_live_capable' => $summary['adapter_live_capable'] ?? null,
        'adapter_submitted' => $summary['adapter_submitted'] ?? null,
        'edxeix_call_made' => $summary['edxeix_call_made'] ?? null,
        'aade_call_made' => $summary['aade_call_made'] ?? null,
        'db_write_made' => $summary['db_write_made'] ?? null,
        'v0_touched' => $summary['v0_touched'] ?? null,
        'blocks' => array_slice(array_map('strval', $blocks), 0, 12),
    ];
}

/** @return array<string,mixed> */
function v3pl_package(string $path): array
{
    $payload = v3pl_read_json($path) ?? [];
    $base = basename($path);
    $queueId = '';
    $stamp = '';
    if (preg_match('/^queue_(\d+)_(\d{8}_\d{6})_edxeix_fields\.json$/', $base, $m)) {
        $queueId = $m[1];
        $stamp = $m[2];
    }
    $prefix = preg_replace('/_edxeix_fields\.json$/', '', $path) ?: $path;
    $safetyPath = $prefix . '_safety_report.json';
    $safety = v3pl_read_json($safetyPath) ?? [];

    return [
        'file' => $base,
        'path' => $path,
        'queue_id' => $queueId,
        'stamp' => $stamp,
        'modified_at' => date('Y-m-d H:i:s', (int)filemtime($path)),
        'size' => (int)filesize($path),
        'json_ok' => $payload !== [],
        'hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
        'lessor' => (string)($payload['lessor'] ?? ''),
        'driver' => (string)($payload['driver'] ?? ''),
        'vehicle' => (string)($payload['vehicle'] ?? ''),
        'starting_point_id' => (string)($payload['starting_point_id'] ?? ''),
        'lessee_name' => (string)($payload['lessee_name'] ?? ''),
        'started_at' => (string)($payload['started_at'] ?? ''),
        'price' => (string)($payload['price'] ?? ''),
        'safety_report' => is_file($safetyPath) ? $safetyPath : '',
        'eligible_for_live_submit_now' => $safety['eligible_for_live_submit_now'] ?? null,
        'current_live_submit_ready' => $safety['current_live_submit_ready'] ?? null,
        'missing_required_fields' => is_array($safety['missing_required_fields'] ?? null) ? $safety['missing_required_fields'] : [],
    ];
}

$appRoot = v3pl_app_root();
$artifactRoot = $appRoot . '/storage/artifacts';
$bundleDir = $artifactRoot . '/v3_pre_live_proof_bundles';
$packageDir = $artifactRoot . '/v3_live_submit_packages';
$cliPath = $appRoot . '/cli/pre_ride_email_v3_proof_ledger.php';
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? max(1, min(100, (int)$_GET['limit'])) : 20;

$bundleFiles = array_slice(v3pl_glob($bundleDir . '/*_summary.json'), 0, $limit);
$packageFiles = array_slice(v3pl_glob($packageDir . '/queue_*_*_edxeix_fields.json'), 0, $limit);
$bundles = array_map('v3pl_bundle', $bundleFiles);
$packages = array_map('v3pl_package', $packageFiles);
$latest = $bundles[0] ?? null;

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>V3 Proof Ledger</title>
    <style>
        :root { --bg:#0d1424; --panel:#121b2d; --panel2:#172236; --line:#2b3855; --text:#f7fbff; --muted:#b9c6da; --good:#16a34a; --bad:#dc2626; --warn:#d97706; --blue:#2563eb; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--text);font-family:Arial,Helvetica,sans-serif;font-size:14px;line-height:1.45}
        a{color:#93c5fd;text-decoration:none} a:hover{text-decoration:underline}
        .wrap{max-width:1260px;margin:0 auto;padding:28px 18px 60px}
        .topnav{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:18px;color:var(--muted)}
        .topnav a{font-weight:700;color:#c7d2fe}
        .hero{background:var(--panel);border:1px solid var(--line);border-radius:18px;padding:24px 26px;margin-bottom:18px;box-shadow:0 10px 30px rgba(0,0,0,.25)}
        .hero h1{margin:0 0 8px;font-size:28px}.hero p{margin:0 0 16px;color:var(--muted)}
        .badges{display:flex;gap:8px;flex-wrap:wrap}.badge{display:inline-block;border-radius:999px;padding:5px 10px;font-size:12px;font-weight:800}.good{background:#dff7e7;color:#056b2f}.bad{background:#fee2e2;color:#991b1b}.warn{background:#ffedd5;color:#9a3412}.neutral{background:#24324c;color:#dbeafe}
        .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:16px}.card h2,.card h3{margin:0 0 10px}.big{font-size:30px;font-weight:900}.muted{color:var(--muted);font-size:12px}.mono{font-family:Consolas,Monaco,monospace}.small{font-size:12px}
        .section{background:var(--panel);border:1px solid var(--line);border-radius:16px;margin:18px 0;padding:18px;overflow:auto}.section h2{margin:0 0 14px;font-size:20px}
        table{width:100%;border-collapse:collapse;min-width:880px} th,td{padding:10px 8px;border-bottom:1px solid var(--line);vertical-align:top;text-align:left} th{background:#1a2538;color:#dbeafe;font-size:12px;text-transform:uppercase;letter-spacing:.04em} td{color:#f8fafc}.path{color:#bfdbfe;font-size:12px;word-break:break-all}.flags{display:flex;gap:5px;flex-wrap:wrap}.blocks{margin:8px 0 0;padding-left:18px;color:#fed7aa}.cmd{background:#070b16;border:1px solid #263248;border-radius:12px;padding:14px;color:#f8fafc;white-space:pre-wrap;word-break:break-word}.buttonrow{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.btn{display:inline-block;border-radius:10px;padding:10px 14px;font-weight:800;background:#1f2a44;color:white}.btn.green{background:#15803d}.btn.blue{background:#1d4ed8}.btn.orange{background:#b45309}
        @media(max-width:900px){.grid{grid-template-columns:1fr}.wrap{padding:16px 10px}.hero h1{font-size:24px}}
    </style>
</head>
<body>
<div class="wrap">
    <div class="topnav">
        <strong>EA / gov.cabnet.app</strong>
        <a href="/ops/">Ops Index</a>
        <a href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Pre-Live Switchboard</a>
        <a href="/ops/pre-ride-email-v3-proof.php">Proof Dashboard</a>
        <a href="/ops/pre-ride-email-v3-live-package-export.php">Package Export</a>
    </div>

    <section class="hero">
        <div class="badges">
            <span class="badge good">READ ONLY</span>
            <span class="badge good">NO EDXEIX CALL</span>
            <span class="badge good">NO AADE CALL</span>
            <span class="badge good">NO DB WRITES</span>
            <span class="badge good">V0 UNTOUCHED</span>
        </div>
        <h1>V3 Proof Ledger</h1>
        <p>Read-only evidence ledger for V3 proof bundles and local EDXEIX field package artifacts.</p>
        <div class="buttonrow">
            <a class="btn green" href="?refresh=1">Refresh ledger</a>
            <a class="btn blue" href="/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php">Proof Bundle Export</a>
            <a class="btn orange" href="/ops/pre-ride-email-v3-pre-live-switchboard.php">Pre-Live Switchboard</a>
        </div>
    </section>

    <div class="grid">
        <div class="card"><h3>Latest bundle</h3><div class="big"><?php echo $latest ? v3pl_h($latest['bundle_safe'] === true ? 'safe' : 'review') : 'none'; ?></div><div class="muted">bundle_safe</div></div>
        <div class="card"><h3>Proof bundles</h3><div class="big"><?php echo count(v3pl_glob($bundleDir . '/*_summary.json')); ?></div><div class="muted">local summaries found</div></div>
        <div class="card"><h3>Package exports</h3><div class="big"><?php echo count(v3pl_glob($packageDir . '/queue_*_*_edxeix_fields.json')); ?></div><div class="muted">EDXEIX field JSON files</div></div>
        <div class="card"><h3>Live adapter</h3><div class="big"><?php echo $latest ? v3pl_h(v3pl_yesno($latest['adapter_live_capable'])) : '-'; ?></div><div class="muted">latest proof says live-capable</div></div>
    </div>

    <section class="section">
        <h2>Current file status</h2>
        <table>
            <tr><th>Item</th><th>Status</th><th>Path</th></tr>
            <tr><td>CLI ledger</td><td><?php echo v3pl_badge(is_file($cliPath) && is_readable($cliPath)); ?></td><td class="path"><?php echo v3pl_h($cliPath); ?></td></tr>
            <tr><td>Proof bundle directory</td><td><?php echo v3pl_badge(is_dir($bundleDir) && is_readable($bundleDir)); ?></td><td class="path"><?php echo v3pl_h($bundleDir); ?></td></tr>
            <tr><td>Live package directory</td><td><?php echo v3pl_badge(is_dir($packageDir) && is_readable($packageDir)); ?></td><td class="path"><?php echo v3pl_h($packageDir); ?></td></tr>
            <tr><td>Version</td><td colspan="2" class="mono"><?php echo v3pl_h(V3PL_WEB_VERSION); ?></td></tr>
            <tr><td>Safety</td><td colspan="2"><?php echo v3pl_h(V3PL_WEB_SAFETY); ?></td></tr>
        </table>
    </section>

    <section class="section">
        <h2>Latest proof bundles</h2>
        <table>
            <thead><tr><th>File</th><th>Safe</th><th>Core checks</th><th>No-call proof</th><th>Modified</th><th>Final blocks</th></tr></thead>
            <tbody>
            <?php if (!$bundles): ?>
                <tr><td colspan="6">No proof bundle summaries found.</td></tr>
            <?php else: foreach ($bundles as $bundle): ?>
                <tr>
                    <td><strong><?php echo v3pl_h($bundle['file']); ?></strong><div class="path"><?php echo v3pl_h($bundle['path']); ?></div><?php if ($bundle['txt_path']): ?><div class="path"><?php echo v3pl_h($bundle['txt_path']); ?></div><?php endif; ?></td>
                    <td><?php echo v3pl_badge($bundle['bundle_safe'], 'safe', 'not safe'); ?><br><?php echo v3pl_badge($bundle['ok'], 'ok', 'not ok'); ?></td>
                    <td><div class="flags"><?php echo v3pl_badge($bundle['storage_ok'], 'storage ok', 'storage no'); ?><?php echo v3pl_badge($bundle['payload_consistency_ok'], 'payload ok', 'payload no'); ?><?php echo v3pl_badge($bundle['db_vs_artifact_match'], 'db/artifact match', 'db/artifact mismatch'); ?><?php echo v3pl_badge($bundle['adapter_hash_match'], 'hash match', 'hash mismatch'); ?></div></td>
                    <td><div class="flags"><?php echo v3pl_badge($bundle['edxeix_call_made'], 'EDXEIX yes', 'EDXEIX no'); ?><?php echo v3pl_badge($bundle['aade_call_made'], 'AADE yes', 'AADE no'); ?><?php echo v3pl_badge($bundle['db_write_made'], 'DB write yes', 'DB write no'); ?><?php echo v3pl_badge($bundle['v0_touched'], 'V0 yes', 'V0 no'); ?><?php echo v3pl_badge($bundle['adapter_submitted'], 'submitted yes', 'submitted no'); ?></div></td>
                    <td class="mono small"><?php echo v3pl_h($bundle['modified_at']); ?><br><?php echo (int)$bundle['size']; ?> bytes</td>
                    <td><?php if ($bundle['blocks']): ?><ul class="blocks"><?php foreach ($bundle['blocks'] as $block): ?><li><?php echo v3pl_h($block); ?></li><?php endforeach; ?></ul><?php else: ?><span class="muted">none</span><?php endif; ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="section">
        <h2>Latest local EDXEIX field package exports</h2>
        <table>
            <thead><tr><th>Queue</th><th>Payload</th><th>IDs</th><th>Hash</th><th>Safety</th><th>Modified</th></tr></thead>
            <tbody>
            <?php if (!$packages): ?>
                <tr><td colspan="6">No EDXEIX field package exports found.</td></tr>
            <?php else: foreach ($packages as $pkg): ?>
                <tr>
                    <td><strong>#<?php echo v3pl_h($pkg['queue_id']); ?></strong><div class="path"><?php echo v3pl_h($pkg['file']); ?></div></td>
                    <td><?php echo v3pl_h($pkg['lessee_name']); ?><br><span class="muted"><?php echo v3pl_h($pkg['started_at']); ?> · €<?php echo v3pl_h($pkg['price']); ?></span><div class="path"><?php echo v3pl_h($pkg['path']); ?></div></td>
                    <td class="mono small">lessor=<?php echo v3pl_h($pkg['lessor']); ?><br>driver=<?php echo v3pl_h($pkg['driver']); ?><br>vehicle=<?php echo v3pl_h($pkg['vehicle']); ?><br>start=<?php echo v3pl_h($pkg['starting_point_id']); ?></td>
                    <td class="mono small"><?php echo v3pl_h(substr((string)$pkg['hash'], 0, 24)); ?>…</td>
                    <td><div class="flags"><?php echo v3pl_badge($pkg['current_live_submit_ready'], 'live-ready', 'not live-ready'); ?><?php echo v3pl_badge($pkg['eligible_for_live_submit_now'], 'eligible now', 'not eligible'); ?><?php echo v3pl_badge(empty($pkg['missing_required_fields']), 'fields complete', 'fields missing'); ?></div><?php if (!empty($pkg['missing_required_fields'])): ?><div class="muted"><?php echo v3pl_h(implode(', ', $pkg['missing_required_fields'])); ?></div><?php endif; ?></td>
                    <td class="mono small"><?php echo v3pl_h($pkg['modified_at']); ?><br><?php echo (int)$pkg['size']; ?> bytes</td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="section">
        <h2>Run ledger from terminal</h2>
        <p class="muted">These commands are read-only. They do not call Bolt, EDXEIX, or AADE and do not write to the database.</p>
        <div class="cmd">su -s /bin/bash cabnet -c "/usr/local/bin/php <?php echo v3pl_h($cliPath); ?>"</div>
        <br>
        <div class="cmd">su -s /bin/bash cabnet -c "/usr/local/bin/php <?php echo v3pl_h($cliPath); ?> --json"</div>
    </section>
</div>
</body>
</html>
