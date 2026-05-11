<?php
/**
 * gov.cabnet.app — shared operations UI shell v1.1
 *
 * Include-only helper for the unified /ops interface.
 * Presentation helper only; no Bolt calls, no EDXEIX calls, no DB writes.
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

    ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= opsui_h($title) ?> | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.5">
    <link rel="stylesheet" href="/assets/css/gov-ops-shell.css?v=1.1">
</head>
<body>
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
        <a href="/ops/pre-ride-email-tool.php">Pre-Ride</a>
        <a href="/ops/pre-ride-email-toolv2.php">Pre-Ride V2</a>
        <a href="/ops/test-session.php">Test Session</a>
        <a href="/ops/preflight-review.php">Preflight Review</a>
        <a href="/ops/route-index.php">Route Index</a>
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
            <?= opsui_side_link('/ops/firefox-extension.php', 'Firefox Helper', $current) ?>
            <?= opsui_side_link('/ops/route-index.php', 'Route Index', $current) ?>
            <?= opsui_side_link('/ops/profile.php', 'Operator Profile', $current) ?>
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
                <?= opsui_tab('/ops/pre-ride-email-tool.php', 'Pre-Ride', $current) ?>
                <?= opsui_tab('/ops/pre-ride-email-toolv2.php', 'V2 Dev', $current) ?>
                <?= opsui_tab('/ops/test-session.php', 'Test Session', $current) ?>
                <?= opsui_tab('/ops/admin-control.php', 'Administration', $current) ?>
                <?= opsui_tab('/ops/profile.php', 'Profile', $current) ?>
            </div>
        </div>

        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>SAFE OPS SHELL.</strong>
                <?= opsui_h($safeNotice) ?>
            </section>
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
