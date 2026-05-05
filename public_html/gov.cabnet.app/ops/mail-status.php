<?php
/**
 * gov.cabnet.app — Bolt Mail Status Dashboard v3.8
 *
 * Read-only operational monitor for the Bolt pre-ride email intake layer.
 *
 * Safety contract:
 * - Does not scan the mailbox.
 * - Does not import emails.
 * - Does not create normalized bookings.
 * - Does not create submission jobs.
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not submit anything live.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

function ms_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ms_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . ms_h($type) . '">' . ms_h($text) . '</span>';
}

function ms_mask_phone(?string $phone): string
{
    $phone = trim((string)$phone);
    if ($phone === '') {
        return '';
    }

    $digits = preg_replace('/\D+/', '', $phone) ?: '';
    if (strlen($digits) <= 4) {
        return '••••';
    }

    return '+' . substr($digits, 0, 4) . '••••' . substr($digits, -4);
}

function ms_load_config(): array
{
    $file = '/home/cabnet/gov.cabnet.app_config/config.php';
    if (!is_file($file) || !is_readable($file)) {
        throw new RuntimeException('Missing config file.');
    }

    $config = require $file;
    if (!is_array($config)) {
        throw new RuntimeException('Config file did not return an array.');
    }

    return $config;
}

function ms_config_get(array $config, string $key, $default = null)
{
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}

function ms_require_key(array $config): void
{
    $expected = (string)ms_config_get($config, 'app.internal_api_key', '');
    $provided = (string)($_GET['key'] ?? '');

    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title><h1>Forbidden</h1><p>Missing or invalid internal key.</p>';
        exit;
    }
}

function ms_db(array $config): mysqli
{
    $dbConfig = is_array($config['db'] ?? null) ? $config['db'] : (is_array($config['database'] ?? null) ? $config['database'] : []);

    $host = (string)($dbConfig['host'] ?? 'localhost');
    $port = (int)($dbConfig['port'] ?? 3306);
    $name = (string)($dbConfig['database'] ?? $dbConfig['name'] ?? '');
    $user = (string)($dbConfig['username'] ?? $dbConfig['user'] ?? '');
    $pass = (string)($dbConfig['password'] ?? $dbConfig['pass'] ?? '');
    $charset = (string)($dbConfig['charset'] ?? 'utf8mb4');

    if ($name === '' || $user === '') {
        throw new RuntimeException('Database name or username is missing from config.');
    }

    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $mysqli = new mysqli($host, $user, $pass, $name, $port);
    $mysqli->set_charset($charset);
    return $mysqli;
}

function ms_fetch_all(mysqli $db, string $sql, array $params = []): array
{
    $stmt = $db->prepare($sql);
    if ($params) {
        $types = str_repeat('s', count($params));
        $values = array_map(static fn($v) => $v === null ? null : (string)$v, $params);
        $refs = [];
        foreach ($values as $k => $v) {
            $refs[$k] = &$values[$k];
        }
        $stmt->bind_param($types, ...$refs);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = $row;
    }
    return $rows;
}

function ms_fetch_one(mysqli $db, string $sql, array $params = []): ?array
{
    $rows = ms_fetch_all($db, $sql, $params);
    return $rows[0] ?? null;
}

function ms_table_exists(mysqli $db, string $table): bool
{
    $row = ms_fetch_one(
        $db,
        'SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$table]
    );
    return ((int)($row['c'] ?? 0)) > 0;
}

function ms_count_mail_files(string $maildir, string $folder): int
{
    $dir = rtrim($maildir, '/') . '/' . $folder;
    if (!is_dir($dir) || !is_readable($dir)) {
        return 0;
    }

    $count = 0;
    $items = scandir($dir);
    if (!is_array($items)) {
        return 0;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        if (is_file($dir . '/' . $item)) {
            $count++;
        }
    }

    return $count;
}

function ms_tail_lines(string $file, int $limit = 40): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }

    return array_slice($lines, -$limit);
}

function ms_current_url_key(): string
{
    $key = (string)($_GET['key'] ?? '');
    return $key !== '' ? ('?key=' . rawurlencode($key)) : '';
}

$config = [];
$error = null;
$db = null;
$stats = [];
$recent = [];
$maildir = '/home/cabnet/mail/gov.cabnet.app/bolt-bridge';
$logFile = '/home/cabnet/gov.cabnet.app_app/storage/logs/bolt_mail_intake.log';
$newCount = 0;
$curCount = 0;
$logTail = [];
$normalizedMailRows = 0;

try {
    $config = ms_load_config();
    if (ms_config_get($config, 'app.timezone')) {
        date_default_timezone_set((string)ms_config_get($config, 'app.timezone'));
    }
    ms_require_key($config);

    $maildir = (string)ms_config_get($config, 'mail.bolt_bridge_maildir', $maildir);
    $db = ms_db($config);

    if (!ms_table_exists($db, 'bolt_mail_intake')) {
        throw new RuntimeException('bolt_mail_intake table is missing.');
    }

    $stats = ms_fetch_all(
        $db,
        "SELECT parse_status, safety_status, COUNT(*) AS c
         FROM bolt_mail_intake
         GROUP BY parse_status, safety_status
         ORDER BY safety_status, parse_status"
    );

    $recent = ms_fetch_all(
        $db,
        "SELECT id, customer_name, customer_mobile, driver_name, vehicle_plate, pickup_address, dropoff_address,
                parsed_pickup_at, parse_status, safety_status, linked_booking_id, created_at, updated_at
         FROM bolt_mail_intake
         ORDER BY id DESC
         LIMIT 20"
    );

    if (ms_table_exists($db, 'normalized_bookings')) {
        $row = ms_fetch_one($db, "SELECT COUNT(*) AS c FROM normalized_bookings WHERE source = 'bolt_mail'");
        $normalizedMailRows = (int)($row['c'] ?? 0);
    }

    $newCount = ms_count_mail_files($maildir, 'new');
    $curCount = ms_count_mail_files($maildir, 'cur');
    $logTail = ms_tail_lines($logFile, 60);
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$total = 0;
$future = 0;
$blockedPast = 0;
$blockedTooSoon = 0;
$needsReview = 0;
$rejected = 0;
foreach ($stats as $row) {
    $count = (int)($row['c'] ?? 0);
    $total += $count;
    $safety = (string)($row['safety_status'] ?? '');
    $parse = (string)($row['parse_status'] ?? '');
    if ($safety === 'future_candidate') {
        $future += $count;
    } elseif ($safety === 'blocked_past') {
        $blockedPast += $count;
    } elseif ($safety === 'blocked_too_soon') {
        $blockedTooSoon += $count;
    }
    if ($safety === 'needs_review' || $parse === 'needs_review') {
        $needsReview += $count;
    }
    if ($parse === 'rejected') {
        $rejected += $count;
    }
}

$keyQuery = ms_current_url_key();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bolt Mail Status | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --panel:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; --soft:#f8fbff; }
        *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.nav{background:var(--nav);color:#fff;min-height:56px;display:flex;align-items:center;gap:18px;padding:0 26px;position:sticky;top:0;z-index:5;overflow:auto}.nav strong{white-space:nowrap}.nav a{color:#fff;text-decoration:none;font-size:15px;white-space:nowrap;opacity:.92}.nav a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1480px,calc(100% - 48px));margin:26px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 26px rgba(8,18,37,.04)}.hero{border-left:7px solid var(--green)}.hero.warn{border-left-color:var(--orange)}.hero.bad{border-left-color:var(--red)}h1{font-size:34px;margin:0 0 12px}h2{font-size:23px;margin:0 0 14px}p{color:var(--muted);line-height:1.45}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:700;margin:1px 3px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px;margin-top:14px}.metric{border:1px solid var(--line);border-radius:10px;padding:14px;background:var(--soft);min-height:82px}.metric strong{display:block;font-size:30px;line-height:1.05;word-break:break-word}.metric span{color:var(--muted);font-size:14px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}.btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none;font-weight:700;background:var(--blue);font-size:14px}.btn.dark{background:var(--slate)}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}table{width:100%;border-collapse:collapse;background:#fff}th,td{border:1px solid var(--line);padding:10px;text-align:left;vertical-align:top;font-size:14px}th{background:#f8fbff}code{background:#eef2ff;padding:2px 5px;border-radius:5px}.log{background:#081225;color:#e5edf8;border-radius:10px;padding:14px;overflow:auto;font-family:Consolas,Monaco,monospace;font-size:13px;white-space:pre-wrap;max-height:420px}.small{font-size:13px;color:var(--muted)}.badline{color:#991b1b}.goodline{color:#166534}.warnline{color:#b45309}@media(max-width:1150px){.grid{grid-template-columns:repeat(2,minmax(0,1fr))}.two{grid-template-columns:1fr}}@media(max-width:720px){.grid{grid-template-columns:1fr}.wrap{width:calc(100% - 24px);margin-top:14px}.nav{padding:0 14px}}
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/home.php">Ops Home</a>
    <a href="/ops/mail-status.php<?= ms_h($keyQuery) ?>">Mail Status</a>
    <a href="/ops/mail-intake.php<?= ms_h($keyQuery) ?>">Mail Intake</a>
    <a href="/ops/mail-preflight.php<?= ms_h($keyQuery) ?>">Mail Preflight</a>
    <a href="/ops/preflight-review.php">Preflight Review</a>
    <a href="/ops/index.php">Guided Console</a>
</nav>

<main class="wrap">
    <section class="card hero <?= $error ? 'bad' : ($future > 0 ? 'warn' : '') ?>">
        <h1>Bolt Mail Status</h1>
        <p>Read-only monitor for the <code>bolt-bridge@gov.cabnet.app</code> Maildir intake layer. This page does not scan, import, stage jobs, call Bolt, call EDXEIX, or submit live.</p>
        <div>
            <?= ms_badge('READ ONLY', 'good') ?>
            <?= ms_badge('MAIL INTAKE MONITOR', 'good') ?>
            <?= ms_badge('LIVE SUBMIT OFF', 'good') ?>
            <?= ms_badge('PII MASKED IN UI', 'good') ?>
        </div>
        <?php if ($error): ?>
            <p class="badline"><strong>Error:</strong> <?= ms_h($error) ?></p>
        <?php endif; ?>

        <div class="grid">
            <div class="metric"><strong><?= ms_h((string)$total) ?></strong><span>Total intake rows</span></div>
            <div class="metric"><strong><?= ms_h((string)$future) ?></strong><span>Future candidates</span></div>
            <div class="metric"><strong><?= ms_h((string)$blockedPast) ?></strong><span>Blocked past</span></div>
            <div class="metric"><strong><?= ms_h((string)$blockedTooSoon) ?></strong><span>Blocked too soon</span></div>
            <div class="metric"><strong><?= ms_h((string)$needsReview) ?></strong><span>Needs review</span></div>
            <div class="metric"><strong><?= ms_h((string)$normalizedMailRows) ?></strong><span>Mail-created bookings</span></div>
        </div>
        <div class="actions">
            <a class="btn good" href="/ops/mail-intake.php<?= ms_h($keyQuery) ?>">Open Mail Intake</a>
            <a class="btn warn" href="/ops/mail-preflight.php<?= ms_h($keyQuery) ?>">Open Mail Preflight</a>
            <a class="btn dark" href="/bolt_edxeix_preflight.php?limit=30">Raw Preflight JSON</a>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Mailbox and cron</h2>
            <p><strong>Maildir:</strong> <code><?= ms_h($maildir) ?></code></p>
            <p><strong>Log:</strong> <code><?= ms_h($logFile) ?></code></p>
            <div class="grid" style="grid-template-columns:repeat(2,minmax(0,1fr))">
                <div class="metric"><strong><?= ms_h((string)$newCount) ?></strong><span>Maildir new/ files</span></div>
                <div class="metric"><strong><?= ms_h((string)$curCount) ?></strong><span>Maildir cur/ files</span></div>
            </div>
        </div>
        <div class="card">
            <h2>Production rule</h2>
            <p class="goodline"><strong>Current expected state:</strong> cron imports emails only. The preflight bridge can create local normalized booking rows only after manual approval of a valid <code>future_candidate</code>.</p>
            <p class="badline"><strong>Still blocked:</strong> past rows, too-soon rows, rejected rows, automatic EDXEIX jobs, and live EDXEIX POST.</p>
        </div>
    </section>

    <section class="card">
        <h2>Status breakdown</h2>
        <table>
            <thead><tr><th>Parse status</th><th>Safety status</th><th>Count</th></tr></thead>
            <tbody>
            <?php if (!$stats): ?>
                <tr><td colspan="3">No rows found.</td></tr>
            <?php else: foreach ($stats as $row): ?>
                <tr>
                    <td><?= ms_badge((string)$row['parse_status'], ((string)$row['parse_status'] === 'parsed') ? 'good' : 'warn') ?></td>
                    <td><?= ms_badge((string)$row['safety_status'], ((string)$row['safety_status'] === 'future_candidate') ? 'warn' : (((string)$row['safety_status'] === 'blocked_past') ? 'bad' : 'neutral')) ?></td>
                    <td><?= ms_h((string)$row['c']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Recent intake rows</h2>
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Pickup</th>
                <th>Customer</th>
                <th>Driver / Vehicle</th>
                <th>Route</th>
                <th>Status</th>
                <th>Linked booking</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$recent): ?>
                <tr><td colspan="7">No recent intake rows.</td></tr>
            <?php else: foreach ($recent as $row): ?>
                <tr>
                    <td><?= ms_h((string)$row['id']) ?><br><span class="small"><?= ms_h((string)$row['created_at']) ?></span></td>
                    <td><?= ms_h((string)$row['parsed_pickup_at']) ?></td>
                    <td><?= ms_h((string)$row['customer_name']) ?><br><span class="small"><?= ms_h(ms_mask_phone($row['customer_mobile'] ?? '')) ?></span></td>
                    <td><?= ms_h((string)$row['driver_name']) ?><br><strong><?= ms_h((string)$row['vehicle_plate']) ?></strong></td>
                    <td><strong>From:</strong> <?= ms_h((string)$row['pickup_address']) ?><br><strong>To:</strong> <?= ms_h((string)$row['dropoff_address']) ?></td>
                    <td>
                        <?= ms_badge((string)$row['parse_status'], ((string)$row['parse_status'] === 'parsed') ? 'good' : 'warn') ?>
                        <?= ms_badge((string)$row['safety_status'], ((string)$row['safety_status'] === 'future_candidate') ? 'warn' : (((string)$row['safety_status'] === 'blocked_past') ? 'bad' : 'neutral')) ?>
                    </td>
                    <td><?= $row['linked_booking_id'] ? ('#' . ms_h((string)$row['linked_booking_id'])) : '—' ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Recent cron log</h2>
        <?php if (!$logTail): ?>
            <p>No log lines found yet.</p>
        <?php else: ?>
            <div class="log"><?= ms_h(implode("\n", $logTail)) ?></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
