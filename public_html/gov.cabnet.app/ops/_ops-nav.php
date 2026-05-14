<?php
/**
 * gov.cabnet.app — Shared Operations Navigation
 *
 * Additive helper for Ops UI pages.
 * - Read-only.
 * - No database access.
 * - No external network calls.
 * - Safe to include from /public_html/gov.cabnet.app/ops/*.php.
 */

declare(strict_types=1);

if (!function_exists('gov_ops_h')) {
    function gov_ops_h(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('gov_ops_badge')) {
    function gov_ops_badge(string $text, string $type = 'neutral'): string
    {
        $allowed = ['good', 'warn', 'bad', 'neutral', 'info', 'dark'];
        if (!in_array($type, $allowed, true)) {
            $type = 'neutral';
        }

        return '<span class="ops-badge ops-badge-' . gov_ops_h($type) . '">' . gov_ops_h($text) . '</span>';
    }
}

if (!function_exists('gov_ops_nav_items')) {
    /**
     * @return array<string, array<int, array{label:string,href:string,key:string,status?:string}>>
     */
    function gov_ops_nav_items(): array
    {
        return [
            'Operations' => [
                ['key' => 'ops_home', 'label' => 'Ops Home', 'href' => '/ops/index.php'],
                ['key' => 'v3_dashboard', 'label' => 'V3 Control Center', 'href' => '/ops/pre-ride-email-v3-dashboard.php', 'status' => 'new'],
            ],
            'V3 Automation' => [
                ['key' => 'v3_queue_watch', 'label' => 'Queue Watch', 'href' => '/ops/pre-ride-email-v3-queue-watch.php'],
                ['key' => 'v3_pulse', 'label' => 'Pulse Runner', 'href' => '/ops/pre-ride-email-v3-fast-pipeline-pulse.php'],
                ['key' => 'v3_fast_pipeline', 'label' => 'Fast Pipeline', 'href' => '/ops/pre-ride-email-v3-fast-pipeline.php'],
                ['key' => 'v3_queue', 'label' => 'Queue', 'href' => '/ops/pre-ride-email-v3-queue.php'],
                ['key' => 'v3_readiness', 'label' => 'Automation Readiness', 'href' => '/ops/pre-ride-email-v3-automation-readiness.php'],
                ['key' => 'v3_cron_health', 'label' => 'Cron Health', 'href' => '/ops/pre-ride-email-v3-cron-health.php'],
            ],
            'Safety Guards' => [
                ['key' => 'v3_starting_point_guard', 'label' => 'Starting-Point Guard', 'href' => '/ops/pre-ride-email-v3-starting-point-guard.php'],
                ['key' => 'v3_expiry_guard', 'label' => 'Expiry Guard', 'href' => '/ops/pre-ride-email-v3-expiry-guard.php'],
                ['key' => 'v3_live_readiness', 'label' => 'Live Readiness', 'href' => '/ops/pre-ride-email-v3-live-readiness.php'],
                ['key' => 'v3_payload_audit', 'label' => 'Payload Audit', 'href' => '/ops/pre-ride-email-v3-live-payload-audit.php'],
            ],
            'Live Submit Locked' => [
                ['key' => 'v3_live_scaffold', 'label' => 'Live Submit Scaffold', 'href' => '/ops/pre-ride-email-v3-live-submit.php'],
                ['key' => 'v3_live_gate', 'label' => 'Submit Gate', 'href' => '/ops/pre-ride-email-v3-live-submit-gate.php'],
            ],
            'Bolt Bridge' => [
                ['key' => 'bolt_live', 'label' => 'Bolt Live', 'href' => '/ops/bolt-live.php'],
                ['key' => 'legacy_readiness', 'label' => 'Legacy Readiness', 'href' => '/ops/readiness.php'],
                ['key' => 'jobs', 'label' => 'Jobs Queue', 'href' => '/ops/jobs.php'],
                ['key' => 'legacy_submit', 'label' => 'Legacy Submit', 'href' => '/ops/submit.php'],
            ],
            'Diagnostics' => [
                ['key' => 'readiness_json', 'label' => 'Readiness JSON', 'href' => '/bolt_readiness_audit.php'],
                ['key' => 'preflight_json', 'label' => 'Preflight JSON', 'href' => '/bolt_edxeix_preflight.php?limit=30'],
                ['key' => 'jobs_json', 'label' => 'Jobs JSON', 'href' => '/bolt_jobs_queue.php?limit=50'],
            ],
        ];
    }
}

if (!function_exists('gov_ops_render_nav')) {
    function gov_ops_render_nav(string $activeKey = ''): void
    {
        $items = gov_ops_nav_items();
        ?>
        <header class="ops-topbar">
            <div class="ops-brand">
                <div class="ops-brand-mark">GC</div>
                <div>
                    <strong>gov.cabnet.app</strong>
                    <span>Operations Console</span>
                </div>
            </div>
            <div class="ops-top-status">
                <?= gov_ops_badge('Production', 'dark') ?>
                <?= gov_ops_badge('Read-only UI', 'info') ?>
                <?= gov_ops_badge('Live submit disabled', 'bad') ?>
            </div>
        </header>
        <nav class="ops-nav" aria-label="Operations navigation">
            <?php foreach ($items as $section => $links): ?>
                <details class="ops-nav-section" open>
                    <summary><?= gov_ops_h($section) ?></summary>
                    <div class="ops-nav-links">
                        <?php foreach ($links as $link): ?>
                            <?php $isActive = ($activeKey !== '' && $activeKey === $link['key']); ?>
                            <a class="<?= $isActive ? 'active' : '' ?>" href="<?= gov_ops_h($link['href']) ?>">
                                <span><?= gov_ops_h($link['label']) ?></span>
                                <?php if (($link['status'] ?? '') === 'new'): ?>
                                    <?= gov_ops_badge('new', 'good') ?>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </nav>
        <?php
    }
}
