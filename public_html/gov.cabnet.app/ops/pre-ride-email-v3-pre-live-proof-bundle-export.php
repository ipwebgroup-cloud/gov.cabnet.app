<?php

declare(strict_types=1);

/**
 * V3 pre-live proof bundle export Ops page.
 *
 * Read-only web page. It does not execute CLI commands and does not write files.
 * Use the CLI command shown on this page to create the server-side proof bundle.
 */

$authFile = __DIR__ . '/_ops-auth.php';
if (is_file($authFile)) {
    require_once $authFile;
} else {
    http_response_code(500);
    echo 'Ops auth include missing.';
    exit;
}

if (function_exists('gov_ops_require_auth')) {
    gov_ops_require_auth();
} elseif (function_exists('ops_require_auth')) {
    ops_require_auth();
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function v3proof_app_root(): string
{
    $env = getenv('GOV_CABNET_APP_ROOT');
    if (is_string($env) && trim($env) !== '') {
        return rtrim(trim($env), '/');
    }
    return '/home/cabnet/gov.cabnet.app_app';
}

/**
 * @return array<int,array<string,mixed>>
 */
function v3proof_latest_files(string $dir): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob(rtrim($dir, '/') . '/bundle_*_*.*') ?: [];
    rsort($files, SORT_STRING);

    $out = [];
    foreach (array_slice($files, 0, 20) as $file) {
        $out[] = [
            'name' => basename($file),
            'path' => $file,
            'size' => is_file($file) ? filesize($file) : 0,
            'mtime' => is_file($file) ? date('Y-m-d H:i:s', filemtime($file)) : '',
            'readable' => is_readable($file),
        ];
    }

    return $out;
}

$appRoot = v3proof_app_root();
$cliPath = $appRoot . '/cli/pre_ride_email_v3_pre_live_proof_bundle_export.php';
$artifactDir = $appRoot . '/storage/artifacts/v3_pre_live_proof_bundles';
$latestFiles = v3proof_latest_files($artifactDir);
$queueId = isset($_GET['queue_id']) ? preg_replace('/[^0-9]/', '', (string)$_GET['queue_id']) : '';

$command = 'su -s /bin/bash cabnet -c "/usr/local/bin/php ' . $cliPath;
if ($queueId !== '') {
    $command .= ' --queue-id=' . $queueId;
}
$command .= ' --write"';

$jsonCommand = 'su -s /bin/bash cabnet -c "/usr/local/bin/php ' . $cliPath;
if ($queueId !== '') {
    $jsonCommand .= ' --queue-id=' . $queueId;
}
$jsonCommand .= ' --json"';

?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>V3 Pre-live Proof Bundle Export</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #0f172a;
            --panel: #111827;
            --panel2: #1f2937;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --ok: #22c55e;
            --warn: #f59e0b;
            --bad: #ef4444;
            --line: rgba(255,255,255,.12);
            --accent: #38bdf8;
        }
        body {
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
            color: var(--text);
            background: linear-gradient(135deg, #0f172a, #111827 55%, #0b1120);
        }
        .wrap {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px;
        }
        .hero, .card {
            background: rgba(17,24,39,.92);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: 0 18px 45px rgba(0,0,0,.28);
        }
        .hero {
            padding: 24px;
            margin-bottom: 18px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        h2 {
            margin: 0 0 14px;
            font-size: 18px;
        }
        p {
            color: var(--muted);
            line-height: 1.5;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .card {
            padding: 18px;
            margin-bottom: 18px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 13px;
            background: rgba(56,189,248,.12);
            color: #bae6fd;
            border: 1px solid rgba(56,189,248,.25);
            margin: 4px 6px 4px 0;
        }
        .badge.ok { background: rgba(34,197,94,.12); color: #bbf7d0; border-color: rgba(34,197,94,.28); }
        .badge.warn { background: rgba(245,158,11,.12); color: #fde68a; border-color: rgba(245,158,11,.28); }
        code, pre {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }
        pre {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            background: #020617;
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            color: #d1d5db;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            overflow: hidden;
            border-radius: 12px;
        }
        th, td {
            padding: 10px 8px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }
        th {
            color: #cbd5e1;
            background: rgba(255,255,255,.04);
        }
        td {
            color: #e5e7eb;
        }
        .muted { color: var(--muted); }
        .small { font-size: 13px; }
        .path {
            overflow-wrap: anywhere;
            color: #bae6fd;
        }
        @media (max-width: 860px) {
            .grid { grid-template-columns: 1fr; }
            .wrap { padding: 14px; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <section class="hero">
        <h1>V3 Pre-live Proof Bundle Export</h1>
        <p>Read-only Ops view for the server-side proof bundle exporter. This page does not run commands and does not write files.</p>
        <span class="badge ok">No Bolt call</span>
        <span class="badge ok">No EDXEIX call</span>
        <span class="badge ok">No AADE call</span>
        <span class="badge ok">No DB writes</span>
        <span class="badge ok">V0 untouched</span>
    </section>

    <div class="grid">
        <section class="card">
            <h2>CLI status</h2>
            <table>
                <tr><th>CLI file</th><td class="path"><?= h($cliPath) ?></td></tr>
                <tr><th>Exists</th><td><?= is_file($cliPath) ? 'yes' : 'no' ?></td></tr>
                <tr><th>Readable</th><td><?= is_readable($cliPath) ? 'yes' : 'no' ?></td></tr>
                <tr><th>Artifact dir</th><td class="path"><?= h($artifactDir) ?></td></tr>
                <tr><th>Dir exists</th><td><?= is_dir($artifactDir) ? 'yes' : 'no' ?></td></tr>
                <tr><th>Dir writable</th><td><?= is_writable($artifactDir) ? 'yes' : 'no' ?></td></tr>
            </table>
        </section>

        <section class="card">
            <h2>Run from terminal</h2>
            <p class="small muted">Creates only local server-side proof artifacts under the private app storage directory.</p>
            <pre><?= h($command) ?></pre>
            <p class="small muted">Preview JSON only:</p>
            <pre><?= h($jsonCommand) ?></pre>
        </section>
    </div>

    <section class="card">
        <h2>Latest proof bundle artifacts</h2>
        <?php if ($latestFiles === []): ?>
            <p>No proof bundle artifacts found yet.</p>
        <?php else: ?>
            <table>
                <thead>
                <tr>
                    <th>File</th>
                    <th>Size</th>
                    <th>Modified</th>
                    <th>Readable</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($latestFiles as $file): ?>
                    <tr>
                        <td class="path"><?= h($file['name']) ?><br><span class="small muted"><?= h($file['path']) ?></span></td>
                        <td><?= h((string)$file['size']) ?></td>
                        <td><?= h($file['mtime']) ?></td>
                        <td><?= !empty($file['readable']) ? 'yes' : 'no' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Expected result</h2>
        <p>The proof bundle should show the adapter simulation is safe, the future adapter remains non-live-capable, submitted is false, and live submission remains blocked by the master gate/adapter controls.</p>
    </section>
</div>
</body>
</html>
