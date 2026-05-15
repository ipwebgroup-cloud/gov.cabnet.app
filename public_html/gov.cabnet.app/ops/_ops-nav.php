<?php
/**
 * gov.cabnet.app — Shared Operations Shell Navigation
 *
 * Additive UI helper for Ops pages.
 * - Read-only.
 * - No database access.
 * - No external calls.
 * - Safe to include from /public_html/gov.cabnet.app/ops/*.php.
 *
 * v3.2.5 keeps the read-only V3 Real Future Candidate Capture Readiness page in shared legacy Ops navigation, adds sanitized evidence snapshot, EDXEIX payload preview/dry-run preflight, and expired candidate safety regression audit support, controlled live-submit readiness checklist support, and adds no new live-submit pathway; V0 remains untouched and live submit remains disabled. The shell follows the established Ops Home palette:
 * white top navigation, deep-blue left sidebar, light content canvas,
 * tabs, cards, and consistent safety badges.
 */

declare(strict_types=1);

if (!function_exists('gov_ops_h')) {
    function gov_ops_h($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('gov_ops_badge')) {
    function gov_ops_badge(string $text, string $type = 'neutral'): string
    {
        $allowed = ['good', 'warn', 'bad', 'neutral', 'info', 'dark', 'soft'];
        if (!in_array($type, $allowed, true)) {
            $type = 'neutral';
        }

        return '<span class="ops-badge ops-badge-' . gov_ops_h($type) . '">' . gov_ops_h($text) . '</span>';
    }
}

if (!function_exists('gov_ops_top_nav_items')) {
    /** @return array<int,array<string,string>> */
    function gov_ops_top_nav_items(): array
    {
        return [
            ['key' => 'home', 'label' => 'ΑΡΧΙΚΗ', 'href' => '/ops/home.php'],
            ['key' => 'my_start', 'label' => 'MY START', 'href' => '/ops/home.php#my-start'],
            ['key' => 'launch', 'label' => 'LAUNCH', 'href' => '/ops/home.php#launch'],
            ['key' => 'pre_ride', 'label' => 'PRE-RIDE', 'href' => '/ops/pre-ride-email-v3-dashboard.php'],
            ['key' => 'workflow', 'label' => 'WORKFLOW', 'href' => '/ops/home.php#workflow'],
            ['key' => 'helper', 'label' => 'HELPER', 'href' => '/ops/home.php#helper'],
            ['key' => 'docs', 'label' => 'DOCS', 'href' => '/ops/home.php#docs'],
            ['key' => 'admin', 'label' => 'ADMIN', 'href' => '/ops/home.php#admin'],
            ['key' => 'profile', 'label' => 'PROFILE', 'href' => '/ops/profile.php'],
        ];
    }
}

if (!function_exists('gov_ops_side_nav_sections')) {
    /** @return array<int,array<string,mixed>> */
    function gov_ops_side_nav_sections(): array
    {
        return [
            [
                'label' => 'Primary Workflow',
                'items' => [
                    ['key' => 'ops_home', 'label' => 'Ops Home', 'href' => '/ops/home.php', 'hint' => 'Safe Bolt → EDXEIX operator landing page'],
                    ['key' => 'v3_dashboard', 'label' => 'V3 Control Center', 'href' => '/ops/pre-ride-email-v3-dashboard.php', 'hint' => 'Current pre-ride automation monitor'],
                    ['key' => 'v3_monitor', 'label' => 'V3 Compact Monitor', 'href' => '/ops/pre-ride-email-v3-monitor.php', 'hint' => 'Fast read-only V3 status view'],
                    ['key' => 'v3_queue_focus', 'label' => 'V3 Queue Focus', 'href' => '/ops/pre-ride-email-v3-queue-focus.php', 'hint' => 'Newest V3 rows and status reasons'],
                    ['key' => 'v3_pulse_focus', 'label' => 'V3 Pulse Focus', 'href' => '/ops/pre-ride-email-v3-pulse-focus.php', 'hint' => 'Pulse cron log and lock visibility'],
                    ['key' => 'v3_readiness_focus', 'label' => 'V3 Readiness Focus', 'href' => '/ops/pre-ride-email-v3-readiness-focus.php', 'hint' => 'Readiness gates, queue, mappings, and pulse overview'],
                    ['key' => 'queue_watch', 'label' => 'Queue Watch', 'href' => '/ops/pre-ride-email-v3-queue-watch.php'],
                    ['key' => 'pulse_runner', 'label' => 'Pulse Runner', 'href' => '/ops/pre-ride-email-v3-fast-pipeline-pulse.php'],
                    ['key' => 'automation_readiness', 'label' => 'Automation Readiness', 'href' => '/ops/pre-ride-email-v3-automation-readiness.php'],
                    ['key' => 'future_candidate_capture', 'label' => 'Future Candidate Capture', 'href' => '/ops/pre-ride-email-v3-real-future-candidate-capture-readiness.php', 'hint' => 'Read-only real future candidate capture readiness'],
                ],
            ],
            [
                'label' => 'Pre-Ride Safety',
                'items' => [
                    ['key' => 'starting_point_guard', 'label' => 'Starting-Point Guard', 'href' => '/ops/pre-ride-email-v3-starting-point-guard.php'],
                    ['key' => 'expiry_guard', 'label' => 'Expiry Guard', 'href' => '/ops/pre-ride-email-v3-expiry-guard.php'],
                    ['key' => 'live_readiness', 'label' => 'Live Readiness', 'href' => '/ops/pre-ride-email-v3-live-readiness.php'],
                    ['key' => 'payload_audit', 'label' => 'Payload Audit', 'href' => '/ops/pre-ride-email-v3-live-payload-audit.php'],
                ],
            ],
            [
                'label' => 'Live Submit Locked',
                'items' => [
                    ['key' => 'submit_gate', 'label' => 'Submit Gate', 'href' => '/ops/pre-ride-email-v3-live-submit-gate.php'],
                    ['key' => 'submit_scaffold', 'label' => 'Live Submit Scaffold', 'href' => '/ops/pre-ride-email-v3-live-submit.php'],
                ],
            ],
            [
                'label' => 'Bolt Bridge',
                'items' => [
                    ['key' => 'bolt_live', 'label' => 'Bolt Live', 'href' => '/ops/bolt-live.php'],
                    ['key' => 'legacy_readiness', 'label' => 'Legacy Readiness', 'href' => '/ops/readiness.php'],
                    ['key' => 'jobs_queue', 'label' => 'Jobs Queue', 'href' => '/ops/jobs.php'],
                    ['key' => 'legacy_submit', 'label' => 'Legacy Submit', 'href' => '/ops/submit.php'],
                ],
            ],
            [
                'label' => 'Evidence & Diagnostics',
                'items' => [
                    ['key' => 'cron_health', 'label' => 'Cron Health', 'href' => '/ops/pre-ride-email-v3-cron-health.php'],
                    ['key' => 'fast_pipeline', 'label' => 'Fast Pipeline', 'href' => '/ops/pre-ride-email-v3-fast-pipeline.php'],
                    ['key' => 'v3_storage_check', 'label' => 'V3 Storage Check', 'href' => '/ops/pre-ride-email-v3-storage-check.php'],
                    ['key' => 'queue', 'label' => 'V3 Queue', 'href' => '/ops/pre-ride-email-v3-queue.php'],
                    ['key' => 'readiness_json', 'label' => 'Readiness JSON', 'href' => '/bolt_readiness_audit.php'],
                    ['key' => 'preflight_json', 'label' => 'Preflight JSON', 'href' => '/bolt_edxeix_preflight.php?limit=30'],
                ],
            ],
        ];
    }
}

if (!function_exists('gov_ops_render_topbar')) {
    function gov_ops_render_topbar(string $activeTopKey = 'pre_ride'): void
    {
        $items = gov_ops_top_nav_items();
        ?>
        <header class="ops-shell-topbar">
            <a class="ops-shell-brand" href="/ops/home.php" aria-label="gov.cabnet.app operations home">
                <span class="ops-shell-logo">EA</span>
                <span class="ops-shell-brand-text"><strong>gov.cabnet.app</strong><em>Bolt → EDXEIX operational console</em></span>
            </a>
            <nav class="ops-shell-topnav" aria-label="Primary operations navigation">
                <?php foreach ($items as $item): ?>
                    <?php $isActive = (($item['key'] ?? '') === $activeTopKey); ?>
                    <a class="<?= $isActive ? 'active' : '' ?>" href="<?= gov_ops_h($item['href'] ?? '#') ?>"><?= gov_ops_h($item['label'] ?? '') ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="ops-shell-user">
                <span class="ops-shell-user-mark">A</span>
                <span><strong>ANDREAS</strong><em>ADMIN</em></span>
            </div>
        </header>
        <?php
    }
}

if (!function_exists('gov_ops_render_sidebar')) {
    function gov_ops_render_sidebar(string $activeKey = ''): void
    {
        $sections = gov_ops_side_nav_sections();
        ?>
        <aside class="ops-shell-sidebar" aria-label="Ops section navigation">
            <section class="ops-operator-card">
                <div class="ops-operator-top">
                    <span class="ops-operator-avatar">A</span>
                    <span><strong>Andreas</strong><em>admin</em></span>
                </div>
                <div class="ops-operator-actions">
                    <a href="/ops/profile.php">Profile</a>
                    <a href="/ops/profile.php#edit">Edit</a>
                    <a href="/ops/profile.php#password">Password</a>
                    <a href="/ops/logout.php">Logout</a>
                </div>
            </section>

            <?php foreach ($sections as $section): ?>
                <section class="ops-side-section">
                    <h3><?= gov_ops_h((string)($section['label'] ?? '')) ?></h3>
                    <?php foreach (($section['items'] ?? []) as $item): ?>
                        <?php $isActive = ($activeKey !== '' && $activeKey === ($item['key'] ?? '')); ?>
                        <a class="ops-side-link <?= $isActive ? 'active' : '' ?>" href="<?= gov_ops_h($item['href'] ?? '#') ?>">
                            <span><?= gov_ops_h($item['label'] ?? '') ?></span>
                        </a>
                        <?php if ($isActive && !empty($item['hint'])): ?>
                            <p class="ops-side-hint"><?= gov_ops_h((string)$item['hint']) ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </section>
            <?php endforeach; ?>
        </aside>
        <?php
    }
}

if (!function_exists('gov_ops_render_page_tabs')) {
    /** @param array<int,array<string,string>> $tabs */
    function gov_ops_render_page_tabs(array $tabs, string $activeKey): void
    {
        ?>
        <nav class="ops-page-tabs" aria-label="Page tabs">
            <?php foreach ($tabs as $tab): ?>
                <?php $isActive = (($tab['key'] ?? '') === $activeKey); ?>
                <a class="<?= $isActive ? 'active' : '' ?>" href="<?= gov_ops_h($tab['href'] ?? '#') ?>"><?= gov_ops_h($tab['label'] ?? '') ?></a>
            <?php endforeach; ?>
        </nav>
        <?php
    }
}
