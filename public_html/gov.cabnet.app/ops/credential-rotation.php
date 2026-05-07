<?php
/**
 * gov.cabnet.app — v4.8 Credential Rotation Gate
 *
 * Read-only credential rotation checklist/status page.
 *
 * Safety contract:
 * - Does not rotate credentials.
 * - Does not display secrets.
 * - Does not write files.
 * - Does not import mail, send email, create bookings/evidence/jobs/attempts.
 * - Does not call Bolt or EDXEIX.
 * - Does not enable live submission.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
$markerFile = dirname(__DIR__, 3) . '/gov.cabnet.app_app/storage/security/credential_rotation_ack.json';

function cr_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cr_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true);
}

function cr_authorized($config): bool
{
    $expected = (string)$config->get('app.internal_api_key', '');
    if ($expected === '' || str_starts_with($expected, 'REPLACE_WITH_')) {
        return false;
    }
    $provided = (string)($_GET['key'] ?? $_POST['key'] ?? ($_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? ''));
    return $provided !== '' && hash_equals($expected, $provided);
}

function cr_json_response(array $payload, int $statusCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('X-Robots-Tag: noindex,nofollow', true);
    }
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

function cr_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . cr_h($type) . '">' . cr_h($text) . '</span>';
}

function cr_bool_badge(bool $ok, string $yes = 'PASS', string $no = 'PENDING'): string
{
    return cr_badge($ok ? $yes : $no, $ok ? 'good' : 'warn');
}

function cr_current_key_query(array $extra = []): string
{
    $params = $extra;
    $key = (string)($_GET['key'] ?? '');
    if ($key !== '') {
        $params = array_merge(['key' => $key], $params);
    }
    return $params ? ('?' . http_build_query($params)) : '';
}

function cr_read_marker(string $markerFile): array
{
    $required = ['ops_key', 'bolt_credentials', 'edxeix_credentials', 'mailbox_credentials'];
    $out = [
        'marker_file' => $markerFile,
        'exists' => false,
        'readable' => false,
        'complete' => false,
        'completed_at' => null,
        'completed_by' => null,
        'items' => [
            'ops_key' => false,
            'bolt_credentials' => false,
            'edxeix_credentials' => false,
            'mailbox_credentials' => false,
        ],
        'notes' => '',
        'error' => null,
    ];

    if (!is_file($markerFile)) {
        return $out;
    }

    $out['exists'] = true;
    $out['readable'] = is_readable($markerFile);
    if (!$out['readable']) {
        $out['error'] = 'marker_not_readable';
        return $out;
    }

    $raw = file_get_contents($markerFile);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) {
        $out['error'] = 'invalid_marker_json';
        return $out;
    }

    $items = is_array($data['items'] ?? null) ? $data['items'] : [];
    foreach ($required as $key) {
        $out['items'][$key] = !empty($items[$key]);
    }

    $complete = true;
    foreach ($required as $key) {
        if (!$out['items'][$key]) {
            $complete = false;
            break;
        }
    }

    $out['complete'] = $complete;
    $out['completed_at'] = isset($data['completed_at']) ? (string)$data['completed_at'] : null;
    $out['completed_by'] = isset($data['completed_by']) ? (string)$data['completed_by'] : null;
    $out['notes'] = isset($data['notes']) ? (string)$data['notes'] : '';

    return $out;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$error = null;
$payload = [];

try {
    if (!is_file($bootstrap) || !is_readable($bootstrap)) {
        throw new RuntimeException('Missing bootstrap file: ' . $bootstrap);
    }

    $app = require $bootstrap;
    $config = $app['config'];

    if (!cr_authorized($config)) {
        if ($format === 'json') {
            cr_json_response(['ok' => false, 'error' => 'forbidden'], 403);
        }
        http_response_code(403);
        echo '<!doctype html><meta charset="utf-8"><title>Forbidden</title><h1>Forbidden</h1><p>Missing or invalid internal key.</p>';
        exit;
    }

    $marker = cr_read_marker($markerFile);
    $dryRun = cr_bool($config->get('app.dry_run', false));
    $liveSubmitEnabled = cr_bool($config->get('edxeix.live_submit_enabled', false));
    $driverNotifications = $config->get('mail.driver_notifications', []);

    $payload = [
        'ok' => true,
        'script' => 'ops/credential-rotation.php',
        'generated_at' => date('c'),
        'verdict' => !empty($marker['complete']) ? 'CREDENTIAL_ROTATION_ACKNOWLEDGED' : 'CREDENTIAL_ROTATION_PENDING',
        'safety_contract' => [
            'read_only' => true,
            'displays_secrets' => false,
            'writes_files' => false,
            'imports_mail' => false,
            'sends_driver_email' => false,
            'creates_normalized_bookings' => false,
            'creates_dry_run_evidence' => false,
            'creates_submission_jobs' => false,
            'creates_submission_attempts' => false,
            'calls_bolt' => false,
            'calls_edxeix' => false,
            'live_edxeix_submission' => false,
        ],
        'config_posture' => [
            'timezone' => (string)$config->get('app.timezone', date_default_timezone_get()),
            'dry_run' => $dryRun,
            'live_submit_enabled' => $liveSubmitEnabled,
            'future_start_guard_minutes' => (int)$config->get('edxeix.future_start_guard_minutes', 0),
            'driver_notifications_enabled' => is_array($driverNotifications) && cr_bool($driverNotifications['enabled'] ?? false),
            'resolve_from_bolt_driver_directory' => is_array($driverNotifications) && cr_bool($driverNotifications['resolve_from_bolt_driver_directory'] ?? false),
            'internal_api_key_configured' => (string)$config->get('app.internal_api_key', '') !== '',
            'bolt_config_present' => $config->get('bolt', null) !== null,
            'edxeix_config_present' => $config->get('edxeix', null) !== null,
            'mail_config_present' => $config->get('mail', null) !== null,
        ],
        'credential_rotation' => $marker,
        'manual_items' => [
            'ops_key' => 'Rotate app.internal_api_key / INTERNAL_API_KEY and update saved dashboard URLs/bookmarks.',
            'bolt_credentials' => 'Rotate exposed Bolt API credentials or tokens, then verify sync_bolt_driver_directory.php still succeeds.',
            'edxeix_credentials' => 'Rotate EDXEIX credentials/session material before any live-submit phase.',
            'mailbox_credentials' => 'Rotate mailbox or forwarding-related credentials if exposed during troubleshooting.',
        ],
        'acknowledgement_command' => '/usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/mark_credential_rotation.php --ops-key --bolt --edxeix --mailbox --by=Andreas',
    ];
} catch (Throwable $e) {
    $error = $e->getMessage();
    $payload = [
        'ok' => false,
        'script' => 'ops/credential-rotation.php',
        'generated_at' => date('c'),
        'error' => $error,
    ];
}

if ($format === 'json') {
    cr_json_response($payload, !empty($payload['ok']) ? 200 : 500);
}

$marker = $payload['credential_rotation'] ?? [];
$configPosture = $payload['config_posture'] ?? [];
$items = is_array($marker['items'] ?? null) ? $marker['items'] : [];
$verdict = (string)($payload['verdict'] ?? 'ERROR');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Credential Rotation | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#465f86;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#087a4d;--orange:#b85c00;--red:#b42318;--purple:#6046a8;--soft:#f8fbff}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.top{background:var(--nav);color:#fff;min-height:58px;display:flex;gap:18px;align-items:center;padding:0 24px;overflow:auto;position:sticky;top:0;z-index:10}.top strong{white-space:nowrap}.top a{color:#fff;text-decoration:none;white-space:nowrap;font-size:14px;opacity:.92}.top a:hover{opacity:1;text-decoration:underline}.wrap{width:min(1200px,calc(100% - 42px));margin:24px auto 60px}.card{background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:18px;margin-bottom:18px;box-shadow:0 10px 28px rgba(8,18,37,.04)}.hero{border-left:8px solid var(--orange)}.hero.good{border-left-color:var(--green)}.hero.bad{border-left-color:var(--red)}h1{margin:0 0 10px;font-size:32px}h2{margin:0 0 14px;font-size:22px}p{color:var(--muted);line-height:1.45}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.two{display:grid;grid-template-columns:1fr 1fr;gap:18px}.metric{border:1px solid var(--line);border-radius:11px;background:var(--soft);padding:14px;min-height:78px}.metric strong{display:block;font-size:24px;line-height:1.05}.metric span{display:block;color:var(--muted);font-size:13px;margin-top:6px}.badge{display:inline-block;padding:5px 9px;border-radius:999px;font-size:12px;font-weight:800;margin:1px 4px 1px 0;white-space:nowrap}.badge-good{background:#dcfce7;color:#166534}.badge-warn{background:#fff7ed;color:#b45309}.badge-bad{background:#fee2e2;color:#991b1b}.badge-neutral{background:#eaf1ff;color:#1e40af}.badge-purple{background:#ede9fe;color:#5b21b6}.kv{display:grid;grid-template-columns:minmax(210px,36%) 1fr;border:1px solid var(--line);border-radius:10px;overflow:hidden}.kv div{padding:10px 12px;border-bottom:1px solid var(--line)}.kv div:nth-last-child(-n+2){border-bottom:none}.kv .k{background:#f8fbff;color:var(--muted);font-weight:700}.check{border:1px solid var(--line);border-radius:12px;padding:13px;background:#fff;margin-bottom:10px;border-left:6px solid var(--orange)}.check.good{border-left-color:var(--green)}.check.bad{border-left-color:var(--red)}.small{font-size:13px;color:var(--muted)}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.btn{display:inline-block;background:var(--blue);color:#fff;text-decoration:none;padding:10px 14px;border-radius:8px;font-weight:800;font-size:14px}.btn.dark{background:#334155}.btn.good{background:var(--green)}.btn.warn{background:var(--orange)}pre{background:#081225;color:#dbeafe;border-radius:10px;padding:14px;overflow:auto;white-space:pre-wrap}.safety{background:#ecfdf3;border:1px solid #bbf7d0;border-left:7px solid var(--green);border-radius:14px;padding:16px;margin-bottom:18px}.safety strong{color:#166534}@media(max-width:900px){.grid,.two{grid-template-columns:1fr}.wrap{width:calc(100% - 24px)}.kv{grid-template-columns:1fr}}
    </style>
</head>
<body>
<nav class="top">
    <strong>gov.cabnet.app</strong>
    <a href="/ops/launch-readiness.php<?= cr_h(cr_current_key_query()) ?>">Launch Readiness</a>
    <a href="/ops/credential-rotation.php<?= cr_h(cr_current_key_query()) ?>">Credential Rotation</a>
    <a href="/ops/mail-status.php<?= cr_h(cr_current_key_query()) ?>">Mail Status</a>
    <a href="/ops/mail-driver-notifications.php<?= cr_h(cr_current_key_query()) ?>">Driver Notifications</a>
    <a href="/ops/mail-dry-run-evidence.php<?= cr_h(cr_current_key_query()) ?>">Dry-run Evidence</a>
</nav>
<main class="wrap">
    <section class="safety">
        <strong>NO SECRETS ARE DISPLAYED OR STORED.</strong>
        This page is read-only and only checks whether a server-side acknowledgement marker exists after manual credential rotation.
    </section>

    <section class="card hero <?= !empty($marker['complete']) ? 'good' : 'warn' ?>">
        <h1>v4.8 Credential Rotation Gate</h1>
        <p>Before any live EDXEIX submit design, rotate exposed credentials and record a no-secret acknowledgement marker.</p>
        <?php if ($error): ?><p><strong>Error:</strong> <?= cr_h($error) ?></p><?php endif; ?>
        <?= cr_badge($verdict, !empty($marker['complete']) ? 'good' : 'warn') ?>
        <?= cr_badge('LIVE SUBMIT OFF', 'good') ?>
        <?= cr_badge('READ ONLY', 'good') ?>
        <div class="grid" style="margin-top:14px">
            <div class="metric"><strong><?= !empty($marker['complete']) ? 'YES' : 'NO' ?></strong><span>rotation acknowledged</span></div>
            <div class="metric"><strong><?= cr_h((string)($marker['completed_at'] ?? 'pending')) ?></strong><span>completed at</span></div>
            <div class="metric"><strong><?= !empty($configPosture['dry_run']) ? 'true' : 'false' ?></strong><span>app.dry_run</span></div>
            <div class="metric"><strong><?= !empty($configPosture['live_submit_enabled']) ? 'true' : 'false' ?></strong><span>live_submit_enabled</span></div>
        </div>
        <div class="actions">
            <a class="btn good" href="/ops/credential-rotation.php<?= cr_h(cr_current_key_query(['format' => 'json'])) ?>">Open JSON</a>
            <a class="btn" href="/ops/launch-readiness.php<?= cr_h(cr_current_key_query()) ?>">Launch Readiness</a>
        </div>
    </section>

    <section class="two">
        <div class="card">
            <h2>Rotation acknowledgement</h2>
            <div class="kv">
                <div class="k">Marker file</div><div><?= cr_h((string)($marker['marker_file'] ?? '')) ?></div>
                <div class="k">Marker exists</div><div><?= cr_bool_badge(!empty($marker['exists'])) ?></div>
                <div class="k">Marker readable</div><div><?= cr_bool_badge(!empty($marker['readable'])) ?></div>
                <div class="k">Complete</div><div><?= cr_bool_badge(!empty($marker['complete'])) ?></div>
                <div class="k">Completed by</div><div><?= cr_h((string)($marker['completed_by'] ?? '')) ?></div>
                <div class="k">Notes</div><div><?= cr_h((string)($marker['notes'] ?? '')) ?></div>
            </div>
        </div>
        <div class="card">
            <h2>Current config posture</h2>
            <div class="kv">
                <div class="k">Dry-run mode</div><div><?= cr_bool_badge(!empty($configPosture['dry_run']), 'true', 'false') ?></div>
                <div class="k">Live submit enabled</div><div><?= cr_bool_badge(empty($configPosture['live_submit_enabled']), 'false', 'true') ?></div>
                <div class="k">Future guard minutes</div><div><?= cr_h((string)($configPosture['future_start_guard_minutes'] ?? '')) ?></div>
                <div class="k">Driver notifications</div><div><?= cr_bool_badge(!empty($configPosture['driver_notifications_enabled']), 'enabled', 'disabled') ?></div>
                <div class="k">Driver directory resolver</div><div><?= cr_bool_badge(!empty($configPosture['resolve_from_bolt_driver_directory']), 'enabled', 'disabled') ?></div>
            </div>
        </div>
    </section>

    <section class="card">
        <h2>Required manual rotation items</h2>
        <div class="check <?= !empty($items['ops_key']) ? 'good' : 'bad' ?>"><strong>Ops/internal API key</strong><?= cr_bool_badge(!empty($items['ops_key'])) ?><div class="small">Rotate the key used by ops dashboard URLs and update bookmarks.</div></div>
        <div class="check <?= !empty($items['bolt_credentials']) ? 'good' : 'bad' ?>"><strong>Bolt credentials</strong><?= cr_bool_badge(!empty($items['bolt_credentials'])) ?><div class="small">Rotate exposed Bolt API credentials/tokens and re-run driver sync.</div></div>
        <div class="check <?= !empty($items['edxeix_credentials']) ? 'good' : 'bad' ?>"><strong>EDXEIX credentials/session</strong><?= cr_bool_badge(!empty($items['edxeix_credentials'])) ?><div class="small">Rotate before any live-submit design. Do not paste credentials in chat.</div></div>
        <div class="check <?= !empty($items['mailbox_credentials']) ? 'good' : 'bad' ?>"><strong>Mailbox/forwarding credentials</strong><?= cr_bool_badge(!empty($items['mailbox_credentials'])) ?><div class="small">Rotate mailbox or forwarding credentials if exposed.</div></div>
    </section>

    <section class="card">
        <h2>Acknowledge after rotation</h2>
        <p>Run this only after the real credentials have been rotated. The marker stores no secrets.</p>
        <pre><?= cr_h((string)($payload['acknowledgement_command'] ?? '')) ?></pre>
        <p class="small">Then refresh Launch Readiness. The credential gate should change from pending to acknowledged.</p>
    </section>
</main>
</body>
</html>
