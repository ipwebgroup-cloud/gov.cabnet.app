<?php
/**
 * gov.cabnet.app — Public Utility Relocation Plan.
 *
 * Read-only no-break plan for guarded public-root utility endpoints.
 */

declare(strict_types=1);

require_once __DIR__ . '/_shell.php';

$homeRoot = dirname(__DIR__, 3);
$cliPath = $homeRoot . '/gov.cabnet.app_app/cli/public_utility_relocation_plan.php';
if (!is_readable($cliPath)) {
    http_response_code(500);
    echo 'Public utility relocation plan CLI is missing or unreadable.';
    exit;
}
require_once $cliPath;

$report = gov_public_utility_relocation_plan_run();
$summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
$routes = is_array($report['routes'] ?? null) ? $report['routes'] : [];
$commands = is_array($report['operator_dependency_check_commands'] ?? null) ? $report['operator_dependency_check_commands'] : [];

function purp_h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function purp_yesno(bool $value): string
{
    return $value ? 'yes' : 'no';
}

opsui_shell_begin([
    'title' => 'Public Utility Relocation Plan',
    'page_title' => 'Public Utility Relocation Plan',
    'active_section' => 'Developer Archive',
    'breadcrumbs' => 'Αρχική / Διαχειριστικό / Public Utility Relocation Plan',
    'force_safe_notice' => true,
    'safe_notice' => 'READ-ONLY PLAN. No route moves, no deletions, no DB, no Bolt, no EDXEIX, no AADE, and no filesystem writes. Classifies dependency evidence before any relocation of guarded public-root utilities.',
]);
?>
<section class="card hero neutral">
    <h1>Public Utility Relocation Plan</h1>
    <p>Plans the later no-break relocation of guarded public-root utility endpoints into private CLI or supervised /ops routes, while showing current dependency evidence. This page does not move or disable anything.</p>
    <div>
        <?= opsui_badge('READ ONLY', 'good') ?>
        <?= opsui_badge('NO DELETE', 'good') ?>
        <?= opsui_badge('NO DB', 'good') ?>
        <?= opsui_badge('NO EDXEIX CALL', 'good') ?>
        <?= opsui_badge((string)($report['version'] ?? ''), 'info') ?>
    </div>
    <div class="actions">
        <a class="btn" href="/ops/public-utility-relocation-plan.php">Refresh</a>
        <a class="btn dark" href="/ops/public-route-exposure-audit.php">Public Route Exposure Audit</a>
        <a class="btn dark" href="/ops/route-index.php">Route Index</a>
    </div>
</section>

<section class="grid">
    <?= opsui_metric((string)($summary['target_public_utilities'] ?? 0), 'target public utilities') ?>
    <?= opsui_metric((string)($summary['delete_recommended_now'] ?? 0), 'delete recommended now') ?>
    <?= opsui_metric((string)($summary['move_recommended_now'] ?? 0), 'move recommended now') ?>
    <?= opsui_metric((string)($summary['requires_cron_or_bookmark_check'] ?? 0), 'require dependency check') ?>
    <?= opsui_metric((string)($summary['blocking_dependency_reference_count'] ?? 0), 'blocking refs found') ?>
</section>

<section class="card">
    <h2>No-break dependency checks</h2>
    <p>Before any relocation, check whether these public-root utility URLs are used by cron, monitors, bookmarks, helper tools, or old operator workflows.</p>
    <?php foreach ($commands as $label => $command): ?>
        <h3><?= purp_h(str_replace('_', ' ', (string)$label)) ?></h3>
        <pre style="white-space:pre-wrap;word-break:break-word;border:1px solid #d8dde7;border-radius:6px;background:#f7f9fc;padding:12px;"><?= purp_h($command) ?></pre>
    <?php endforeach; ?>
</section>

<section class="card">
    <h2>Relocation candidates</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Current route</th>
                    <th>Role</th>
                    <th>Recommended target</th>
                    <th>Tokens</th>
                    <th>Dependency evidence</th>
                    <th>Safe action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($routes as $route): ?>
                <?php
                if (!is_array($route)) {
                    continue;
                }
                $tokens = is_array($route['tokens'] ?? null) ? $route['tokens'] : [];
                $activeTokens = [];
                foreach ($tokens as $key => $value) {
                    if ($value) {
                        $activeTokens[] = (string)$key;
                    }
                }
                ?>
                <tr>
                    <td>
                        <strong><?= purp_h($route['current_route'] ?? '') ?></strong><br>
                        <span class="small"><?= purp_h($route['file'] ?? '') ?></span><br>
                        <?= !empty($route['file_meta']['exists']) ? opsui_badge('present', 'good') : opsui_badge('missing', 'warn') ?>
                    </td>
                    <td>
                        <?= purp_h($route['role'] ?? '') ?><br>
                        <span class="small"><?= purp_h($route['current_mode'] ?? '') ?></span>
                    </td>
                    <td>
                        <?= opsui_badge((string)($route['recommended_target'] ?? ''), 'info') ?><br>
                        <code><?= purp_h($route['target_path'] ?? '') ?></code><br>
                        <span class="small"><?= purp_h($route['why'] ?? '') ?></span>
                    </td>
                    <td>
                        <?php if ($activeTokens): ?>
                            <?php foreach ($activeTokens as $token): ?><?= opsui_badge($token, 'neutral') ?> <?php endforeach; ?>
                        <?php else: ?>
                            <?= opsui_badge('no notable tokens', 'good') ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= !empty($route['requires_cron_or_bookmark_check_before_move']) ? opsui_badge('dependency review first', 'warn') : opsui_badge('lower dependency risk', 'good') ?><br>
                        <span class="small">External refs: <?= purp_h($route['external_project_reference_count'] ?? 0) ?></span><br>
                        <span class="small">Blocking refs: <?= purp_h($route['blocking_dependency_reference_count'] ?? 0) ?></span>
                        <?php $kinds = is_array($route['reference_kinds'] ?? null) ? $route['reference_kinds'] : []; ?>
                        <?php if ($kinds): ?>
                            <br><span class="small">Kinds: <?= purp_h(json_encode($kinds, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <strong>Move now:</strong> <?= purp_h(purp_yesno(!empty($route['move_now']))) ?><br>
                        <strong>Delete now:</strong> <?= purp_h(purp_yesno(!empty($route['delete_now']))) ?><br>
                        <span class="small"><?= purp_h($route['safe_next_action'] ?? '') ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="card">
    <h2>Dependency evidence samples</h2>
    <p class="small">These samples explain why no move is recommended now. Inventory/planner self-references are filtered out of the blocking count where possible.</p>
    <?php foreach ($routes as $route): ?>
        <?php if (!is_array($route)) { continue; } ?>
        <?php $samples = is_array($route['blocking_dependency_references_sample'] ?? null) ? $route['blocking_dependency_references_sample'] : []; ?>
        <?php if (!$samples) { continue; } ?>
        <h3><?= purp_h($route['current_route'] ?? '') ?></h3>
        <ul class="list">
            <?php foreach ($samples as $sample): ?>
                <?php if (!is_array($sample)) { continue; } ?>
                <li>
                    <code><?= purp_h($sample['path'] ?? '') ?>:<?= purp_h($sample['line'] ?? '') ?></code><br>
                    <span class="small"><?= purp_h($sample['preview'] ?? '') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endforeach; ?>
</section>

<section class="card">
    <h2>Recommended relocation sequence</h2>
    <ol class="list">
        <li>Run the dependency search commands above as root and paste the output for review.</li>
        <li>For active cron utilities, create private CLI equivalents first and update cron to the private app path.</li>
        <li>Keep authenticated compatibility stubs in the old public-root locations until no references remain.</li>
        <li>Only after a quiet period, replace old public-root utilities with archived notices or remove them with explicit approval.</li>
    </ol>
    <p class="small"><strong>Current recommendation:</strong> <?= purp_h($report['recommended_next_step'] ?? '') ?></p>
</section>
<?php opsui_shell_end(); ?>
