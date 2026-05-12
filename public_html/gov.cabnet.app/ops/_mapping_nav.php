<?php
/**
 * gov.cabnet.app — Mapping governance local navigation v1.0
 * Include-only helper for mapping governance pages.
 */
declare(strict_types=1);

if (realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

if (!function_exists('gmnav_h')) {
    function gmnav_h(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('gov_mapping_nav')) {
    /**
     * Render a compact mapping-only menu/dropdown that links all mapping governance pages.
     */
    function gov_mapping_nav(string $active = ''): void
    {
        $items = [
            'center' => ['/ops/mapping-center.php', 'Mapping Center', 'Main hub for all mapping tools'],
            'health' => ['/ops/mapping-health.php', 'Mapping Health', 'Read-only failure-point dashboard'],
            'companies' => ['/ops/company-mapping-control.php', 'Company Control', 'Lessor/company mapping overview'],
            'whiteblue' => ['/ops/company-mapping-detail.php?lessor=1756', 'WHITEBLUE Detail', 'Known verified lessor detail'],
            'starting' => ['/ops/starting-point-control.php', 'Starting Point Control', 'Admin starting-point overrides'],
            'verification' => ['/ops/mapping-verification.php', 'Verification Register', 'Record verified mapping decisions'],
            'review' => ['/ops/mapping-control.php', 'Mapping Review', 'Existing read-only driver/vehicle overview'],
            'editor' => ['/ops/mappings.php', 'Original Editor', 'Existing guarded mapping editor'],
            'json' => ['/ops/mappings.php?format=json', 'Mapping JSON', 'Existing JSON mapping output'],
            'readiness' => ['/ops/readiness-control.php', 'Readiness Control', 'Existing readiness overview'],
        ];
        ?>
        <style>
            .mapping-nav-wrap{background:#fff;border:1px solid #d8dde7;border-radius:6px;padding:14px 16px;margin:0 0 18px;box-shadow:0 6px 18px rgba(26,33,52,.05)}
            .mapping-nav-head{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:10px}
            .mapping-nav-head h2{font-size:18px;margin:0;color:#173766}.mapping-nav-head p{margin:2px 0 0;color:#52617f;font-size:13px}
            .mapping-nav-select{position:relative;display:inline-block}.mapping-nav-select details{position:relative}.mapping-nav-select summary{list-style:none;cursor:pointer;background:#4f5ea7;color:#fff;border-radius:5px;padding:10px 14px;font-weight:700}.mapping-nav-select summary::-webkit-details-marker{display:none}
            .mapping-nav-select details[open] summary{background:#394991}.mapping-nav-menu{position:absolute;right:0;top:calc(100% + 8px);z-index:50;background:#fff;border:1px solid #d8dde7;border-radius:8px;box-shadow:0 18px 44px rgba(26,33,52,.18);min-width:280px;padding:8px}.mapping-nav-menu a{display:block;text-decoration:none;color:#173766;padding:10px 12px;border-radius:6px}.mapping-nav-menu a:hover,.mapping-nav-menu a.active{background:#eef1f8;color:#394991}.mapping-nav-menu small{display:block;color:#667085;margin-top:2px}
            .mapping-nav-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}.mapping-nav-pill{border:1px solid #d8dde7;background:#f8fafc;border-radius:5px;padding:9px 10px;text-decoration:none;color:#173766;min-height:54px}.mapping-nav-pill strong{display:block;font-size:13px}.mapping-nav-pill span{display:block;font-size:12px;color:#667085;margin-top:2px}.mapping-nav-pill.active,.mapping-nav-pill:hover{border-color:#4f5ea7;background:#eef1f8;color:#394991;text-decoration:none}
            @media(max-width:1180px){.mapping-nav-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.mapping-nav-select{width:100%}.mapping-nav-select summary{text-align:center}.mapping-nav-menu{left:0;right:auto;width:100%;min-width:0}}
            @media(max-width:700px){.mapping-nav-grid{grid-template-columns:1fr}}
        </style>
        <section class="mapping-nav-wrap" aria-label="Mapping governance navigation">
            <div class="mapping-nav-head">
                <div>
                    <h2>Mapping Governance Menu</h2>
                    <p>Central access to company, driver, vehicle, starting-point, verification, and legacy mapping pages.</p>
                </div>
                <div class="mapping-nav-select">
                    <details>
                        <summary>Mapping tools ▾</summary>
                        <div class="mapping-nav-menu">
                            <?php foreach ($items as $key => $item): ?>
                                <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= gmnav_h($item[0]) ?>">
                                    <?= gmnav_h($item[1]) ?>
                                    <small><?= gmnav_h($item[2]) ?></small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </div>
            </div>
            <div class="mapping-nav-grid">
                <?php foreach ($items as $key => $item): ?>
                    <a class="mapping-nav-pill<?= $active === $key ? ' active' : '' ?>" href="<?= gmnav_h($item[0]) ?>">
                        <strong><?= gmnav_h($item[1]) ?></strong>
                        <span><?= gmnav_h($item[2]) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
    }
}
