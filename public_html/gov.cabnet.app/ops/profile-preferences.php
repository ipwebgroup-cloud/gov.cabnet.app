<?php
/**
 * gov.cabnet.app — operator preferences page v1.0
 *
 * Allows a logged-in operator to store safe UI/display preferences.
 * Does not call Bolt, EDXEIX, AADE, or any trip workflow.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    echo 'Private app bootstrap not found.';
    exit;
}

try {
    $ctx = require $bootstrap;
    $db = $ctx['db']->connection();
    $auth = new Bridge\Auth\OpsAuth($db, [
        'session_name' => (string)$ctx['config']->get('ops_auth.session_name', 'gov_cabnet_ops_session'),
        'login_path' => (string)$ctx['config']->get('ops_auth.login_path', '/ops/login.php'),
        'after_login_path' => (string)$ctx['config']->get('ops_auth.after_login_path', '/ops/home.php'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Preferences page bootstrap failed.';
    exit;
}

$user = opsui_current_user();
$userId = (int)($user['id'] ?? 0);
$message = '';
$messageType = 'neutral';

function oppref_client_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
        $value = trim((string)($_SERVER[$key] ?? ''));
        if ($value !== '') { return substr($value, 0, 45); }
    }
    return '';
}

function oppref_table_exists(mysqli $db, string $table): bool
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

function oppref_audit(mysqli $db, int $userId, string $event, array $meta = []): void
{
    try {
        $ip = oppref_client_ip();
        $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $metaJson = $meta === [] ? null : json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $db->prepare('INSERT INTO ops_audit_log (user_id, event_type, ip_address, user_agent, meta_json, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('issss', $userId, $event, $ip, $ua, $metaJson);
        $stmt->execute();
    } catch (Throwable) {
        // Audit failure must not break preferences during staged rollout.
    }
}

function oppref_defaults(): array
{
    return [
        'default_landing_path' => '/ops/home.php',
        'sidebar_density' => 'comfortable',
        'table_density' => 'comfortable',
        'show_safety_notices' => '1',
        'updated_at' => '',
    ];
}

function oppref_fetch(mysqli $db, int $userId): array
{
    $defaults = oppref_defaults();
    if ($userId <= 0 || !oppref_table_exists($db, 'ops_user_preferences')) {
        return $defaults;
    }

    try {
        $stmt = $db->prepare('SELECT default_landing_path, sidebar_density, table_density, show_safety_notices, updated_at FROM ops_user_preferences WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if (!is_array($row)) {
            return $defaults;
        }
        return array_merge($defaults, [
            'default_landing_path' => (string)($row['default_landing_path'] ?? $defaults['default_landing_path']),
            'sidebar_density' => (string)($row['sidebar_density'] ?? $defaults['sidebar_density']),
            'table_density' => (string)($row['table_density'] ?? $defaults['table_density']),
            'show_safety_notices' => (string)((int)($row['show_safety_notices'] ?? 1)),
            'updated_at' => (string)($row['updated_at'] ?? ''),
        ]);
    } catch (Throwable) {
        return $defaults;
    }
}

function oppref_save(mysqli $db, int $userId, array $prefs): void
{
    $stmt = $db->prepare(
        'INSERT INTO ops_user_preferences (user_id, default_landing_path, sidebar_density, table_density, show_safety_notices, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, NOW(), NOW())
         ON DUPLICATE KEY UPDATE
             default_landing_path = VALUES(default_landing_path),
             sidebar_density = VALUES(sidebar_density),
             table_density = VALUES(table_density),
             show_safety_notices = VALUES(show_safety_notices),
             updated_at = NOW()'
    );
    $show = (int)$prefs['show_safety_notices'];
    $stmt->bind_param(
        'isssi',
        $userId,
        $prefs['default_landing_path'],
        $prefs['sidebar_density'],
        $prefs['table_density'],
        $show
    );
    $stmt->execute();
}

$allowedLanding = [
    '/ops/home.php' => 'Ops Home',
    '/ops/pre-ride-email-tool.php' => 'Production Pre-Ride Tool',
    '/ops/pre-ride-email-toolv2.php' => 'Pre-Ride V2 Dev',
    '/ops/test-session.php' => 'Test Session Control',
    '/ops/preflight-review.php' => 'Preflight Review',
    '/ops/profile.php' => 'Profile',
];
$allowedDensity = ['comfortable' => 'Comfortable', 'compact' => 'Compact'];
$tableReady = oppref_table_exists($db, 'ops_user_preferences');
$preferences = oppref_fetch($db, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $landing = (string)($_POST['default_landing_path'] ?? '');
    $sidebarDensity = (string)($_POST['sidebar_density'] ?? 'comfortable');
    $tableDensity = (string)($_POST['table_density'] ?? 'comfortable');
    $showSafety = !empty($_POST['show_safety_notices']) ? '1' : '0';

    if (!$auth->validateCsrf((string)($_POST['csrf'] ?? ''))) {
        $message = 'Security token expired. Please try again.';
        $messageType = 'bad';
    } elseif (!$tableReady) {
        $message = 'Preferences table is not installed yet. Run the Phase 8 SQL migration first.';
        $messageType = 'bad';
    } elseif ($userId <= 0) {
        $message = 'Your user session could not be identified.';
        $messageType = 'bad';
    } elseif (!array_key_exists($landing, $allowedLanding)) {
        $message = 'Selected landing page is not allowed.';
        $messageType = 'bad';
    } elseif (!array_key_exists($sidebarDensity, $allowedDensity) || !array_key_exists($tableDensity, $allowedDensity)) {
        $message = 'Selected density value is not allowed.';
        $messageType = 'bad';
    } else {
        try {
            $payload = [
                'default_landing_path' => $landing,
                'sidebar_density' => $sidebarDensity,
                'table_density' => $tableDensity,
                'show_safety_notices' => $showSafety,
            ];
            oppref_save($db, $userId, $payload);
            oppref_audit($db, $userId, 'preferences_updated', $payload);
            $preferences = oppref_fetch($db, $userId);
            $message = 'Preferences saved successfully.';
            $messageType = 'good';
        } catch (Throwable) {
            $message = 'Preferences could not be saved. Please try again.';
            $messageType = 'bad';
        }
    }
}

$csrf = $auth->csrfToken();

opsui_shell_begin([
    'title' => 'Preferences',
    'page_title' => 'Προτιμήσεις χρήστη',
    'active_section' => 'User area',
    'subtitle' => 'Safe personal display preferences for the operator console',
    'breadcrumbs' => 'Αρχική / Χρήστες / Προτιμήσεις',
    'safe_notice' => 'This page only stores personal UI preferences for the logged-in operator. It does not call Bolt, EDXEIX, AADE, or any trip workflow.',
]);
?>
<?php if (!$tableReady): ?>
    <?= opsui_flash('Preferences table is not installed yet. Run gov.cabnet.app_sql/2026_05_11_ops_user_preferences.sql before saving preferences.', 'warn') ?>
<?php endif; ?>
<?php if ($message !== ''): ?>
    <?= opsui_flash($message, $messageType) ?>
<?php endif; ?>

<section class="card hero neutral">
    <h1>Operator preferences</h1>
    <p>Store personal UI preferences for your account. These settings are intentionally limited and do not affect live workflow behavior.</p>
    <div>
        <?= opsui_badge('PROFILE AREA', 'neutral') ?>
        <?= opsui_badge('SELF-SERVICE', 'good') ?>
        <?= opsui_badge('NO WORKFLOW ACTIONS', 'good') ?>
    </div>
</section>

<section class="two">
    <article class="card">
        <h2>Preferences</h2>
        <form method="post" action="/ops/profile-preferences.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= opsui_h($csrf) ?>">
            <div class="gov-form-grid">
                <div class="gov-form-field full">
                    <label for="default_landing_path">Default landing page</label>
                    <select id="default_landing_path" name="default_landing_path">
                        <?php foreach ($allowedLanding as $path => $label): ?>
                            <option value="<?= opsui_h($path) ?>" <?= $preferences['default_landing_path'] === $path ? 'selected' : '' ?>><?= opsui_h($label) ?> — <?= opsui_h($path) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="gov-form-help">Stored only as a preference for now. We can apply it to login redirects after confirming the flow.</div>
                </div>

                <div class="gov-form-field">
                    <label for="sidebar_density">Sidebar density</label>
                    <select id="sidebar_density" name="sidebar_density">
                        <?php foreach ($allowedDensity as $value => $label): ?>
                            <option value="<?= opsui_h($value) ?>" <?= $preferences['sidebar_density'] === $value ? 'selected' : '' ?>><?= opsui_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gov-form-field">
                    <label for="table_density">Table density</label>
                    <select id="table_density" name="table_density">
                        <?php foreach ($allowedDensity as $value => $label): ?>
                            <option value="<?= opsui_h($value) ?>" <?= $preferences['table_density'] === $value ? 'selected' : '' ?>><?= opsui_h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="gov-form-field full gov-check-field">
                    <label>
                        <input type="checkbox" name="show_safety_notices" value="1" <?= $preferences['show_safety_notices'] === '1' ? 'checked' : '' ?>>
                        Show safety notices where supported
                    </label>
                    <div class="gov-form-help">Safety notices remain enabled by default. Hiding them will only be applied on pages we later update to honor this preference.</div>
                </div>
            </div>

            <div class="gov-panel-actions">
                <button class="btn good" type="submit" <?= $tableReady ? '' : 'disabled' ?>>Save preferences</button>
                <a class="btn dark" href="/ops/profile.php">Back to profile</a>
            </div>
        </form>
    </article>

    <article class="card">
        <h2>Current preference snapshot</h2>
        <div class="gov-info-list">
            <div class="gov-info-row"><div class="label">Default landing page</div><div class="value"><code><?= opsui_h($preferences['default_landing_path']) ?></code></div></div>
            <div class="gov-info-row"><div class="label">Sidebar density</div><div class="value"><?= opsui_h($preferences['sidebar_density']) ?></div></div>
            <div class="gov-info-row"><div class="label">Table density</div><div class="value"><?= opsui_h($preferences['table_density']) ?></div></div>
            <div class="gov-info-row"><div class="label">Safety notices</div><div class="value"><?= $preferences['show_safety_notices'] === '1' ? opsui_badge('SHOW', 'good') : opsui_badge('HIDE WHERE SUPPORTED', 'warn') ?></div></div>
            <div class="gov-info-row"><div class="label">Updated</div><div class="value"><?= opsui_h($preferences['updated_at'] !== '' ? $preferences['updated_at'] : 'Not saved yet') ?></div></div>
        </div>
        <div class="gov-alert-note" style="margin-top:14px;">
            Phase 8 stores preferences first. Applying them globally should be a separate tested rollout so production pages are not disrupted.
        </div>
    </article>
</section>
<?php opsui_shell_end(); ?>
