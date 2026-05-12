<?php
/**
 * gov.cabnet.app — EDXEIX Submit Capture v0.1
 *
 * Sanitized research metadata capture for the future server-side EDXEIX submitter.
 *
 * Safety contract:
 * - No Bolt API calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No workflow queue staging.
 * - No live submission.
 * - Does not store cookies, sessions, CSRF token values, passwords, or credentials.
 * - Stores only sanitized research metadata entered by an authenticated operator.
 * - Production pre-ride tool is not modified by this file.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$shellFile = __DIR__ . '/_shell.php';
if (is_file($shellFile)) {
    require_once $shellFile;
}

function esc_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function esc_badge(string $text, string $type = 'neutral'): string
{
    if (function_exists('opsui_badge')) {
        return opsui_badge($text, $type);
    }
    return '<span class="badge badge-' . esc_h($type) . '">' . esc_h($text) . '</span>';
}

function esc_csrf(): string
{
    if (empty($_SESSION['edxeix_submit_capture_csrf']) || !is_string($_SESSION['edxeix_submit_capture_csrf'])) {
        $_SESSION['edxeix_submit_capture_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['edxeix_submit_capture_csrf'];
}

function esc_validate_csrf(string $token): bool
{
    return isset($_SESSION['edxeix_submit_capture_csrf'])
        && is_string($_SESSION['edxeix_submit_capture_csrf'])
        && hash_equals($_SESSION['edxeix_submit_capture_csrf'], $token);
}

function esc_user(): array
{
    if (function_exists('opsui_current_user')) {
        $u = opsui_current_user();
        return is_array($u) ? $u : [];
    }
    $u = $_SESSION['ops_user'] ?? [];
    return is_array($u) ? $u : [];
}

function esc_is_admin(): bool
{
    if (function_exists('opsui_is_admin')) {
        return opsui_is_admin();
    }
    return strtolower((string)(esc_user()['role'] ?? '')) === 'admin';
}

function esc_bootstrap(?string &$error = null): ?array
{
    static $ctx = null;
    static $loaded = false;
    static $loadError = null;

    if ($loaded) {
        $error = $loadError;
        return is_array($ctx) ? $ctx : null;
    }

    $loaded = true;
    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $loadError = 'Private app bootstrap not found.';
        $error = $loadError;
        return null;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Private app bootstrap returned an invalid context.');
        }
        $error = null;
        return $ctx;
    } catch (Throwable $e) {
        $loadError = $e->getMessage();
        $error = $loadError;
        return null;
    }
}

function esc_db(?string &$error = null): ?mysqli
{
    $ctx = esc_bootstrap($error);
    if (!$ctx) {
        return null;
    }
    try {
        $db = $ctx['db']->connection();
        return $db instanceof mysqli ? $db : null;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        return null;
    }
}

function esc_table_exists(mysqli $db, string $table): bool
{
    try {
        $stmt = $db->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        return (bool)$stmt->get_result()->fetch_assoc();
    } catch (Throwable) {
        return false;
    }
}

function esc_split_names(string $text, int $max = 120): array
{
    $out = [];
    $lines = preg_split('/[\r\n,]+/', $text) ?: [];
    foreach ($lines as $line) {
        $name = trim((string)$line);
        if ($name === '') {
            continue;
        }
        $name = preg_replace('/[^A-Za-z0-9_\-\.\[\]:]+/', '', $name) ?? '';
        $name = substr($name, 0, 190);
        if ($name !== '' && !in_array($name, $out, true)) {
            $out[] = $name;
        }
        if (count($out) >= $max) {
            break;
        }
    }
    return $out;
}

function esc_field_name(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^A-Za-z0-9_\-\.\[\]:]+/', '', $value) ?? '';
    return substr($value, 0, 190);
}

function esc_status(string $value): string
{
    $value = strtolower(trim($value));
    return in_array($value, ['draft', 'candidate', 'validated', 'retired'], true) ? $value : 'draft';
}

function esc_method(string $value): string
{
    $value = strtoupper(trim($value));
    return in_array($value, ['POST', 'GET'], true) ? $value : 'POST';
}

function esc_action_parts(string $url): array
{
    $url = trim($url);
    if ($url === '') {
        return ['', '', 'missing_action_url'];
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return ['', '', 'invalid_action_url'];
    }

    $host = strtolower((string)($parts['host'] ?? ''));
    $path = (string)($parts['path'] ?? '');
    $scheme = strtolower((string)($parts['scheme'] ?? 'https'));

    if ($host === '' && str_starts_with($url, '/')) {
        $host = 'edxeix.yme.gov.gr';
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
    }

    if ($scheme !== 'https' && $host !== '') {
        return [$host, $path, 'action_url_must_be_https'];
    }
    if ($host !== '' && $host !== 'edxeix.yme.gov.gr') {
        return [$host, $path, 'unexpected_action_host'];
    }
    if ($path === '') {
        $path = '/';
    }

    return [substr($host, 0, 190), substr($path, 0, 500), ''];
}

function esc_summary(array $row): array
{
    return [
        'safety' => [
            'stores_cookies' => false,
            'stores_sessions' => false,
            'stores_csrf_token_values' => false,
            'calls_edxeix' => false,
            'live_submit_enabled' => false,
        ],
        'form' => [
            'method' => $row['form_method'] ?? 'POST',
            'action_host' => $row['form_action_host'] ?? '',
            'action_path' => $row['form_action_path'] ?? '',
            'csrf_field_name_present' => ($row['csrf_field_name'] ?? '') !== '',
            'map_lat_field_name_present' => ($row['map_lat_field_name'] ?? '') !== '',
            'map_lng_field_name_present' => ($row['map_lng_field_name'] ?? '') !== '',
        ],
        'counts' => [
            'required_fields' => count($row['required_field_names'] ?? []),
            'select_fields' => count($row['select_field_names'] ?? []),
        ],
    ];
}

function esc_json(array $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function esc_fetch_captures(mysqli $db): array
{
    if (!esc_table_exists($db, 'ops_edxeix_submit_captures')) {
        return [];
    }
    $rows = [];
    try {
        $sql = "SELECT c.*, u.username, u.display_name
                FROM ops_edxeix_submit_captures c
                LEFT JOIN ops_users u ON u.id = c.user_id
                ORDER BY c.id DESC
                LIMIT 30";
        $res = $db->query($sql);
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    } catch (Throwable) {
    }
    return $rows;
}

function esc_audit(mysqli $db, int $userId, string $event, array $meta): void
{
    if ($userId <= 0 || !esc_table_exists($db, 'ops_audit_log')) {
        return;
    }
    try {
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $json = esc_json($meta);
        $stmt = $db->prepare('INSERT INTO ops_audit_log (user_id, event_type, ip_address, user_agent, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('issss', $userId, $event, $ip, $ua, $json);
        $stmt->execute();
    } catch (Throwable) {
    }
}

function esc_shell_begin(): void
{
    if (function_exists('opsui_shell_begin')) {
        opsui_shell_begin([
            'title' => 'EDXEIX Submit Capture',
            'page_title' => 'EDXEIX Submit Capture',
            'active_section' => 'Mobile Submit',
            'breadcrumbs' => 'Αρχική / Mobile Submit / EDXEIX Submit Capture',
            'safe_notice' => 'Sanitized research metadata only. This page does not call EDXEIX and does not store cookies, sessions, CSRF token values, or credentials.',
            'force_safe_notice' => true,
        ]);
        return;
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>EDXEIX Submit Capture | gov.cabnet.app</title><style>body{font-family:Arial,Helvetica,sans-serif;background:#eef1f6;color:#20293a;margin:0;padding:18px}.card{background:#fff;border:1px solid #d8dde7;border-radius:8px;padding:16px;margin:0 0 16px}.badge{display:inline-block;padding:6px 10px;border-radius:12px;background:#e9edf7;margin:2px}.badge-good{background:#dbf0dc;color:#2d7b37}.badge-warn{background:#f8ead3;color:#9a5a00}.badge-bad{background:#f8dedd;color:#b13c35}.btn{display:inline-block;background:#4f5ea7;color:#fff;padding:11px 14px;border-radius:5px;text-decoration:none;border:0;font-weight:700}.btn.warn{background:#d4922d}.btn.dark{background:#6b7280}textarea,input,select{width:100%;box-sizing:border-box;border:1px solid #d8dde7;border-radius:6px;padding:10px}.small{font-size:13px;color:#667085}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.table-wrap{overflow:auto}table{width:100%;border-collapse:collapse}th,td{padding:9px;border-bottom:1px solid #e5e7eb;text-align:left}@media(max-width:860px){.grid{grid-template-columns:1fr}}</style></head><body>';
}

function esc_shell_end(): void
{
    if (function_exists('opsui_shell_end')) {
        opsui_shell_end();
        return;
    }
    echo '</body></html>';
}

$dbError = null;
$db = esc_db($dbError);
$tableReady = $db instanceof mysqli && esc_table_exists($db, 'ops_edxeix_submit_captures');
$message = '';
$error = '';
$csrf = esc_csrf();
$user = esc_user();
$userId = (int)($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$db instanceof mysqli) {
        $error = 'Database connection is unavailable.';
    } elseif (!$tableReady) {
        $error = 'Capture table is missing. Run the Phase 31 SQL migration first.';
    } elseif (!esc_validate_csrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please try again.';
    } else {
        $method = esc_method((string)($_POST['form_method'] ?? 'POST'));
        [$host, $path, $urlWarning] = esc_action_parts((string)($_POST['form_action_url'] ?? ''));
        $status = esc_status((string)($_POST['capture_status'] ?? 'draft'));
        $csrfField = esc_field_name((string)($_POST['csrf_field_name'] ?? ''));
        $mapLat = esc_field_name((string)($_POST['map_lat_field_name'] ?? ''));
        $mapLng = esc_field_name((string)($_POST['map_lng_field_name'] ?? ''));
        $mapAddress = esc_field_name((string)($_POST['map_address_field_name'] ?? ''));
        $required = esc_split_names((string)($_POST['required_field_names'] ?? ''));
        $selects = esc_split_names((string)($_POST['select_field_names'] ?? ''));
        $notes = trim((string)($_POST['notes'] ?? ''));
        $notes = preg_replace('/(cookie|session|password|token|csrf[\s_\-]*value)\s*[:=][^\r\n]+/i', '$1: [removed]', $notes) ?? $notes;
        $notes = substr($notes, 0, 5000);

        if ($urlWarning !== '') {
            $error = 'Action URL warning: ' . $urlWarning . '. Save blocked until the action URL is corrected/sanitized.';
        } elseif ($host === '' || $path === '') {
            $error = 'Action URL/path is required.';
        } else {
            try {
                $summary = esc_summary([
                    'form_method' => $method,
                    'form_action_host' => $host,
                    'form_action_path' => $path,
                    'csrf_field_name' => $csrfField,
                    'map_lat_field_name' => $mapLat,
                    'map_lng_field_name' => $mapLng,
                    'required_field_names' => $required,
                    'select_field_names' => $selects,
                ]);
                $requiredJson = esc_json($required);
                $selectJson = esc_json($selects);
                $summaryJson = esc_json($summary);
                $stmt = $db->prepare("INSERT INTO ops_edxeix_submit_captures
                    (user_id, capture_status, form_method, form_action_host, form_action_path, csrf_field_name, map_lat_field_name, map_lng_field_name, map_address_field_name, required_field_names_json, select_field_names_json, sanitized_summary_json, notes, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param('issssssssssss', $userId, $status, $method, $host, $path, $csrfField, $mapLat, $mapLng, $mapAddress, $requiredJson, $selectJson, $summaryJson, $notes);
                $stmt->execute();
                $captureId = (int)$db->insert_id;
                esc_audit($db, $userId, 'edxeix_submit_capture_saved', [
                    'capture_id' => $captureId,
                    'status' => $status,
                    'action_host' => $host,
                    'action_path' => $path,
                    'required_field_count' => count($required),
                    'select_field_count' => count($selects),
                ]);
                $message = 'Sanitized capture saved as #' . $captureId . '.';
            } catch (Throwable $e) {
                $error = 'Save failed: ' . $e->getMessage();
            }
        }
    }
}

$captures = $db instanceof mysqli ? esc_fetch_captures($db) : [];

esc_shell_begin();
?>
<style>
.esc-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(320px,.65fr);gap:18px}.esc-card{background:#fff;border:1px solid #d8dde7;border-radius:4px;padding:18px 20px;box-shadow:0 6px 18px rgba(26,33,52,.06);margin-bottom:18px}.esc-form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.esc-field.full{grid-column:1 / -1}.esc-field label{display:block;font-weight:700;margin-bottom:6px;color:#475467}.esc-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.esc-code{background:#f1f4fa;border:1px solid #d8dde7;border-radius:4px;padding:12px;white-space:pre-wrap;font-family:Consolas,Menlo,monospace;font-size:13px}.esc-warning{border-left:5px solid #d4922d}.esc-ok{border-left:5px solid #5fa865}.esc-bad{border-left:5px solid #c44b44}.esc-kv{display:grid;grid-template-columns:190px minmax(0,1fr);gap:8px 14px}.esc-kv div{padding:7px 0;border-bottom:1px solid #eef1f5}.esc-kv .k{font-weight:700;color:#667085}@media(max-width:980px){.esc-grid,.esc-form-grid,.esc-kv{grid-template-columns:1fr}.esc-field.full{grid-column:auto}.esc-actions .btn{width:100%;text-align:center}}</style>

<section class="card hero warn">
    <h1>EDXEIX Submit Capture</h1>
    <p>Capture sanitized form research metadata for the future server-side mobile submitter. Do not paste cookies, session values, passwords, CSRF token values, or private credentials.</p>
    <div>
        <?= esc_badge('SANITIZED METADATA ONLY', 'warn') ?>
        <?= esc_badge('NO EDXEIX CALLS', 'good') ?>
        <?= esc_badge('NO LIVE SUBMIT', 'good') ?>
        <?= esc_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
    </div>
</section>

<?php if ($message !== ''): ?><div class="gov-alert gov-alert-good"><?= esc_h($message) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="gov-alert gov-alert-bad"><?= esc_h($error) ?></div><?php endif; ?>

<section class="esc-grid">
    <div>
        <section class="esc-card <?= $tableReady ? 'esc-ok' : 'esc-bad' ?>">
            <h2>1. Capture readiness</h2>
            <div class="esc-kv">
                <div class="k">Database</div><div><?= $db instanceof mysqli ? esc_badge('CONNECTED', 'good') : esc_badge('UNAVAILABLE', 'bad') ?></div>
                <div class="k">Capture table</div><div><?= $tableReady ? esc_badge('READY', 'good') : esc_badge('MISSING', 'bad') ?></div>
                <div class="k">Logged-in user</div><div><?= esc_h((string)($user['username'] ?? 'operator')) ?> <?= esc_badge((string)($user['role'] ?? 'operator'), 'neutral') ?></div>
                <div class="k">Save access</div><div><?= $tableReady ? esc_badge('ENABLED FOR SANITIZED METADATA', 'warn') : esc_badge('RUN SQL FIRST', 'bad') ?></div>
            </div>
            <?php if (!$tableReady): ?>
                <h3>SQL required</h3>
                <pre class="esc-code">mysql -u cabnet_gov -p cabnet_gov &lt; /home/cabnet/gov.cabnet.app_sql/2026_05_12_ops_edxeix_submit_captures.sql</pre>
            <?php endif; ?>
        </section>

        <form class="esc-card" method="post" action="/ops/edxeix-submit-capture.php" autocomplete="off">
            <h2>2. Sanitized capture form</h2>
            <p class="small">Only field names, action host/path, method, and notes are stored. Token values and cookies must not be pasted.</p>
            <input type="hidden" name="csrf" value="<?= esc_h($csrf) ?>">
            <div class="esc-form-grid">
                <div class="esc-field">
                    <label for="capture_status">Capture status</label>
                    <select id="capture_status" name="capture_status">
                        <option value="draft">Draft</option>
                        <option value="candidate">Candidate</option>
                        <option value="validated">Validated</option>
                        <option value="retired">Retired</option>
                    </select>
                </div>
                <div class="esc-field">
                    <label for="form_method">Form method</label>
                    <select id="form_method" name="form_method">
                        <option value="POST">POST</option>
                        <option value="GET">GET</option>
                    </select>
                </div>
                <div class="esc-field full">
                    <label for="form_action_url">EDXEIX form action URL or path</label>
                    <input id="form_action_url" name="form_action_url" type="text" placeholder="https://edxeix.yme.gov.gr/..." required>
                </div>
                <div class="esc-field">
                    <label for="csrf_field_name">CSRF field name only</label>
                    <input id="csrf_field_name" name="csrf_field_name" type="text" placeholder="_token or csrf_token">
                </div>
                <div class="esc-field">
                    <label for="map_address_field_name">Map/address field name</label>
                    <input id="map_address_field_name" name="map_address_field_name" type="text" placeholder="address / location field name">
                </div>
                <div class="esc-field">
                    <label for="map_lat_field_name">Latitude field name</label>
                    <input id="map_lat_field_name" name="map_lat_field_name" type="text" placeholder="lat / latitude field name">
                </div>
                <div class="esc-field">
                    <label for="map_lng_field_name">Longitude field name</label>
                    <input id="map_lng_field_name" name="map_lng_field_name" type="text" placeholder="lng / longitude field name">
                </div>
                <div class="esc-field full">
                    <label for="required_field_names">Required field names</label>
                    <textarea id="required_field_names" name="required_field_names" rows="7" placeholder="One field name per line. Do not include values."></textarea>
                </div>
                <div class="esc-field full">
                    <label for="select_field_names">Select/dropdown field names</label>
                    <textarea id="select_field_names" name="select_field_names" rows="5" placeholder="lessor_id&#10;driver_id&#10;vehicle_id&#10;starting_point_id"></textarea>
                </div>
                <div class="esc-field full">
                    <label for="notes">Sanitized notes</label>
                    <textarea id="notes" name="notes" rows="5" placeholder="Describe observations. Do not paste cookies, session values, passwords, CSRF token values, or credentials."></textarea>
                </div>
            </div>
            <div class="esc-actions">
                <button class="btn warn" type="submit" <?= $tableReady ? '' : 'disabled' ?>>Save sanitized capture</button>
                <a class="btn dark" href="/ops/edxeix-submit-research.php">Back to Research</a>
                <a class="btn" href="/ops/mobile-submit-dev.php">Mobile Submit Dev</a>
            </div>
        </form>
    </div>

    <aside>
        <section class="esc-card esc-warning">
            <h2>What this is for</h2>
            <ol class="list">
                <li>Record the EDXEIX form action path and method.</li>
                <li>Record field names only.</li>
                <li>Identify map coordinate fields.</li>
                <li>Build the later server-side dry-run payload builder.</li>
            </ol>
        </section>
        <section class="esc-card esc-bad">
            <h2>Never store here</h2>
            <ul class="list">
                <li>Cookies</li>
                <li>Session values</li>
                <li>CSRF token values</li>
                <li>Passwords or credentials</li>
                <li>Passenger personal data from real rides unless already part of the sanitized field-name research</li>
            </ul>
        </section>
        <section class="esc-card">
            <h2>Next build target</h2>
            <p><strong>Phase 32:</strong> server-side dry-run payload builder. It should consume a saved capture and a parsed ride, then show what would be posted without making an EDXEIX request.</p>
            <div><?= esc_badge('DRY RUN ONLY', 'good') ?> <?= esc_badge('NO LIVE SUBMIT', 'good') ?></div>
        </section>
    </aside>
</section>

<section class="esc-card">
    <h2>Recent sanitized captures</h2>
    <?php if (!$tableReady): ?>
        <p class="warnline">Run the SQL migration to enable captures.</p>
    <?php elseif ($captures === []): ?>
        <p>No captures saved yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Status</th><th>Method</th><th>Host</th><th>Path</th><th>CSRF field</th><th>Map fields</th><th>By</th><th>Created</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($captures as $row): ?>
                    <tr>
                        <td><?= esc_h($row['id'] ?? '') ?></td>
                        <td><?= esc_badge((string)($row['capture_status'] ?? 'draft'), 'neutral') ?></td>
                        <td><?= esc_h($row['form_method'] ?? '') ?></td>
                        <td><?= esc_h($row['form_action_host'] ?? '') ?></td>
                        <td><code><?= esc_h($row['form_action_path'] ?? '') ?></code></td>
                        <td><?= esc_h($row['csrf_field_name'] ?? '') ?></td>
                        <td>
                            <div class="small">lat: <?= esc_h($row['map_lat_field_name'] ?? '') ?></div>
                            <div class="small">lng: <?= esc_h($row['map_lng_field_name'] ?? '') ?></div>
                            <div class="small">addr: <?= esc_h($row['map_address_field_name'] ?? '') ?></div>
                        </td>
                        <td><?= esc_h(($row['display_name'] ?? '') ?: ($row['username'] ?? '')) ?></td>
                        <td><?= esc_h($row['created_at'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php
esc_shell_end();
