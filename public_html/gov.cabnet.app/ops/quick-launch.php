<?php
/**
 * gov.cabnet.app — Ops Quick Launch
 *
 * Read-only route launcher for the shared operations GUI.
 * No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

/** @return array<int,array<string,string>> */
function gql_routes(): array
{
    return [
        ['group' => 'Primary', 'label' => 'Ops Home', 'url' => '/ops/home.php', 'desc' => 'Main safe operator landing page.', 'mode' => 'read-only'],
        ['group' => 'Primary', 'label' => 'Production Pre-Ride Tool', 'url' => '/ops/pre-ride-email-tool.php', 'desc' => 'Live production pre-ride email to EDXEIX operator tool. Do not disrupt.', 'mode' => 'production'],
        ['group' => 'Primary', 'label' => 'Pre-Ride Tool V2 Dev', 'url' => '/ops/pre-ride-email-toolv2.php', 'desc' => 'Safe development wrapper for future pre-ride improvements.', 'mode' => 'dev'],
        ['group' => 'Primary', 'label' => 'Mobile Pre-Ride Review', 'url' => '/ops/pre-ride-mobile-review.php', 'desc' => 'Mobile-friendly read-only review/checking page.', 'mode' => 'review'],
        ['group' => 'Workflow', 'label' => 'Test Session Control', 'url' => '/ops/test-session.php', 'desc' => 'Main page to use during the next real future Bolt ride test.', 'mode' => 'guarded'],
        ['group' => 'Workflow', 'label' => 'Preflight Review', 'url' => '/ops/preflight-review.php', 'desc' => 'Plain-language preflight state and blockers.', 'mode' => 'read-only'],
        ['group' => 'Workflow', 'label' => 'Dev Accelerator', 'url' => '/ops/dev-accelerator.php', 'desc' => 'Dry-run capture buttons and development support.', 'mode' => 'guarded'],
        ['group' => 'Workflow', 'label' => 'Workflow Guide', 'url' => '/ops/workflow-guide.php', 'desc' => 'Staff SOP for the live manual EDXEIX workflow.', 'mode' => 'guide'],
        ['group' => 'Workflow', 'label' => 'Safety Checklist', 'url' => '/ops/safety-checklist.php', 'desc' => 'Pre-submit safety checklist for operators.', 'mode' => 'guide'],
        ['group' => 'Helper', 'label' => 'Firefox Helper Center', 'url' => '/ops/firefox-extension.php', 'desc' => 'Download/status page for current helper files.', 'mode' => 'download'],
        ['group' => 'Helper', 'label' => 'Extension Pair Status', 'url' => '/ops/firefox-extensions-status.php', 'desc' => 'Current two-extension workflow inventory.', 'mode' => 'read-only'],
        ['group' => 'Helper', 'label' => 'Mobile Compatibility', 'url' => '/ops/mobile-compatibility.php', 'desc' => 'Explains desktop vs mobile support boundaries.', 'mode' => 'guide'],
        ['group' => 'Evidence', 'label' => 'Evidence Bundle', 'url' => '/ops/evidence-bundle.php', 'desc' => 'Visibility snapshots and evidence bundle review.', 'mode' => 'read-only'],
        ['group' => 'Evidence', 'label' => 'Evidence Report', 'url' => '/ops/evidence-report.php', 'desc' => 'Evidence report for test/live observation.', 'mode' => 'read-only'],
        ['group' => 'Docs', 'label' => 'Documentation Center', 'url' => '/ops/documentation-center.php', 'desc' => 'Central operator documentation and route index.', 'mode' => 'read-only'],
        ['group' => 'Docs', 'label' => 'Tool Inventory', 'url' => '/ops/tool-inventory.php', 'desc' => 'Safe file inventory and fingerprints.', 'mode' => 'read-only'],
        ['group' => 'Docs', 'label' => 'System Status', 'url' => '/ops/system-status.php', 'desc' => 'Safe system status, DB ping, and table presence.', 'mode' => 'read-only'],
        ['group' => 'Docs', 'label' => 'Deployment Center', 'url' => '/ops/deployment-center.php', 'desc' => 'Manual cPanel/GitHub Desktop deployment checklist.', 'mode' => 'guide'],
        ['group' => 'Docs', 'label' => 'Handoff Center', 'url' => '/ops/handoff-center.php', 'desc' => 'Copy/paste continuity prompt for a new session.', 'mode' => 'guide'],
        ['group' => 'Admin', 'label' => 'Admin Control', 'url' => '/ops/admin-control.php', 'desc' => 'Administration hub for readiness, mappings, and jobs.', 'mode' => 'admin'],
        ['group' => 'Admin', 'label' => 'Readiness Control', 'url' => '/ops/readiness-control.php', 'desc' => 'Readiness state and safety checks.', 'mode' => 'admin'],
        ['group' => 'Admin', 'label' => 'Mapping Review', 'url' => '/ops/mapping-control.php', 'desc' => 'Driver/vehicle mapping review.', 'mode' => 'admin'],
        ['group' => 'Admin', 'label' => 'Jobs Review', 'url' => '/ops/jobs-control.php', 'desc' => 'Submission/dry-run job visibility.', 'mode' => 'admin'],
        ['group' => 'Admin', 'label' => 'Users Control', 'url' => '/ops/users-control.php', 'desc' => 'Admin-only user overview and management.', 'mode' => 'admin'],
        ['group' => 'Admin', 'label' => 'Activity Center', 'url' => '/ops/activity-center.php', 'desc' => 'Admin-only activity overview.', 'mode' => 'admin'],
        ['group' => 'Admin', 'label' => 'Audit Log', 'url' => '/ops/audit-log.php', 'desc' => 'Admin-only operator audit log.', 'mode' => 'admin'],
        ['group' => 'Admin', 'label' => 'Login Attempts', 'url' => '/ops/login-attempts.php', 'desc' => 'Admin-only authentication attempt visibility.', 'mode' => 'admin'],
        ['group' => 'Profile', 'label' => 'Operator Profile', 'url' => '/ops/profile.php', 'desc' => 'Current logged-in operator profile.', 'mode' => 'profile'],
        ['group' => 'Profile', 'label' => 'Edit Profile', 'url' => '/ops/profile-edit.php', 'desc' => 'Edit your display name and email.', 'mode' => 'profile'],
        ['group' => 'Profile', 'label' => 'Preferences', 'url' => '/ops/profile-preferences.php', 'desc' => 'Set UI preferences and landing route.', 'mode' => 'profile'],
        ['group' => 'Profile', 'label' => 'Change Password', 'url' => '/ops/profile-password.php', 'desc' => 'Change your own operator password.', 'mode' => 'profile'],
        ['group' => 'Profile', 'label' => 'My Activity', 'url' => '/ops/profile-activity.php', 'desc' => 'Your recent login and audit activity.', 'mode' => 'profile'],
    ];
}

function gql_file_status(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH);
    $path = is_string($path) ? $path : '';
    if ($path === '') {
        return 'unknown';
    }
    $file = dirname(__DIR__) . $path;
    return is_file($file) ? 'available' : 'missing';
}

function gql_badge_for_mode(string $mode): string
{
    return match ($mode) {
        'production' => opsui_badge('PRODUCTION', 'warn'),
        'dev' => opsui_badge('DEV', 'neutral'),
        'admin' => opsui_badge('ADMIN', 'warn'),
        'guarded' => opsui_badge('GUARDED', 'warn'),
        'download' => opsui_badge('DOWNLOAD', 'neutral'),
        'profile' => opsui_badge('PROFILE', 'neutral'),
        'guide' => opsui_badge('GUIDE', 'good'),
        'review' => opsui_badge('REVIEW', 'good'),
        default => opsui_badge(strtoupper($mode), 'good'),
    };
}

$routes = gql_routes();
$groups = [];
foreach ($routes as $route) {
    $groups[$route['group']][] = $route;
}

opsui_shell_begin([
    'title' => 'Quick Launch',
    'page_title' => 'Quick Launch',
    'active_section' => 'Quick Launch',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Quick Launch',
    'subtitle' => 'Fast route launcher for the shared operations console.',
    'safe_notice' => 'This launcher is read-only. It only links to existing protected /ops routes and does not call Bolt, EDXEIX, or AADE.',
]);
?>
<section class="card hero neutral">
    <h1>Ops Quick Launch</h1>
    <p>Use this page to find the correct operator route without crowding the top navigation. The production pre-ride tool remains the live staff route; V2 stays for development.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO WORKFLOW ACTIONS', 'good') ?>
        <?= opsui_badge('PRODUCTION TOOL UNCHANGED', 'good') ?>
    </div>
</section>

<section class="card">
    <h2>Search routes</h2>
    <div class="gov-form-grid gov-form-grid-3">
        <div class="field">
            <label for="routeSearch">Search</label>
            <input id="routeSearch" type="search" placeholder="pre-ride, helper, users, audit, mobile..." autocomplete="off">
        </div>
        <div class="field">
            <label for="routeGroup">Group</label>
            <select id="routeGroup">
                <option value="">All groups</option>
                <?php foreach (array_keys($groups) as $group): ?>
                    <option value="<?= opsui_h(strtolower($group)) ?>"><?= opsui_h($group) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label>&nbsp;</label>
            <button class="btn dark" type="button" onclick="gqlResetSearch()">Reset</button>
        </div>
    </div>
</section>

<?php foreach ($groups as $group => $items): ?>
<section class="card gql-route-section" data-group="<?= opsui_h(strtolower($group)) ?>">
    <h2><?= opsui_h($group) ?></h2>
    <div class="gov-admin-grid gql-route-grid">
        <?php foreach ($items as $route):
            $status = gql_file_status($route['url']);
            $search = strtolower($group . ' ' . $route['label'] . ' ' . $route['desc'] . ' ' . $route['url'] . ' ' . $route['mode']);
            ?>
            <a class="gov-admin-link gql-route-card" href="<?= opsui_h($route['url']) ?>" data-search="<?= opsui_h($search) ?>" data-group="<?= opsui_h(strtolower($group)) ?>">
                <strong><?= opsui_h($route['label']) ?></strong>
                <span><?= opsui_h($route['desc']) ?></span>
                <div class="gql-route-meta">
                    <code><?= opsui_h($route['url']) ?></code><br>
                    <?= gql_badge_for_mode($route['mode']) ?>
                    <?= $status === 'available' ? opsui_badge('AVAILABLE', 'good') : opsui_badge('CHECK FILE', 'warn') ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endforeach; ?>

<style>
.gov-form-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;align-items:end}.gov-form-grid .field{display:flex;flex-direction:column;gap:6px}.gov-form-grid label{font-weight:700;color:#27385f}.gov-form-grid input,.gov-form-grid select{width:100%;border:1px solid #d8dde7;border-radius:4px;padding:11px 12px;font-size:15px;background:#fff;color:#162b5b}.gql-route-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.gql-route-meta{margin-top:12px}.gql-route-card[hidden],.gql-route-section[hidden]{display:none!important}@media(max-width:1180px){.gql-route-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media(max-width:760px){.gov-form-grid,.gql-route-grid{grid-template-columns:1fr}}
</style>
<script>
(function(){
    const input = document.getElementById('routeSearch');
    const group = document.getElementById('routeGroup');
    const cards = Array.from(document.querySelectorAll('.gql-route-card'));
    const sections = Array.from(document.querySelectorAll('.gql-route-section'));
    function apply(){
        const q = String(input && input.value || '').toLowerCase().trim();
        const g = String(group && group.value || '').toLowerCase().trim();
        cards.forEach(card => {
            const okSearch = !q || String(card.dataset.search || '').includes(q);
            const okGroup = !g || String(card.dataset.group || '') === g;
            card.hidden = !(okSearch && okGroup);
        });
        sections.forEach(section => {
            const visible = Array.from(section.querySelectorAll('.gql-route-card')).some(card => !card.hidden);
            section.hidden = !visible;
        });
    }
    window.gqlResetSearch = function(){ if(input){input.value='';} if(group){group.value='';} apply(); };
    if(input){ input.addEventListener('input', apply); }
    if(group){ group.addEventListener('change', apply); }
    apply();
})();
</script>
<?php
opsui_shell_end();
