<?php
/**
 * gov.cabnet.app — shared operations UI shell v2.1
 *
 * Include-only helper for the unified /ops interface.
 * Presentation/helper layer only; no Bolt calls, no EDXEIX calls.
 */

declare(strict_types=1);

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

function opsui_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function opsui_current_user(): array
{
    $user = $_SESSION['ops_user'] ?? [];
    return is_array($user) ? $user : [];
}

function opsui_user_display(array $user): string
{
    $display = trim((string)($user['display_name'] ?? ''));
    if ($display !== '') { return $display; }
    $username = trim((string)($user['username'] ?? ''));
    return $username !== '' ? $username : 'Operator';
}

function opsui_user_role(array $user): string
{
    $role = trim((string)($user['role'] ?? 'operator'));
    return $role !== '' ? $role : 'operator';
}

function opsui_is_admin(?array $user = null): bool
{
    $user = $user ?? opsui_current_user();
    return strtolower(opsui_user_role($user)) === 'admin';
}

function opsui_substr(string $value, int $start, int $length): string
{
    if (function_exists('mb_substr')) {
        return mb_substr($value, $start, $length, 'UTF-8');
    }
    return substr($value, $start, $length);
}

function opsui_upper(string $value): string
{
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($value, 'UTF-8');
    }
    return strtoupper($value);
}

function opsui_initials(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') { return 'OP'; }
    $parts = explode(' ', $name);
    $first = opsui_substr($parts[0] ?? 'O', 0, 1);
    $last = count($parts) > 1 ? opsui_substr($parts[count($parts) - 1], 0, 1) : '';
    $out = opsui_upper($first . $last);
    return $out !== '' ? $out : 'OP';
}

function opsui_current_path(): string
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/ops/home.php'), PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/ops/home.php';
}

function opsui_active(string $href, ?string $current = null): string
{
    $current = $current ?: opsui_current_path();
    return $current === $href ? ' active' : '';
}

function opsui_side_link(string $href, string $label, ?string $current = null): string
{
    return '<a class="gov-side-link' . opsui_active($href, $current) . '" href="' . opsui_h($href) . '">' . opsui_h($label) . '</a>';
}

function opsui_tab(string $href, string $label, ?string $current = null): string
{
    return '<a class="gov-tab' . opsui_active($href, $current) . '" href="' . opsui_h($href) . '">' . opsui_h($label) . '</a>';
}

function opsui_badge(string $text, string $type = 'neutral'): string
{
    return '<span class="badge badge-' . opsui_h($type) . '">' . opsui_h($text) . '</span>';
}

function opsui_metric(mixed $value, string $label): string
{
    return '<div class="metric"><strong>' . opsui_h((string)$value) . '</strong><span>' . opsui_h($label) . '</span></div>';
}

function opsui_bool_badge(bool $value, string $yes = 'YES', string $no = 'NO'): string
{
    return opsui_badge($value ? $yes : $no, $value ? 'good' : 'bad');
}

function opsui_user_chip(array $user): string
{
    $name = opsui_user_display($user);
    $role = opsui_user_role($user);
    return '<a class="gov-user-chip" href="/ops/profile.php" title="Operator profile">'
        . '<span class="gov-user-avatar">' . opsui_h(opsui_initials($name)) . '</span>'
        . '<span class="gov-user-meta"><strong>' . opsui_h($name) . '</strong><span>' . opsui_h($role) . '</span></span>'
        . '</a>';
}

function opsui_flash(string $message, string $type = 'neutral'): string
{
    $class = in_array($type, ['good', 'warn', 'bad', 'neutral'], true) ? $type : 'neutral';
    return '<div class="gov-alert gov-alert-' . opsui_h($class) . '">' . opsui_h($message) . '</div>';
}


function opsui_table_exists(mysqli $db, string $table): bool
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

function opsui_preferences_defaults(): array
{
    return [
        'default_landing_path' => '/ops/home.php',
        'sidebar_density' => 'comfortable',
        'table_density' => 'comfortable',
        'show_safety_notices' => '1',
    ];
}

function opsui_sanitize_preferences(array $prefs): array
{
    $defaults = opsui_preferences_defaults();
    $allowedLanding = [
        '/ops/home.php',
        '/ops/pre-ride-email-tool.php',
        '/ops/pre-ride-email-toolv2.php',
        '/ops/test-session.php',
        '/ops/preflight-review.php',
        '/ops/profile.php',
    ];
    $allowedDensity = ['comfortable', 'compact'];

    $out = array_merge($defaults, $prefs);
    if (!in_array((string)$out['default_landing_path'], $allowedLanding, true)) {
        $out['default_landing_path'] = $defaults['default_landing_path'];
    }
    if (!in_array((string)$out['sidebar_density'], $allowedDensity, true)) {
        $out['sidebar_density'] = $defaults['sidebar_density'];
    }
    if (!in_array((string)$out['table_density'], $allowedDensity, true)) {
        $out['table_density'] = $defaults['table_density'];
    }
    $out['show_safety_notices'] = ((string)$out['show_safety_notices'] === '0') ? '0' : '1';
    return $out;
}

function opsui_preferences(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $defaults = opsui_preferences_defaults();
    $user = opsui_current_user();
    $userId = (int)($user['id'] ?? 0);
    if ($userId <= 0) {
        $cached = $defaults;
        return $cached;
    }

    $bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
    if (!is_file($bootstrap)) {
        $cached = $defaults;
        return $cached;
    }

    try {
        $ctx = require $bootstrap;
        if (!is_array($ctx) || !isset($ctx['db']) || !method_exists($ctx['db'], 'connection')) {
            throw new RuntimeException('Invalid private app context.');
        }
        $db = $ctx['db']->connection();
        if (!opsui_table_exists($db, 'ops_user_preferences')) {
            $cached = $defaults;
            return $cached;
        }
        $stmt = $db->prepare('SELECT default_landing_path, sidebar_density, table_density, show_safety_notices FROM ops_user_preferences WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $cached = is_array($row) ? opsui_sanitize_preferences($row) : $defaults;
        return $cached;
    } catch (Throwable) {
        $cached = $defaults;
        return $cached;
    }
}

function opsui_preference_body_class(array $prefs): string
{
    $classes = [];
    if (($prefs['sidebar_density'] ?? 'comfortable') === 'compact') {
        $classes[] = 'gov-pref-sidebar-compact';
    }
    if (($prefs['table_density'] ?? 'comfortable') === 'compact') {
        $classes[] = 'gov-pref-table-compact';
    }
    if (($prefs['show_safety_notices'] ?? '1') === '0') {
        $classes[] = 'gov-pref-hide-safety';
    }
    return implode(' ', $classes);
}


/**
 * @param array<string,mixed> $options
 */
function opsui_shell_begin(array $options = []): void
{
    $title = (string)($options['title'] ?? 'Operations');
    $pageTitle = (string)($options['page_title'] ?? $title);
    $subtitle = (string)($options['subtitle'] ?? 'Safe Bolt → EDXEIX operator console');
    $breadcrumbs = (string)($options['breadcrumbs'] ?? 'Αρχική / Διαχειριστικό');
    $activeSection = (string)($options['active_section'] ?? 'Operations');
    $current = opsui_current_path();
    $user = opsui_current_user();
    $name = opsui_user_display($user);
    $role = opsui_user_role($user);
    $initials = opsui_initials($name);
    $safeNotice = (string)($options['safe_notice'] ?? 'This page uses the shared operations shell. It does not change live EDXEIX submission behavior.');
    $prefs = opsui_preferences();
    $bodyClass = opsui_preference_body_class($prefs);
    $showSafetyNotice = (($prefs['show_safety_notices'] ?? '1') !== '0') || !empty($options['force_safe_notice']);

    ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= opsui_h($title) ?> | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.5">
    <link rel="stylesheet" href="/assets/css/gov-ops-shell.css?v=2.1">
</head>
<body class="<?= opsui_h($bodyClass) ?>">
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text">
            <strong>gov.cabnet.app</strong>
            <span>Bolt → EDXEIX operational console</span>
        </div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/home.php">Αρχική</a>
        <a href="/ops/my-start.php">My Start</a>
        <a href="/ops/pre-ride-email-tool.php">Pre-Ride</a>
        <a href="/ops/pre-ride-email-toolv2.php">Pre-Ride V2</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight Review</a>
        <a href="/ops/firefox-extension.php">Helper</a>
        <a href="/ops/firefox-extensions-status.php">Helper Status</a>
        <a href="/ops/profile.php">Profile</a>
        <?= opsui_is_admin($user) ? '<a href="/ops/users-control.php">Users</a><a href="/ops/activity-center.php">Activity</a>' : '' ?>
        <?= opsui_user_chip($user) ?>
    </div>
</div>

<div class="gov-shell">
    <aside class="gov-sidebar">
        <div class="gov-side-profile">
            <a class="gov-side-profile-main" href="/ops/profile.php">
                <span class="gov-user-avatar"><?= opsui_h($initials) ?></span>
                <span><strong><?= opsui_h($name) ?></strong><span><?= opsui_h($role) ?></span></span>
            </a>
            <div class="gov-side-mini-actions">
                <a href="/ops/profile.php">Profile</a>
                <a href="/ops/profile-edit.php">Edit</a>
                <a href="/ops/profile-password.php">Password</a>
                <a href="/ops/logout.php">Logout</a>
            </div>
        </div>

        <h3><?= opsui_h($activeSection !== '' ? $activeSection : $pageTitle) ?></h3>
        <p><?= opsui_h($subtitle) ?></p>

        <div class="gov-side-group">
            <div class="gov-side-group-title">Primary workflow</div>
            <?= opsui_side_link('/ops/home.php', 'Ops Home', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-tool.php', 'Production Pre-Ride Tool', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-toolv2.php', 'Pre-Ride Tool V2 Dev', $current) ?>
            <?= opsui_side_link('/ops/test-session.php', 'Test Session Control', $current) ?>
            <?= opsui_side_link('/ops/preflight-review.php', 'Preflight Review', $current) ?>
            <?= opsui_side_link('/ops/dev-accelerator.php', 'Dev Accelerator', $current) ?>

            <div class="gov-side-group-title">Evidence</div>
            <?= opsui_side_link('/ops/evidence-bundle.php', 'Evidence Bundle', $current) ?>
            <?= opsui_side_link('/ops/evidence-report.php', 'Evidence Report', $current) ?>

            <div class="gov-side-group-title">Administration</div>
            <?= opsui_side_link('/ops/admin-control.php', 'Admin Control', $current) ?>
            <?= opsui_side_link('/ops/readiness-control.php', 'Readiness Control', $current) ?>
            <?= opsui_side_link('/ops/mapping-control.php', 'Mapping Review', $current) ?>
            <?= opsui_side_link('/ops/jobs-control.php', 'Jobs Review', $current) ?>
            <?= opsui_side_link('/ops/firefox-extension.php', 'Firefox Helper Center', $current) ?>
            <?= opsui_side_link('/ops/firefox-extensions-status.php', 'Extension Pair Status', $current) ?>
            <?= opsui_side_link('/ops/route-index.php', 'Route Index', $current) ?>

            <div class="gov-side-group-title">User area</div>
            <?= opsui_side_link('/ops/my-start.php', 'My Start', $current) ?>
            <?= opsui_side_link('/ops/profile.php', 'Operator Profile', $current) ?>
            <?= opsui_side_link('/ops/profile-edit.php', 'Edit Profile', $current) ?>
            <?= opsui_side_link('/ops/profile-preferences.php', 'Preferences', $current) ?>
            <?= opsui_side_link('/ops/profile-password.php', 'Change Password', $current) ?>
            <?= opsui_side_link('/ops/profile-activity.php', 'My Activity', $current) ?>
            <?= opsui_is_admin($user) ? opsui_side_link('/ops/activity-center.php', 'Activity Center', $current) : '' ?>
            <?= opsui_is_admin($user) ? opsui_side_link('/ops/users-control.php', 'Users Control', $current) : '' ?>
            <?= opsui_is_admin($user) ? opsui_side_link('/ops/users-new.php', 'Create User', $current) : '' ?>
            <?= opsui_is_admin($user) ? opsui_side_link('/ops/audit-log.php', 'Audit Log', $current) : '' ?>
            <?= opsui_is_admin($user) ? opsui_side_link('/ops/login-attempts.php', 'Login Attempts', $current) : '' ?>
            <?= opsui_side_link('/ops/ui-shell-preview.php', 'UI Shell Preview', $current) ?>
        </div>

        <div class="gov-side-note">Live EDXEIX submission remains blocked unless explicitly enabled later under a separate reviewed change.</div>
    </aside>

    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title"><?= opsui_h($pageTitle) ?></h1>
                <div class="gov-breadcrumbs"><?= opsui_h($breadcrumbs) ?></div>
            </div>
            <div class="gov-tabs">
                <?= opsui_tab('/ops/home.php', 'Καρτέλα', $current) ?>
                <?= opsui_tab('/ops/my-start.php', 'My Start', $current) ?>
                <?= opsui_tab('/ops/pre-ride-email-tool.php', 'Pre-Ride', $current) ?>
                <?= opsui_tab('/ops/pre-ride-email-toolv2.php', 'V2 Dev', $current) ?>
                <?= opsui_tab('/ops/test-session.php', 'Test Session', $current) ?>
                <?= opsui_tab('/ops/admin-control.php', 'Administration', $current) ?>
                <?= opsui_tab('/ops/firefox-extension.php', 'Helper', $current) ?>
                <?= opsui_tab('/ops/firefox-extensions-status.php', 'Helper Status', $current) ?>
                <?= opsui_is_admin($user) ? opsui_tab('/ops/activity-center.php', 'Activity', $current) : '' ?>
                <?= opsui_tab('/ops/profile.php', 'Profile', $current) ?>
                <?= opsui_tab('/ops/profile-preferences.php', 'Preferences', $current) ?>
            </div>
        </div>

        <main class="wrap wrap-shell">
            <?php if ($showSafetyNotice): ?>
            <section class="safety gov-optional-safety">
                <strong>SAFE OPS SHELL.</strong>
                <?= opsui_h($safeNotice) ?>
            </section>
            <?php endif; ?>
    <?php
}

function opsui_shell_end(): void
{
    ?>
        </main>
    </div>
</div>
</body>
</html>
    <?php
}
