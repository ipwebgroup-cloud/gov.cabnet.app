<?php
/**
 * gov.cabnet.app — Authenticated Firefox helper download page.
 *
 * This does not install the extension in Firefox. It only packages the current
 * server-side helper files for staff download.
 */

declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow', true);

function fx_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$extensionDir = '/home/cabnet/tools/firefox-edxeix-autofill-helper';
$allowedFiles = ['manifest.json', 'edxeix-fill.js', 'gov-capture.js', 'README.md'];
$missing = [];
foreach ($allowedFiles as $file) {
    if (!is_file($extensionDir . '/' . $file)) {
        $missing[] = $file;
    }
}

if (($_GET['download'] ?? '') === 'zip') {
    if (!class_exists(ZipArchive::class)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'ZipArchive is not available on this PHP installation.';
        exit;
    }
    if ($missing !== []) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Extension files missing: ' . implode(', ', $missing);
        exit;
    }

    $tmp = tempnam(sys_get_temp_dir(), 'gov-edxeix-extension-');
    if ($tmp === false) {
        http_response_code(500);
        echo 'Unable to create temporary zip file.';
        exit;
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp);
        http_response_code(500);
        echo 'Unable to open temporary zip file.';
        exit;
    }

    foreach ($allowedFiles as $file) {
        $zip->addFile($extensionDir . '/' . $file, $file);
    }
    $zip->close();

    $name = 'firefox-edxeix-autofill-helper-' . date('Ymd-His') . '.zip';
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . (string)filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Firefox EDXEIX Helper | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--orange:#b85c00;--red:#b42318;--soft:#f8fbff;--gold:#d4922d}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.wrap{width:min(980px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}h1{font-size:32px;margin:0 0 12px}h2{font-size:22px;margin:0 0 12px}p,li{color:var(--muted);line-height:1.5}.hero{border-left:7px solid var(--gold)}.btn{display:inline-block;border:0;padding:12px 15px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px;cursor:pointer}.btn.green{background:var(--green)}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.good{background:#dcfce7;color:#166534}.warn{background:#fff7ed;color:#b45309}.bad{background:#fee2e2;color:#991b1b}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.path{font-family:Consolas,Menlo,monospace;background:#0b1220;color:#dbeafe;border-radius:10px;padding:12px;overflow:auto}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.notice{background:#fff7ed;border:1px solid #fed7aa;border-radius:10px;padding:12px;color:#9a3412}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/pre-ride-email-tool.php">Pre-Ride Email Tool</a>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/firefox-extension.php">Firefox Helper</a>
    <a href="/ops/logout.php">Logout</a>
</nav>
<main class="wrap">
    <section class="card hero">
        <h1>Firefox EDXEIX Autofill Helper</h1>
        <p>Download the current helper package from the server. Firefox still requires local temporary loading unless the extension is signed as an XPI or deployed by enterprise policy.</p>
        <div>
            <span class="badge good">AUTHENTICATED DOWNLOAD</span>
            <span class="badge warn">NO COOKIES OR TOKENS INCLUDED</span>
            <span class="badge warn">NOT A LIVE SUBMIT CHANGE</span>
        </div>
        <div class="actions">
            <a class="btn green" href="/ops/firefox-extension.php?download=zip">Download current helper ZIP</a>
        </div>
    </section>

    <section class="card">
        <h2>Server source folder</h2>
        <div class="path"><?= fx_h($extensionDir) ?></div>
        <?php if ($missing === []): ?>
            <p><span class="badge good">READY</span> All expected helper files are present.</p>
        <?php else: ?>
            <p><span class="badge bad">CHECK</span> Missing files: <?= fx_h(implode(', ', $missing)) ?></p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>How staff should load it today</h2>
        <ol>
            <li>Download the helper ZIP from this page.</li>
            <li>Extract it to a local folder, for example Desktop.</li>
            <li>Open Firefox and go to <code>about:debugging#/runtime/this-firefox</code>.</li>
            <li>Click <strong>Load Temporary Add-on</strong>.</li>
            <li>Select <code>manifest.json</code> inside the extracted helper folder.</li>
        </ol>
        <div class="notice">Temporary loading is for the current browser session. For permanent server-hosted installation, we must package and sign an XPI through Mozilla/AMO, or deploy it through Firefox Enterprise policies.</div>
    </section>
</main>
</body>
</html>
