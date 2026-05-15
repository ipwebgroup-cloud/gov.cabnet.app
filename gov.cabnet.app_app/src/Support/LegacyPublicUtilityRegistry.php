<?php
/**
 * gov.cabnet.app — Legacy public utility registry.
 *
 * SAFETY:
 * - Metadata only.
 * - No Bolt calls.
 * - No EDXEIX calls.
 * - No AADE calls.
 * - No database connection.
 * - No filesystem writes.
 * - Does not move, delete, include, or execute legacy public-root utilities.
 */

declare(strict_types=1);

if (!function_exists('gov_legacy_public_utility_registry_version')) {
    function gov_legacy_public_utility_registry_version(): string
    {
        return 'v3.0.89-legacy-public-utility-ops-wrapper-registry';
    }
}

if (!function_exists('gov_legacy_public_utility_public_root')) {
    function gov_legacy_public_utility_public_root(): string
    {
        return '/home/cabnet/public_html/gov.cabnet.app';
    }
}

if (!function_exists('gov_legacy_public_utility_items')) {
    /**
     * @return array<string,array<string,mixed>>
     */
    function gov_legacy_public_utility_items(): array
    {
        return [
            'bolt-api-smoke-test' => [
                'label' => 'Bolt API Smoke Test',
                'legacy_file' => 'bolt-api-smoke-test.php',
                'legacy_route' => '/bolt-api-smoke-test.php',
                'role' => 'Bolt API smoke/readiness probe.',
                'current_posture' => 'Guarded public-root utility. Keep available until compatibility migration is complete.',
                'future_target' => 'Private CLI plus supervised /ops wrapper.',
                'operator_use' => 'Use only as a supervised diagnostic. Prefer V3 readiness and public route audit pages for normal operations.',
                'risk_level' => 'medium',
                'move_now' => false,
                'delete_now' => false,
                'direct_execution_from_wrapper' => false,
            ],
            'bolt-fleet-orders-watch' => [
                'label' => 'Bolt Fleet Orders Watch',
                'legacy_file' => 'bolt-fleet-orders-watch.php',
                'legacy_route' => '/bolt-fleet-orders-watch.php',
                'role' => 'Bolt fleet order watcher/state diff utility.',
                'current_posture' => 'Guarded public-root utility with local watcher-state behavior.',
                'future_target' => 'Private CLI only, optionally surfaced by read-only ops status.',
                'operator_use' => 'Do not relocate until cron/monitor/bookmark dependency checks are complete.',
                'risk_level' => 'medium',
                'move_now' => false,
                'delete_now' => false,
                'direct_execution_from_wrapper' => false,
            ],
            'bolt-stage-edxeix-jobs' => [
                'label' => 'Legacy Bolt Stage EDXEIX Jobs',
                'legacy_file' => 'bolt_stage_edxeix_jobs.php',
                'legacy_route' => '/bolt_stage_edxeix_jobs.php',
                'role' => 'Legacy guarded local EDXEIX job staging dry-run/staging utility.',
                'current_posture' => 'Submit/stage-adjacent guarded public-root utility. No live EDXEIX call by design.',
                'future_target' => 'Locked private CLI or /ops legacy admin-only wrapper.',
                'operator_use' => 'Keep route unchanged until V3 fully replaces legacy staging and dependencies are cleaned.',
                'risk_level' => 'high',
                'move_now' => false,
                'delete_now' => false,
                'direct_execution_from_wrapper' => false,
            ],
            'bolt-submission-worker' => [
                'label' => 'Legacy Bolt Submission Worker',
                'legacy_file' => 'bolt_submission_worker.php',
                'legacy_route' => '/bolt_submission_worker.php',
                'role' => 'Legacy dry-run submission worker / local audit recorder.',
                'current_posture' => 'Submit-adjacent guarded public-root utility. Must remain non-live.',
                'future_target' => 'Locked private CLI or /ops legacy admin-only wrapper.',
                'operator_use' => 'Do not expose as a daily operation. Keep available only for supervised legacy dry-run review.',
                'risk_level' => 'high',
                'move_now' => false,
                'delete_now' => false,
                'direct_execution_from_wrapper' => false,
            ],
            'bolt-sync-orders' => [
                'label' => 'Bolt Sync Orders',
                'legacy_file' => 'bolt_sync_orders.php',
                'legacy_route' => '/bolt_sync_orders.php',
                'role' => 'Bolt fleet orders sync into raw payloads / normalized bookings.',
                'current_posture' => 'Guarded public-root sync utility. Better long-term as private CLI/cron.',
                'future_target' => 'Private CLI first, optional supervised /ops audit wrapper.',
                'operator_use' => 'Check cron/manual dependency first; do not move until replacement CLI path is verified.',
                'risk_level' => 'medium',
                'move_now' => false,
                'delete_now' => false,
                'direct_execution_from_wrapper' => false,
            ],
            'bolt-sync-reference' => [
                'label' => 'Bolt Sync Reference',
                'legacy_file' => 'bolt_sync_reference.php',
                'legacy_route' => '/bolt_sync_reference.php',
                'role' => 'Bolt drivers/vehicles reference sync into mapping tables.',
                'current_posture' => 'Guarded public-root sync utility. Better long-term as private CLI/cron.',
                'future_target' => 'Private CLI first, optional supervised /ops audit wrapper.',
                'operator_use' => 'Check cron/operator dependency first; do not move until replacement CLI path is verified.',
                'risk_level' => 'medium',
                'move_now' => false,
                'delete_now' => false,
                'direct_execution_from_wrapper' => false,
            ],
        ];
    }
}

if (!function_exists('gov_legacy_public_utility_find')) {
    /**
     * @return array<string,mixed>|null
     */
    function gov_legacy_public_utility_find(string $key): ?array
    {
        $items = gov_legacy_public_utility_items();
        return $items[$key] ?? null;
    }
}

if (!function_exists('gov_legacy_public_utility_file_meta')) {
    /**
     * @return array<string,mixed>
     */
    function gov_legacy_public_utility_file_meta(string $legacyFile): array
    {
        $path = rtrim(gov_legacy_public_utility_public_root(), '/') . '/' . ltrim($legacyFile, '/');
        return [
            'path' => $path,
            'exists' => is_file($path),
            'readable' => is_readable($path),
            'size' => is_file($path) ? (int)filesize($path) : 0,
            'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
        ];
    }
}

if (!function_exists('gov_legacy_public_utility_report')) {
    /**
     * @return array<string,mixed>
     */
    function gov_legacy_public_utility_report(): array
    {
        $items = gov_legacy_public_utility_items();
        $rows = [];
        $existing = 0;
        foreach ($items as $key => $item) {
            $meta = gov_legacy_public_utility_file_meta((string)$item['legacy_file']);
            if (!empty($meta['exists'])) {
                $existing++;
            }
            $item['key'] = $key;
            $item['file_meta'] = $meta;
            $rows[] = $item;
        }

        return [
            'ok' => true,
            'version' => gov_legacy_public_utility_registry_version(),
            'mode' => 'metadata_only_legacy_public_utility_ops_wrapper_registry',
            'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No DB connection. No filesystem writes. No route moves. No route deletions. Legacy utilities are not included or executed.',
            'summary' => [
                'utilities_registered' => count($items),
                'legacy_files_existing' => $existing,
                'move_recommended_now' => 0,
                'delete_recommended_now' => 0,
                'direct_execution_from_wrapper' => false,
            ],
            'utilities' => $rows,
            'next_safe_step' => 'Use this wrapper registry as the future target for links. Do not move/delete legacy public-root utilities until dependencies are clean and Andreas explicitly approves.',
        ];
    }
}
