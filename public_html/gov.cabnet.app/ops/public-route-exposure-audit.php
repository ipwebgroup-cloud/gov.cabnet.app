<?php
/**
 * gov.cabnet.app — Public Route Exposure Audit.
 *
 * Read-only ops page. No Bolt/EDXEIX/AADE/DB calls and no writes.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once __DIR__ . '/_shell.php';

$homeRoot = dirname(__DIR__, 3);
$cliPath = $homeRoot . '/gov.cabnet.app_app/cli/public_route_exposure_audit.php';
if (!is_readable($cliPath)) {
    http_response_code(500);
    echo 'Public route exposure audit CLI is missing or unreadable.';
    exit;
}
require_once $cliPath;

$report = gov_pra_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$auth = is_array($report['auth_posture'] ?? null) ? $report['auth_posture'] : [];
$authChecks = is_array($auth['checks'] ?? null) ? $auth['checks'] : [];
$routes = is_array($report['public_root_routes'] ?? null) ? $report['public_root_routes'] : [];
$opsSummary = is_array($report['ops_summary'] ?? null) ? $report['ops_summary'] : [];
$warnings = is_array($report['warnings'] ?? null) ? $report['warnings'] : [];
$blocks = is_array($report['final_blocks'] ?? null) ? $report['final_blocks'] : [];

function pra_badge_bool(bool $ok, string $yes = 'YES', string $no = 'NO'): string
{
    return opsui_badge($ok ? $yes : $no, $ok ? 'good' : 'bad');
}

function pra_class_badge(string $classification): string
{
    $type = 'neutral';
    if (str_starts_with($classification, 'keep')) {
        $type = 'good';
    } elseif (str_starts_with($classification, 'locked')) {
        $type = 'warn';
    } elseif (str_contains($classification, 'internal')) {
        $type = 'neutral';
    }
    return opsui_badge(strtoupper(str_replace('_', ' ', $classification)), $type);
}

opsui_shell_begin([
    'title' => 'Public Route Exposure Audit',
    'page_title' => 'Public Route Exposure Audit',
    'active_section' => 'Developer archive',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Public Route Exposure Audit',
    'safe_notice' => 'READ-ONLY AUDIT. No Bolt, EDXEIX, AADE, DB, or write actions. Checks public-root PHP endpoints and global auth-prepend posture.',
]);
?>
<section class="card hero neutral">
    <h1>Public Route Exposure Audit</h1>
    <p>Scans public-root PHP endpoints and verifies that the global <code>.user.ini</code> auth prepend still protects utility endpoints.</p>
    <div>
        <?= opsui_badge(!empty($report['ok']) ? 'AUDIT OK' : 'AUDIT BLOCKED', !empty($report['ok']) ? 'good' : 'bad') ?>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO DB', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
        <?= opsui_badge((string)($report['version'] ?? ''), 'neutral') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/public-route-exposure-audit.php">Refresh</a>
        <a class="btn dark" href="/ops/route-index.php">Route Index</a>
        <a class="btn dark" href="/ops/handoff-center.php">Handoff Center</a>
    </div>
</section>

<section class="grid cols-4">
    <div class="metric"><strong><?= opsui_h($summary['public_root_php_count'] ?? 0) ?></strong><span>public-root PHP files</span></div>
    <div class="metric"><strong><?= opsui_h($summary['ops_php_count'] ?? 0) ?></strong><span>ops PHP routes</span></div>
    <div class="metric"><strong><?= opsui_h($summary['guarded_public_utility_count'] ?? 0) ?></strong><span>guarded public utilities</span></div>
    <div class="metric"><strong><?= opsui_h($summary['delete_recommended_now'] ?? 0) ?></strong><span>delete recommended now</span></div>
</section>

<?php if ($blocks): ?>
<section class="card" style="border-left:6px solid #b42318;">
    <h2>Final blocks</h2>
    <ul class="list"><?php foreach ($blocks as $block): ?><li><?= opsui_h($block) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<section class="two">
    <div class="card">
        <h2>Auth prepend posture</h2>
        <div class="table-wrap">
            <table>
                <tbody>
                <?php foreach ($authChecks as $key => $value): ?>
                    <tr>
                        <td><strong><?= opsui_h(str_replace('_', ' ', (string)$key)) ?></strong></td>
                        <td><?= pra_badge_bool((bool)$value) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr><td><strong>auto prepend path</strong></td><td><code><?= opsui_h($auth['auto_prepend_path'] ?? '') ?></code></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card">
        <h2>Ops route surface</h2>
        <p><strong><?= opsui_h($opsSummary['php_route_count'] ?? 0) ?></strong> ops PHP routes found.</p>
        <p><strong><?= opsui_h($opsSummary['developer_like_count'] ?? 0) ?></strong> developer/test/probe-like routes.</p>
        <p><strong><?= opsui_h($opsSummary['submit_like_count'] ?? 0) ?></strong> submit/stage/live-like routes.</p>
        <p class="small">These remain behind ops auth. This page does not delete or disable anything.</p>
    </div>
</section>

<?php if ($warnings): ?>
<section class="card" style="border-left:6px solid #b7791f;">
    <h2>Warnings / follow-up</h2>
    <ul class="list"><?php foreach ($warnings as $warning): ?><li><?= opsui_h($warning) ?></li><?php endforeach; ?></ul>
</section>
<?php endif; ?>

<section class="card">
    <h2>Public-root PHP route inventory</h2>
    <p class="small">These are PHP files directly under <code>/public_html/gov.cabnet.app</code>, outside <code>/ops</code>. They are protected by global auth-prepend when PHP reads <code>.user.ini</code>.</p>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Route</th>
                    <th>Classification</th>
                    <th>Risk</th>
                    <th>Write tokens</th>
                    <th>Network tokens</th>
                    <th>Recommended action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($routes as $route): ?>
                <tr>
                    <td><code><?= opsui_h($route['route'] ?? '') ?></code></td>
                    <td><?= pra_class_badge((string)($route['classification'] ?? 'review')) ?></td>
                    <td><?= opsui_h($route['risk'] ?? '') ?></td>
                    <td><?= empty($route['write_tokens']) ? opsui_badge('NONE', 'good') : opsui_badge(implode(', ', (array)$route['write_tokens']), 'warn') ?></td>
                    <td><?= empty($route['network_tokens']) ? opsui_badge('NONE', 'good') : opsui_badge(implode(', ', (array)$route['network_tokens']), 'warn') ?></td>
                    <td><?= opsui_h($route['recommended_action'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Next safe action</h2>
    <p><?= opsui_h($report['next_safe_action'] ?? '') ?></p>
</section>
<?php opsui_shell_end(); ?>
