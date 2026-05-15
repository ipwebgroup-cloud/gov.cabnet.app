<?php
/**
 * gov.cabnet.app — shared operations UI shell v3.2.12
 *
 * Include-only helper for the unified /ops interface.
 * Presentation/helper layer only; no Bolt calls, no EDXEIX calls.
 *
 * v3.2.0:
 * - Adds navigation for the V3 Real Future Candidate Capture Readiness board.
 * - Keeps opsui_badge() helper for Handoff Center compatibility.
 * - Navigation/text/helper only; no route moves, deletes, redirects, DB writes, queue mutations, or live-submit changes.
 *
 * v3.2.1:
 * - Normalizes the historical v3.1.6 side-note wording.
 *
 * v3.2.2:
 * - Notes sanitized candidate evidence snapshot export.
 *
 * v3.2.3:
 * - Notes EDXEIX payload preview / dry-run preflight support.
 *
 * v3.2.4:
 * - Notes expired candidate safety regression audit support.
 *
 * v3.2.5:
 * - Notes controlled live-submit readiness checklist support.
 *
 * v3.2.6:
 * - Notes single-row controlled live-submit design draft support.
 *
 * v3.2.7:
 * - Notes controlled live-submit runbook / authorization packet support.
 *
 * v3.2.8:
 * - Notes real-format demo mail fixture preview support.
 *
 * v3.2.9:
 * - Notes controlled Maildir fixture writer design support.
 *
 * v3.2.10:
 * - Notes Maildir fixture writer preflight audit support.
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

function opsui_badge(string $label, string $tone = 'neutral'): string
{
    $tone = strtolower(trim($tone));
    $allowed = [
        'good' => true,
        'warn' => true,
        'bad' => true,
        'neutral' => true,
        'info' => true,
    ];

    if (!isset($allowed[$tone])) {
        $tone = 'neutral';
    }

    return '<span class="badge ' . opsui_h($tone) . '">' . opsui_h($label) . '</span>';
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

function opsui_top_link(string $href, string $label, ?string $current = null): string
{
    return '<a class="gov-top-single' . opsui_active($href, $current) . '" href="' . opsui_h($href) . '">' . opsui_h($label) . '</a>';
}

/**
 * @param array<int,array{0:string,1:string}> $items
 */
function opsui_top_dropdown(string $label, array $items, ?string $current = null): string
{
    $current = $current ?: opsui_current_path();
    $isActive = false;
    foreach ($items as $item) {
        if (($item[0] ?? '') === $current) {
            $isActive = true;
            break;
        }
    }

    $html = '<span class="gov-nav-menu' . ($isActive ? ' active' : '') . '">';
    $html .= '<button type="button">' . opsui_h($label) . ' ▾</button>';
    $html .= '<span class="gov-nav-menu-panel">';
    foreach ($items as $item) {
        $href = (string)($item[0] ?? '#');
        $text = (string)($item[1] ?? $href);
        $html .= '<a class="gov-nav-menu-item' . opsui_active($href, $current) . '" href="' . opsui_h($href) . '">' . opsui_h($text) . '</a>';
    }
    $html .= '</span></span>';
    return $html;
}

function opsui_user_chip(array $user): string
{
    $name = opsui_user_display($user);
    $role = opsui_user_role($user);
    $initials = opsui_initials($name);

    return '<a class="gov-user-chip" href="/ops/profile.php">'
        . '<span class="gov-user-avatar">' . opsui_h($initials) . '</span>'
        . '<span class="gov-user-chip-text"><strong>' . opsui_h($name) . '</strong><small>' . opsui_h($role) . '</small></span>'
        . '</a>';
}

function opsui_preferences(): array
{
    static $cached = null;
    if (is_array($cached)) {
        return $cached;
    }

    $defaults = [
        'sidebar_density' => 'comfortable',
        'table_density' => 'comfortable',
        'show_safety_notices' => '1',
    ];

    $user = opsui_current_user();
    $raw = $user['preferences_json'] ?? $user['preferences'] ?? null;
    if (is_string($raw) && trim($raw) !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $cached = array_merge($defaults, $decoded);
            return $cached;
        }
    }

    $cached = $defaults;
    return $cached;
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

    $preRideItems = [
        ['/ops/pre-ride-email-v3-dashboard.php', 'V3 Control Center'],
        ['/ops/pre-ride-email-tool.php', 'Production Pre-Ride Tool'],
        ['/ops/pre-ride-email-v3-observation-overview.php', 'V3 Observation Overview'],
        ['/ops/pre-ride-email-v3-real-mail-queue-health.php', 'V3 Real-Mail Queue Health'],
        ['/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php', 'V3 Expiry Reason Audit'],
        ['/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php', 'V3 Next Candidate Watch'],
        ['/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php', 'V3 Future Candidate Capture Readiness'],
        ['/ops/pre-ride-email-v3-live-operator-console.php', 'V3 Live Operator Console'],
        ['/ops/pre-ride-email-v3-pre-live-switchboard.php', 'V3 Pre-Live Switchboard'],
        ['/ops/pre-ride-email-v3-live-adapter-contract-test.php', 'V3 Contract Test'],
    ];
    $workflowItems = [
        ['/ops/quick-launch.php', 'Quick Launch'],
        ['/ops/preflight-review.php', 'Preflight Review'],
        ['/ops/safety-checklist.php', 'Safety Checklist'],
        ['/ops/workflow-guide.php', 'Workflow Guide'],
        ['/ops/route-index.php', 'Developer Archive / Route Index'],
    ];
    $helperItems = [
        ['/ops/firefox-extension.php', 'Firefox Helper Center'],
        ['/ops/firefox-extensions-status.php', 'Extension Pair Status'],
        ['/ops/edxeix-session-readiness.php', 'EDXEIX Session Readiness'],
    ];
    $docsItems = [
        ['/ops/handoff-center.php', 'Handoff Center'],
        ['/ops/route-index.php', 'Route Index'],
        ['/ops/documentation-center.php', 'Documentation Center'],
        ['/ops/tool-inventory.php', 'Tool Inventory'],
        ['/ops/system-status.php', 'System Status'],
        ['/ops/deployment-center.php', 'Deployment Center'],
        ['/ops/handoff-package-tools.php', 'Package Tools'],
    ];
    $profileItems = [
        ['/ops/profile.php', 'Profile'],
        ['/ops/profile-edit.php', 'Edit Profile'],
        ['/ops/profile-preferences.php', 'Preferences'],
        ['/ops/profile-password.php', 'Change Password'],
        ['/ops/profile-activity.php', 'My Activity'],
    ];
    $adminItems = [
        ['/ops/admin-control.php', 'Admin Control'],
        ['/ops/readiness-control.php', 'Readiness Control'],
        ['/ops/mapping-control.php', 'Mapping Review'],
        ['/ops/jobs-control.php', 'Jobs Review'],
        ['/ops/users-control.php', 'Users Control'],
        ['/ops/users-new.php', 'Create User'],
        ['/ops/activity-center.php', 'Activity Center'],
        ['/ops/audit-log.php', 'Audit Log'],
        ['/ops/login-attempts.php', 'Login Attempts'],
    ];

    ?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= opsui_h($title) ?> | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=2.5">
    <link rel="stylesheet" href="/assets/css/gov-ops-shell.css?v=3.0">
    <style>
        /* v3.0 compact top navigation: CSS-only dropdowns to avoid second-row wrapping. */
        .gov-topbar{position:sticky;top:0;z-index:1000;align-items:center;flex-wrap:nowrap;overflow:visible;}
        .gov-top-links{flex:1 1 auto;min-width:0;display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-wrap:nowrap;overflow:visible;}
        .gov-top-single,.gov-nav-menu>button{white-space:nowrap;display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:8px 10px;color:#55637f;text-decoration:none;font-size:13px;text-transform:uppercase;letter-spacing:.02em;line-height:1;border:1px solid transparent;background:transparent;cursor:pointer;font-family:inherit;}
        .gov-top-single:hover,.gov-top-single.active,.gov-nav-menu:hover>button,.gov-nav-menu:focus-within>button,.gov-nav-menu.active>button{background:#eef1f8;border-color:#d8dde7;color:#3f4b87;text-decoration:none;}
        .gov-nav-menu{position:relative;display:inline-flex;align-items:center;}
        .gov-nav-menu-panel{display:none;position:absolute;right:0;top:calc(100% + 8px);min-width:240px;background:#fff;border:1px solid #d8dde7;border-radius:8px;box-shadow:0 18px 44px rgba(26,33,52,.18);padding:8px;z-index:2000;}
        .gov-nav-menu-panel:before{content:"";position:absolute;left:0;right:0;top:-10px;height:10px;}
        .gov-nav-menu:hover .gov-nav-menu-panel,.gov-nav-menu:focus-within .gov-nav-menu-panel{display:block;}
        .gov-nav-menu-item{display:block;padding:10px 12px;border-radius:6px;color:#27385f;text-decoration:none;font-size:14px;text-transform:none;letter-spacing:0;white-space:nowrap;}
        .gov-nav-menu-item:hover,.gov-nav-menu-item.active{background:#eef1f8;color:#3f4b87;text-decoration:none;}
        .gov-top-links .gov-user-chip{margin-left:4px;flex:0 0 auto;white-space:nowrap;}
        .gov-brand{flex:0 0 auto;}
        .gov-side-archive{margin-top:10px;border-top:1px solid rgba(255,255,255,.12);padding-top:10px;}
        .gov-side-archive summary{cursor:pointer;color:rgba(255,255,255,.78);font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.08em;padding:8px 0;list-style:none;}
        .gov-side-archive summary::-webkit-details-marker{display:none;}
        .gov-side-archive summary:after{content:'▾';float:right;color:rgba(255,255,255,.55);}
        .gov-side-archive[open] summary:after{content:'▴';}
        .gov-side-archive .gov-side-link{opacity:.86;}
        .gov-side-archive-note{font-size:11px;color:rgba(255,255,255,.62);line-height:1.35;margin:6px 0 8px;}
        @media (max-width:1180px){.gov-top-links{display:none!important;}}
    </style>
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
    <div class="gov-top-links" aria-label="Primary operations navigation">
        <?= opsui_top_link('/ops/home.php', 'Αρχική', $current) ?>
        <?= opsui_top_link('/ops/my-start.php', 'My Start', $current) ?>
        <?= opsui_top_link('/ops/quick-launch.php', 'Launch', $current) ?>
        <?= opsui_top_dropdown('Pre-Ride', $preRideItems, $current) ?>
        <?= opsui_top_dropdown('Workflow', $workflowItems, $current) ?>
        <?= opsui_top_dropdown('Helper', $helperItems, $current) ?>
        <?= opsui_top_dropdown('Docs', $docsItems, $current) ?>
        <?= opsui_is_admin($user) ? opsui_top_dropdown('Admin', $adminItems, $current) : '' ?>
        <?= opsui_top_dropdown('Profile', $profileItems, $current) ?>
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
            <div class="gov-side-group-title">Daily operations</div>
            <?= opsui_side_link('/ops/home.php', 'Ops Home', $current) ?>
            <?= opsui_side_link('/ops/quick-launch.php', 'Quick Launch', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-dashboard.php', 'V3 Control Center', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-tool.php', 'Production Pre-Ride Tool', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-observation-overview.php', 'V3 Observation Overview', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-real-mail-queue-health.php', 'Real-Mail Queue Health', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php', 'Expiry Reason Audit', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php', 'Next Candidate Watch', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php', 'Future Candidate Capture', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-live-operator-console.php', 'Live Operator Console', $current) ?>
            <?= opsui_side_link('/ops/preflight-review.php', 'Preflight Review', $current) ?>

            <div class="gov-side-group-title">V3 proof & readiness</div>
            <?= opsui_side_link('/ops/pre-ride-email-v3-live-adapter-contract-test.php', 'Live Adapter Contract Test', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-live-gate-drift-guard.php', 'Live Gate Drift Guard', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-pre-live-switchboard.php', 'Pre-Live Switchboard', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-adapter-payload-consistency.php', 'Payload Consistency', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-adapter-row-simulation.php', 'Adapter Row Simulation', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-pre-live-proof-bundle-export.php', 'Proof Bundle Export', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-readiness-focus.php', 'Readiness Focus', $current) ?>
            <?= opsui_side_link('/ops/pre-ride-email-v3-queue-focus.php', 'Queue Focus', $current) ?>

            <div class="gov-side-group-title">Admin & audit</div>
            <?= opsui_side_link('/ops/admin-control.php', 'Admin Control', $current) ?>
            <?= opsui_side_link('/ops/readiness-control.php', 'Readiness Control', $current) ?>
            <?= opsui_side_link('/ops/mapping-control.php', 'Mapping Review', $current) ?>
            <?= opsui_side_link('/ops/jobs-control.php', 'Jobs Review', $current) ?>
            <?= opsui_side_link('/ops/handoff-center.php', 'Handoff Center', $current) ?>
            <?= opsui_side_link('/ops/route-index.php', 'Route Index / Archive', $current) ?>
            <?= opsui_side_link('/ops/system-status.php', 'System Status', $current) ?>

            <details class="gov-side-archive">
                <summary>Developer archive</summary>
                <p class="gov-side-archive-note">No routes were deleted. Older V2, mobile, test, evidence, package, and helper pages remain available here for supervised use.</p>
                <?= opsui_side_link('/ops/pre-ride-email-toolv2.php', 'Pre-Ride Tool V2 Dev', $current) ?>
                <?= opsui_side_link('/ops/test-session.php', 'Test Session Control', $current) ?>
                <?= opsui_side_link('/ops/dev-accelerator.php', 'Dev Accelerator', $current) ?>
                <?= opsui_side_link('/ops/evidence-bundle.php', 'Evidence Bundle', $current) ?>
                <?= opsui_side_link('/ops/evidence-report.php', 'Evidence Report', $current) ?>
                <?= opsui_side_link('/ops/firefox-extension.php', 'Firefox Helper Center', $current) ?>
                <?= opsui_side_link('/ops/firefox-extensions-status.php', 'Extension Pair Status', $current) ?>
                <?= opsui_side_link('/ops/mobile-compatibility.php', 'Mobile Compatibility', $current) ?>
                <?= opsui_side_link('/ops/pre-ride-mobile-review.php', 'Mobile Pre-Ride Review', $current) ?>
                <?= opsui_side_link('/ops/workflow-guide.php', 'Workflow Guide', $current) ?>
                <?= opsui_side_link('/ops/safety-checklist.php', 'Safety Checklist', $current) ?>
                <?= opsui_side_link('/ops/documentation-center.php', 'Documentation Center', $current) ?>
                <?= opsui_side_link('/ops/tool-inventory.php', 'Tool Inventory', $current) ?>
                <?= opsui_side_link('/ops/public-route-exposure-audit.php', 'Public Route Exposure Audit', $current) ?>
                <?= opsui_side_link('/ops/public-utility-relocation-plan.php', 'Public Utility Relocation Plan', $current) ?>
                <?= opsui_side_link('/ops/public-utility-reference-cleanup-phase2-preview.php', 'Public Utility Phase 2 Preview', $current) ?>
                <?= opsui_side_link('/ops/legacy-public-utility.php', 'Legacy Public Utility Wrapper', $current) ?>
                <?= opsui_side_link('/ops/legacy-public-utility-usage-audit.php', 'Legacy Utility Usage Audit', $current) ?>
                <?= opsui_side_link('/ops/legacy-public-utility-quiet-period-audit.php', 'Legacy Quiet-Period Audit', $current) ?>
                <?= opsui_side_link('/ops/legacy-public-utility-stats-source-audit.php', 'Legacy Stats Source Audit', $current) ?>
                <?= opsui_side_link('/ops/legacy-public-utility-readiness-board.php', 'Legacy Utility Readiness Board', $current) ?>
                <?= opsui_side_link('/ops/pre-ride-email-v3-observation-toolchain-integrity-audit.php', 'V3 Toolchain Integrity Audit', $current) ?>
                <?= opsui_side_link('/ops/deployment-center.php', 'Deployment Center', $current) ?>
                <?= opsui_side_link('/ops/handoff-package-tools.php', 'Package Tools', $current) ?>
                <?= opsui_side_link('/ops/handoff-package-archive.php', 'Package Archive', $current) ?>
                <?= opsui_side_link('/ops/handoff-package-validator.php', 'Package Validator', $current) ?>
                <?= opsui_side_link('/ops/ui-shell-preview.php', 'UI Shell Preview', $current) ?>
            </details>

            <div class="gov-side-group-title">User area</div>
            <?= opsui_side_link('/ops/my-start.php', 'My Start', $current) ?>
            <?= opsui_side_link('/ops/profile.php', 'Operator Profile', $current) ?>
            <?= opsui_side_link('/ops/profile-preferences.php', 'Preferences', $current) ?>
            <?= opsui_is_admin($user) ? opsui_side_link('/ops/activity-center.php', 'Activity Center', $current) : '' ?>
            <?= opsui_is_admin($user) ? opsui_side_link('/ops/users-control.php', 'Users Control', $current) : '' ?>
            <?= opsui_is_admin($user) ? opsui_side_link('/ops/audit-log.php', 'Audit Log', $current) : '' ?>
        </div>

        <div class="gov-side-note">V3.2.12 observation toolchain installed. Maildir writer go/no-go snapshot is available. Routes were not deleted. Live EDXEIX submission remains blocked.</div>
    </aside>

    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title"><?= opsui_h($pageTitle) ?></h1>
                <div class="gov-breadcrumbs"><?= opsui_h($breadcrumbs) ?></div>
            </div>
            <div class="gov-tabs">
                <?= opsui_tab('/ops/home.php', 'Καρτέλα', $current) ?>
                <?= opsui_tab('/ops/quick-launch.php', 'Launch', $current) ?>
                <?= opsui_tab('/ops/pre-ride-email-v3-dashboard.php', 'V3 Control', $current) ?>
                <?= opsui_tab('/ops/pre-ride-email-tool.php', 'Pre-Ride', $current) ?>
                <?= opsui_tab('/ops/pre-ride-email-v3-live-operator-console.php', 'Live Console', $current) ?>
                <?= opsui_tab('/ops/mapping-control.php', 'Mapping', $current) ?>
                <?= opsui_tab('/ops/handoff-center.php', 'Handoff', $current) ?>
                <?= opsui_tab('/ops/route-index.php', 'Archive', $current) ?>
                <?= opsui_tab('/ops/profile.php', 'Profile', $current) ?>
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
