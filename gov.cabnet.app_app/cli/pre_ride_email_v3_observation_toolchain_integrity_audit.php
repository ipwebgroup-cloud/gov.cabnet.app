<?php
/**
 * gov.cabnet.app — V3 Observation Toolchain Integrity Audit CLI.
 *
 * v3.1.12:
 * - Read-only integrity audit for the V3 real-mail observation toolchain.
 * - Verifies required CLI/ops files, shared shell navigation/note posture, and public backup hygiene.
 * - Calls the existing read-only V3 observation overview for consolidated closed-gate status.
 * - No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes, no queue mutations, no filesystem writes.
 */

declare(strict_types=1);

const GOV_V3_OBSERVATION_TOOLCHAIN_AUDIT_VERSION = 'v3.1.12-v3-observation-toolchain-integrity-audit';
const GOV_V3_OBSERVATION_TOOLCHAIN_AUDIT_MODE = 'read_only_v3_observation_toolchain_integrity_audit';
const GOV_V3_OBSERVATION_TOOLCHAIN_AUDIT_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No filesystem writes. Read-only file/toolchain inspection plus existing read-only observation overview.';

function gov_v3oti_app_root(): string
{
    return dirname(__DIR__);
}

function gov_v3oti_home_root(): string
{
    return dirname(gov_v3oti_app_root());
}

function gov_v3oti_public_root(): string
{
    return gov_v3oti_home_root() . '/public_html/gov.cabnet.app';
}

/** @return array<string,string> */
function gov_v3oti_required_files(): array
{
    $app = gov_v3oti_app_root();
    $public = gov_v3oti_public_root();

    return [
        'cli.queue_health' => $app . '/cli/pre_ride_email_v3_real_mail_queue_health.php',
        'cli.expiry_reason_audit' => $app . '/cli/pre_ride_email_v3_real_mail_expiry_reason_audit.php',
        'cli.next_candidate_watch' => $app . '/cli/pre_ride_email_v3_next_real_mail_candidate_watch.php',
        'cli.observation_overview' => $app . '/cli/pre_ride_email_v3_observation_overview.php',
        'ops.shell' => $public . '/ops/_shell.php',
        'ops.queue_health' => $public . '/ops/pre-ride-email-v3-real-mail-queue-health.php',
        'ops.expiry_reason_audit' => $public . '/ops/pre-ride-email-v3-real-mail-expiry-reason-audit.php',
        'ops.next_candidate_watch' => $public . '/ops/pre-ride-email-v3-next-real-mail-candidate-watch.php',
        'ops.observation_overview' => $public . '/ops/pre-ride-email-v3-observation-overview.php',
    ];
}

/** @return array<string,mixed> */
function gov_v3oti_file_state(string $key, string $path): array
{
    $state = [
        'key' => $key,
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => 0,
        'modified_at' => null,
        'version_marker' => '',
        'ok' => false,
        'warning' => '',
    ];

    if (!is_file($path)) {
        $state['warning'] = 'missing';
        return $state;
    }

    $state['size'] = (int)@filesize($path);
    $mtime = @filemtime($path);
    $state['modified_at'] = $mtime ? date('Y-m-d H:i:s', $mtime) : null;

    if (!is_readable($path)) {
        $state['warning'] = 'not readable';
        return $state;
    }

    $contents = @file_get_contents($path);
    if (!is_string($contents)) {
        $state['warning'] = 'read failed';
        return $state;
    }

    if (preg_match('/v3\.1\.[0-9]+[^\s\'"<]*/', $contents, $m)) {
        $state['version_marker'] = (string)$m[0];
    }

    $state['ok'] = true;
    return $state;
}

/** @return array<string,mixed> */
function gov_v3oti_shell_posture(string $shellFile): array
{
    $goodTokens = [
        'overview_route' => '/ops/pre-ride-email-v3-observation-overview.php',
        'overview_label' => 'V3 Observation Overview',
        'legacy_stats_note' => 'legacy stats source audit navigation',
        'candidate_watch_note' => 'next real-mail candidate watch navigation added in v3.1.6',
        'overview_note' => 'real-mail observation overview navigation added in v3.1.10',
    ];

    $badTokens = [
        'legacystats',
        'inv3.1.6',
        'utilityrelocation',
        'healthnavigation',
        'navigationadded',
        'notdeleted',
    ];

    $out = [
        'path' => $shellFile,
        'exists' => is_file($shellFile),
        'readable' => is_readable($shellFile),
        'good_tokens' => [],
        'bad_tokens_found' => [],
        'nav_ok' => false,
        'note_ok' => false,
        'ok' => false,
    ];

    if (!is_file($shellFile) || !is_readable($shellFile)) {
        return $out;
    }

    $contents = @file_get_contents($shellFile);
    if (!is_string($contents)) {
        return $out;
    }

    foreach ($goodTokens as $name => $token) {
        $out['good_tokens'][$name] = strpos($contents, $token) !== false;
    }

    foreach ($badTokens as $token) {
        if (strpos($contents, $token) !== false) {
            $out['bad_tokens_found'][] = $token;
        }
    }

    $out['nav_ok'] = !empty($out['good_tokens']['overview_route']) && !empty($out['good_tokens']['overview_label']);
    $out['note_ok'] = !empty($out['good_tokens']['legacy_stats_note'])
        && !empty($out['good_tokens']['candidate_watch_note'])
        && !empty($out['good_tokens']['overview_note'])
        && $out['bad_tokens_found'] === [];
    $out['ok'] = $out['nav_ok'] && $out['note_ok'];
    return $out;
}

/** @return array<string,mixed> */
function gov_v3oti_public_backup_state(): array
{
    $opsRoot = gov_v3oti_public_root() . '/ops';
    $patterns = [
        $opsRoot . '/_shell.php.bak_v3_1_8*',
        $opsRoot . '/_shell.php.bak_v3_1_10*',
        $opsRoot . '/_shell.php.bak_v3_1_11*',
    ];

    $found = [];
    foreach ($patterns as $pattern) {
        $matches = glob($pattern) ?: [];
        foreach ($matches as $file) {
            if (is_file($file)) {
                $found[] = $file;
            }
        }
    }

    return [
        'ops_root' => $opsRoot,
        'patterns_checked' => $patterns,
        'files' => array_values(array_unique($found)),
        'count' => count(array_unique($found)),
        'ok' => count(array_unique($found)) === 0,
    ];
}

/** @return array<string,mixed> */
function gov_v3oti_observation_overview(): array
{
    $path = gov_v3oti_app_root() . '/cli/pre_ride_email_v3_observation_overview.php';
    $out = [
        'path' => $path,
        'loaded' => false,
        'ok' => false,
        'version' => '',
        'summary' => [],
        'final_blocks' => [],
        'error' => '',
    ];

    if (!is_file($path) || !is_readable($path)) {
        $out['error'] = 'observation overview file missing or unreadable';
        $out['final_blocks'][] = 'overview: file missing or unreadable';
        return $out;
    }

    try {
        require_once $path;
        $out['loaded'] = true;
        if (!function_exists('gov_v3_observation_overview_run')) {
            $out['error'] = 'gov_v3_observation_overview_run not found';
            $out['final_blocks'][] = 'overview: run function missing';
            return $out;
        }

        $report = gov_v3_observation_overview_run();
        if (!is_array($report)) {
            $out['error'] = 'overview run did not return array';
            $out['final_blocks'][] = 'overview: invalid return';
            return $out;
        }

        $out['ok'] = !empty($report['ok']);
        $out['version'] = (string)($report['version'] ?? '');
        $out['summary'] = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $out['final_blocks'] = is_array($report['final_blocks'] ?? null) ? array_values($report['final_blocks']) : [];
    } catch (Throwable $e) {
        $out['error'] = $e->getMessage();
        $out['final_blocks'][] = 'overview: exception while running read-only overview';
    }

    return $out;
}

/** @return array<string,mixed> */
function gov_v3_observation_toolchain_integrity_audit_run(): array
{
    $required = gov_v3oti_required_files();
    $files = [];
    $finalBlocks = [];

    foreach ($required as $key => $path) {
        $state = gov_v3oti_file_state($key, $path);
        $files[$key] = $state;
        if (empty($state['ok'])) {
            $finalBlocks[] = 'file: ' . $key . ' is missing/unreadable';
        }
    }

    $shell = gov_v3oti_shell_posture($required['ops.shell']);
    if (empty($shell['nav_ok'])) {
        $finalBlocks[] = 'shell: observation overview navigation missing';
    }
    if (empty($shell['note_ok'])) {
        $finalBlocks[] = 'shell: shared side-note is not normalized or old typo tokens remain';
    }

    $backups = gov_v3oti_public_backup_state();
    if (empty($backups['ok'])) {
        $finalBlocks[] = 'public hygiene: public _shell.php backup files found';
    }

    $overview = gov_v3oti_observation_overview();
    if (empty($overview['ok'])) {
        $finalBlocks[] = 'overview: consolidated read-only overview is not ok';
    }
    foreach ((array)($overview['final_blocks'] ?? []) as $block) {
        $block = trim((string)$block);
        if ($block !== '') {
            $finalBlocks[] = 'overview: ' . $block;
        }
    }

    $overviewSummary = is_array($overview['summary'] ?? null) ? $overview['summary'] : [];
    $liveRisk = (bool)($overviewSummary['live_risk_detected'] ?? false);
    if ($liveRisk) {
        $finalBlocks[] = 'live risk detected by observation overview';
    }

    $summary = [
        'required_files' => count($files),
        'required_files_ok' => count(array_filter($files, static fn(array $f): bool => !empty($f['ok']))),
        'component_files_ok' => count($files) === count(array_filter($files, static fn(array $f): bool => !empty($f['ok']))),
        'shell_nav_ok' => !empty($shell['nav_ok']),
        'shell_note_ok' => !empty($shell['note_ok']),
        'public_backup_files_found' => (int)($backups['count'] ?? 0),
        'overview_ok' => !empty($overview['ok']),
        'queue_health_ok' => (bool)($overviewSummary['queue_health_ok'] ?? false),
        'expiry_audit_ok' => (bool)($overviewSummary['expiry_audit_ok'] ?? false),
        'candidate_watch_ok' => (bool)($overviewSummary['candidate_watch_ok'] ?? false),
        'future_active_rows' => (int)($overviewSummary['future_active_rows'] ?? 0),
        'operator_review_candidates' => (int)($overviewSummary['operator_review_candidates'] ?? 0),
        'live_risk_detected' => $liveRisk,
        'db_write_made' => false,
        'queue_mutation_made' => false,
        'edxeix_call_made' => false,
        'aade_call_made' => false,
    ];

    return [
        'ok' => $finalBlocks === [],
        'version' => GOV_V3_OBSERVATION_TOOLCHAIN_AUDIT_VERSION,
        'mode' => GOV_V3_OBSERVATION_TOOLCHAIN_AUDIT_MODE,
        'started_at' => date('c'),
        'finished_at' => date('c'),
        'safety' => GOV_V3_OBSERVATION_TOOLCHAIN_AUDIT_SAFETY,
        'app_root' => gov_v3oti_app_root(),
        'public_root' => gov_v3oti_public_root(),
        'files' => $files,
        'shell' => $shell,
        'public_backups' => $backups,
        'overview' => $overview,
        'summary' => $summary,
        'warnings' => [],
        'recommended_next_step' => $finalBlocks === []
            ? 'Toolchain integrity is clean. Continue observation; do not enable live submit.'
            : 'Resolve final_blocks before relying on the observation toolchain. Do not enable live submit.',
        'final_blocks' => array_values(array_unique($finalBlocks)),
    ];
}

/** @param array<int,string> $argv */
function gov_v3_observation_toolchain_integrity_audit_main(array $argv): int
{
    $json = in_array('--json', $argv, true);
    $report = gov_v3_observation_toolchain_integrity_audit_run();

    if ($json) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'V3 Observation Toolchain Integrity Audit ' . GOV_V3_OBSERVATION_TOOLCHAIN_AUDIT_VERSION . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Component files OK: ' . (!empty($summary['component_files_ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Shell nav OK: ' . (!empty($summary['shell_nav_ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Shell note OK: ' . (!empty($summary['shell_note_ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Public backup files found: ' . (string)($summary['public_backup_files_found'] ?? 0) . PHP_EOL;
    echo 'Overview OK: ' . (!empty($summary['overview_ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Future active rows: ' . (string)($summary['future_active_rows'] ?? 0) . PHP_EOL;
    echo 'Operator review candidates: ' . (string)($summary['operator_review_candidates'] ?? 0) . PHP_EOL;
    echo 'Live risk: ' . (!empty($summary['live_risk_detected']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Final blocks: ' . json_encode($report['final_blocks'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(gov_v3_observation_toolchain_integrity_audit_main($argv ?? []));
}
