<?php
/**
 * gov.cabnet.app — V3 Real-Mail Observation Overview CLI.
 *
 * v3.1.9:
 * - Read-only consolidated overview for V3 queue health, expiry audit, and next candidate watch.
 * - Calls only existing read-only V3 audit/watch run functions.
 * - No Bolt calls, no EDXEIX calls, no AADE calls, no DB writes, no queue mutations.
 */

declare(strict_types=1);

const GOV_V3_OBSERVATION_OVERVIEW_VERSION = 'v3.1.9-v3-real-mail-observation-overview';
const GOV_V3_OBSERVATION_OVERVIEW_MODE = 'read_only_v3_real_mail_observation_overview';
const GOV_V3_OBSERVATION_OVERVIEW_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No filesystem writes. Read-only composition of existing V3 audit/watch outputs only.';

function gov_v3obs_app_root(): string
{
    return dirname(__DIR__);
}

/** @return array<string,string> */
function gov_v3obs_component_files(): array
{
    $cli = gov_v3obs_app_root() . '/cli';
    return [
        'queue_health' => $cli . '/pre_ride_email_v3_real_mail_queue_health.php',
        'expiry_reason_audit' => $cli . '/pre_ride_email_v3_real_mail_expiry_reason_audit.php',
        'next_candidate_watch' => $cli . '/pre_ride_email_v3_next_real_mail_candidate_watch.php',
    ];
}

/** @return array<string,mixed> */
function gov_v3obs_empty_component(string $key, string $path): array
{
    return [
        'key' => $key,
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'ok' => false,
        'version' => '',
        'error' => '',
        'summary' => [],
        'final_blocks' => [],
    ];
}

/** @return array<string,mixed> */
function gov_v3obs_component_report(string $key, string $path, string $functionName): array
{
    $component = gov_v3obs_empty_component($key, $path);

    if (!is_file($path) || !is_readable($path)) {
        $component['error'] = 'Component file missing or unreadable.';
        $component['final_blocks'][] = $key . ': component file missing or unreadable';
        return $component;
    }

    try {
        require_once $path;
        $component['loaded'] = true;
        if (!function_exists($functionName)) {
            $component['error'] = 'Expected run function not found: ' . $functionName;
            $component['final_blocks'][] = $key . ': run function missing';
            return $component;
        }

        $report = $functionName();
        if (!is_array($report)) {
            $component['error'] = 'Run function did not return an array.';
            $component['final_blocks'][] = $key . ': invalid report return';
            return $component;
        }

        $component['ok'] = !empty($report['ok']);
        $component['version'] = (string)($report['version'] ?? '');
        $component['summary'] = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $component['final_blocks'] = is_array($report['final_blocks'] ?? null) ? array_values($report['final_blocks']) : [];
        $component['warnings'] = is_array($report['warnings'] ?? null) ? array_values($report['warnings']) : [];
        return $component;
    } catch (Throwable $e) {
        $component['error'] = $e->getMessage();
        $component['final_blocks'][] = $key . ': exception while loading/running component';
        return $component;
    }
}

/** @param array<string,mixed> $component */
function gov_v3obs_summary_int(array $component, string $key, int $default = 0): int
{
    $summary = is_array($component['summary'] ?? null) ? $component['summary'] : [];
    return (int)($summary[$key] ?? $default);
}

/** @param array<string,mixed> $component */
function gov_v3obs_summary_bool(array $component, string $key, bool $default = false): bool
{
    $summary = is_array($component['summary'] ?? null) ? $component['summary'] : [];
    return (bool)($summary[$key] ?? $default);
}

/** @return array<string,mixed> */
function gov_v3_observation_overview_run(): array
{
    $files = gov_v3obs_component_files();

    $components = [
        'queue_health' => gov_v3obs_component_report('queue_health', $files['queue_health'], 'gov_pre_ride_email_v3_real_mail_queue_health_run'),
        'expiry_reason_audit' => gov_v3obs_component_report('expiry_reason_audit', $files['expiry_reason_audit'], 'gov_v3_real_mail_expiry_reason_audit_run'),
        'next_candidate_watch' => gov_v3obs_component_report('next_candidate_watch', $files['next_candidate_watch'], 'gov_v3_next_real_mail_candidate_watch_run'),
    ];

    $finalBlocks = [];
    $warnings = [];
    foreach ($components as $key => $component) {
        if (empty($component['ok'])) {
            $finalBlocks[] = $key . ': component report not ok';
        }
        foreach ((array)($component['final_blocks'] ?? []) as $block) {
            $block = trim((string)$block);
            if ($block !== '') {
                $finalBlocks[] = $key . ': ' . $block;
            }
        }
        foreach ((array)($component['warnings'] ?? []) as $warning) {
            $warning = trim((string)$warning);
            if ($warning !== '') {
                $warnings[] = $key . ': ' . $warning;
            }
        }
    }

    $queue = $components['queue_health'];
    $expiry = $components['expiry_reason_audit'];
    $watch = $components['next_candidate_watch'];

    $liveRisk = gov_v3obs_summary_bool($queue, 'live_risk_detected')
        || gov_v3obs_summary_bool($expiry, 'live_risk_detected')
        || gov_v3obs_summary_bool($watch, 'live_risk_detected');

    if ($liveRisk) {
        $finalBlocks[] = 'overview: live risk detected by one or more read-only components';
    }

    $futureActive = gov_v3obs_summary_int($queue, 'future_active_rows');
    $operatorCandidates = gov_v3obs_summary_int($watch, 'operator_review_candidates');
    $urgentCandidates = gov_v3obs_summary_int($watch, 'urgent_operator_review_candidates');

    $recommendedNextStep = 'Continue observation. If future_possible_real_rows/operator_review_candidates becomes greater than zero, inspect with closed-gate tools before pickup expires. Do not enable live submit.';
    if ($operatorCandidates > 0) {
        $recommendedNextStep = 'Operator review candidate exists. Inspect immediately in closed-gate tools only; live submit remains disabled.';
    }

    $summary = [
        'queue_health_ok' => !empty($queue['ok']),
        'expiry_audit_ok' => !empty($expiry['ok']),
        'candidate_watch_ok' => !empty($watch['ok']),
        'possible_real_rows_queue_health' => gov_v3obs_summary_int($queue, 'possible_real_mail_recent_count'),
        'possible_real_rows_expiry_audit' => gov_v3obs_summary_int($expiry, 'possible_real_mail_rows'),
        'possible_real_expired_guard_rows' => gov_v3obs_summary_int($expiry, 'possible_real_mail_expired_guard_rows'),
        'possible_real_non_expired_guard_rows' => gov_v3obs_summary_int($expiry, 'possible_real_mail_non_expired_guard_rows'),
        'mapping_correction_rows' => gov_v3obs_summary_int($expiry, 'possible_real_mail_mapping_correction_rows'),
        'queue_health_vs_expiry_count_mismatch_explained' => gov_v3obs_summary_bool($expiry, 'queue_health_vs_expiry_count_mismatch_explained'),
        'future_active_rows' => $futureActive,
        'future_possible_real_rows' => gov_v3obs_summary_int($watch, 'future_possible_real_rows'),
        'operator_review_candidates' => $operatorCandidates,
        'urgent_operator_review_candidates' => $urgentCandidates,
        'live_risk_detected' => $liveRisk,
        'live_submit_recommended_now' => 0,
        'db_write_made' => false,
        'queue_mutation_made' => false,
        'edxeix_call_made' => false,
        'aade_call_made' => false,
    ];

    return [
        'ok' => $finalBlocks === [],
        'version' => GOV_V3_OBSERVATION_OVERVIEW_VERSION,
        'mode' => GOV_V3_OBSERVATION_OVERVIEW_MODE,
        'started_at' => date('c'),
        'finished_at' => date('c'),
        'safety' => GOV_V3_OBSERVATION_OVERVIEW_SAFETY,
        'app_root' => gov_v3obs_app_root(),
        'components' => $components,
        'summary' => $summary,
        'warnings' => array_values(array_unique($warnings)),
        'recommended_next_step' => $recommendedNextStep,
        'final_blocks' => array_values(array_unique($finalBlocks)),
    ];
}

/** @param array<int,string> $argv */
function gov_v3_observation_overview_main(array $argv): int
{
    $json = in_array('--json', $argv, true);
    $report = gov_v3_observation_overview_run();

    if ($json) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
    echo 'V3 Real-Mail Observation Overview ' . GOV_V3_OBSERVATION_OVERVIEW_VERSION . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Queue health OK: ' . (!empty($summary['queue_health_ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Expiry audit OK: ' . (!empty($summary['expiry_audit_ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Candidate watch OK: ' . (!empty($summary['candidate_watch_ok']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Future active rows: ' . (string)($summary['future_active_rows'] ?? 0) . PHP_EOL;
    echo 'Operator review candidates: ' . (string)($summary['operator_review_candidates'] ?? 0) . PHP_EOL;
    echo 'Live risk: ' . (!empty($summary['live_risk_detected']) ? 'yes' : 'no') . PHP_EOL;
    echo 'Final blocks: ' . json_encode($report['final_blocks'] ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(gov_v3_observation_overview_main($argv ?? []));
}
