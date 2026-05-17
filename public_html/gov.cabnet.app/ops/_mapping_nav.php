<?php
/**
 * gov.cabnet.app — Mapping navigation partial v1.3
 *
 * Include-only helper for Bolt → EDXEIX mapping governance pages.
 * No DB access, no external calls, no writes. v1.3 adds the Admin Excluded vehicle audit link.
 */

declare(strict_types=1);

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

if (!function_exists('gov_mapping_nav_h')) {
    function gov_mapping_nav_h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('gov_mapping_nav_current')) {
    function gov_mapping_nav_current(): string
    {
        $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        return is_string($path) && $path !== '' ? $path : '';
    }
}

if (!function_exists('gov_mapping_nav_active')) {
    function gov_mapping_nav_active(string $href, ?string $current = null): string
    {
        $current = $current ?? gov_mapping_nav_current();
        return $current === $href ? ' active' : '';
    }
}

if (!function_exists('gov_mapping_nav_link')) {
    function gov_mapping_nav_link(string $href, string $label, string $desc = '', ?string $current = null): string
    {
        $html = '<a class="mapping-nav-link' . gov_mapping_nav_active($href, $current) . '" href="' . gov_mapping_nav_h($href) . '">';
        $html .= '<strong>' . gov_mapping_nav_h($label) . '</strong>';
        if ($desc !== '') {
            $html .= '<span>' . gov_mapping_nav_h($desc) . '</span>';
        }
        $html .= '</a>';
        return $html;
    }
}

if (!function_exists('gov_mapping_nav_render')) {
    function gov_mapping_nav_render(?string $current = null): void
    {
        $current = $current ?? gov_mapping_nav_current();
        ?>
        <style>
            .mapping-nav-card{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:14px 16px;margin:0 0 16px;box-shadow:0 6px 18px rgba(26,33,52,.05)}
            .mapping-nav-title{display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:10px}
            .mapping-nav-title h2{font-size:18px;margin:0;color:#102b5c}.mapping-nav-title span{color:#667085;font-size:13px}
            .mapping-nav-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
            .mapping-nav-link{display:block;text-decoration:none;border:1px solid #d8dde7;border-radius:5px;background:#fbfcff;padding:10px 12px;color:#1d355f;min-height:72px}
            .mapping-nav-link:hover,.mapping-nav-link.active{background:#eef2ff;border-color:#4f5ea7;text-decoration:none}
            .mapping-nav-link strong{display:block;font-size:14px;margin-bottom:4px}.mapping-nav-link span{display:block;color:#667085;font-size:12px;line-height:1.25}
            .mapping-nav-badge{display:inline-flex;background:#ecfdf3;color:#166534;border:1px solid #bbf7d0;border-radius:999px;padding:5px 9px;font-weight:700;font-size:12px;white-space:nowrap}
            @media(max-width:1120px){.mapping-nav-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
            @media(max-width:700px){.mapping-nav-grid{grid-template-columns:1fr}.mapping-nav-title{display:block}.mapping-nav-title span{display:block;margin-top:5px}}
        </style>
        <section class="mapping-nav-card">
            <div class="mapping-nav-title">
                <div>
                    <h2>Mapping tools</h2>
                    <span>Use these pages before trusting a driver/vehicle/lessor combination in production.</span>
                </div>
                <span class="mapping-nav-badge">Mapping subsystem</span>
            </div>
            <div class="mapping-nav-grid">
                <?= gov_mapping_nav_link('/ops/mapping-center.php', 'Mapping Center', 'Main hub for mapping tools.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mapping-workbench-v3.php', 'Workbench V3', 'Driver + vehicle + lessor verified workflow.', $current) ?>
                <?= gov_mapping_nav_link('/ops/pre-ride-email-v3-emt8640-exemption-audit.php', 'Admin Exclusions', 'EMT8640 / Sprinter no invoice, no driver mail.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mapping-health.php', 'Mapping Health', 'Read-only health dashboard.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mapping-audit.php', 'Mapping Audit', 'Failure-point audit by lessor.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mapping-exceptions.php', 'Exception Queue', 'Prioritized issues to fix.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mapping-resolver-test.php', 'Resolver Test', 'Test driver + vehicle pair.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mapping-verification.php', 'Verification Register', 'Record verified decisions.', $current) ?>
                <?= gov_mapping_nav_link('/ops/company-mapping-control.php', 'Company Control', 'Lessor/company overview.', $current) ?>
                <?= gov_mapping_nav_link('/ops/starting-point-control.php', 'Starting Points', 'Manage lessor overrides.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mapping-control.php', 'Legacy Review', 'Original read-only overview.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mappings.php', 'Legacy Editor', 'Guarded mapping editor.', $current) ?>
                <?= gov_mapping_nav_link('/ops/mappings.php?format=json', 'Mapping JSON', 'Raw mapping JSON view.', $current) ?>
                <?= gov_mapping_nav_link('/ops/readiness-control.php', 'Readiness', 'Overall readiness status.', $current) ?>
            </div>
        </section>
        <?php
    }
}
