<?php

declare(strict_types=1);

/**
 * V3 live gate drift guard CLI.
 *
 * Read-only safety guard for the V3 live-submit master gate.
 * It verifies that the current server gate is still closed unless an explicit
 * future live cutover has been intentionally prepared.
 *
 * Safety: no Bolt call, no EDXEIX call, no AADE call, no DB writes,
 * no queue status changes, no production submission tables, V0 untouched.
 */

const V3_GATE_DRIFT_VERSION = 'v3.0.74-v3-live-gate-drift-guard';
const V3_GATE_DRIFT_SAFETY = 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.';
const V3_GATE_CONFIG_PATH = '/home/cabnet/gov.cabnet.app_config/pre_ride_email_v3_live_submit.php';

function v3gd_arg_value(string $name, ?string $default = null): ?string
{
    global $argv;
    foreach ($argv as $arg) {
        if ($arg === $name) {
            return '1';
        }
        if (str_starts_with($arg, $name . '=')) {
            return substr($arg, strlen($name) + 1);
        }
    }
    return $default;
}

function v3gd_flag(string $name): bool
{
    return v3gd_arg_value($name) !== null;
}

function v3gd_bool(mixed $value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (int)$value === 1;
    }
    if (is_string($value)) {
        $v = strtolower(trim($value));
        if (in_array($v, ['1', 'true', 'yes', 'on', 'enabled', 'live'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true)) {
            return false;
        }
    }
    return $default;
}

function v3gd_yesno(mixed $value): string
{
    return v3gd_bool($value) ? 'yes' : 'no';
}

/** @return array<string,mixed> */
function v3gd_safe_file(string $path): array
{
    return [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'size' => is_file($path) ? (int)filesize($path) : 0,
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)filemtime($path)) : null,
    ];
}

/** @param array<string,mixed> $source */
function v3gd_pick(array $source, array $keys, mixed $default = null): mixed
{
    foreach ($keys as $key) {
        $parts = explode('.', (string)$key);
        $cur = $source;
        $found = true;
        foreach ($parts as $part) {
            if (!is_array($cur) || !array_key_exists($part, $cur)) {
                $found = false;
                break;
            }
            $cur = $cur[$part];
        }
        if ($found) {
            return $cur;
        }
    }
    return $default;
}

/** @return array<string,mixed> */
function v3gd_load_gate_config(string $path): array
{
    $out = [
        'path' => $path,
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'loaded' => false,
        'returned_array' => false,
        'error' => '',
        'raw_keys' => [],
        'safe' => [
            'enabled' => false,
            'mode' => 'missing',
            'adapter' => 'missing',
            'hard_enable_live_submit' => false,
            'acknowledgement_phrase_present' => false,
        ],
    ];

    if (!is_file($path)) {
        $out['error'] = 'gate config file missing';
        return $out;
    }
    if (!is_readable($path)) {
        $out['error'] = 'gate config file not readable';
        return $out;
    }

    try {
        /** @noinspection PhpIncludeInspection */
        $config = include $path;
    } catch (Throwable $e) {
        $out['error'] = 'gate config include failed: ' . $e->getMessage();
        return $out;
    }

    $out['loaded'] = true;
    if (!is_array($config)) {
        $out['error'] = 'gate config did not return an array';
        return $out;
    }

    $out['returned_array'] = true;
    $out['raw_keys'] = array_values(array_map('strval', array_keys($config)));

    $enabledRaw = v3gd_pick($config, [
        'enabled',
        'live_submit.enabled',
        'pre_ride_email_v3.enabled',
        'v3_live_submit.enabled',
    ], false);
    $modeRaw = v3gd_pick($config, [
        'mode',
        'live_submit.mode',
        'pre_ride_email_v3.mode',
        'v3_live_submit.mode',
    ], 'missing');
    $adapterRaw = v3gd_pick($config, [
        'adapter',
        'live_submit.adapter',
        'pre_ride_email_v3.adapter',
        'v3_live_submit.adapter',
    ], 'missing');
    $hardRaw = v3gd_pick($config, [
        'hard_enable_live_submit',
        'hard_enabled',
        'live_submit.hard_enable_live_submit',
        'pre_ride_email_v3.hard_enable_live_submit',
        'v3_live_submit.hard_enable_live_submit',
    ], false);
    $ackRaw = v3gd_pick($config, [
        'required_acknowledgement_phrase',
        'acknowledgement_phrase',
        'required_ack_phrase',
        'live_submit.required_acknowledgement_phrase',
        'live_submit.acknowledgement_phrase',
        'pre_ride_email_v3.required_acknowledgement_phrase',
        'v3_live_submit.required_acknowledgement_phrase',
    ], '');

    $out['safe'] = [
        'enabled' => v3gd_bool($enabledRaw),
        'mode' => strtolower(trim((string)$modeRaw)),
        'adapter' => strtolower(trim((string)$adapterRaw)),
        'hard_enable_live_submit' => v3gd_bool($hardRaw),
        'acknowledgement_phrase_present' => trim((string)$ackRaw) !== '',
    ];

    return $out;
}

/** @return array<string,mixed> */
function v3gd_adapter_files(string $appRoot): array
{
    $base = rtrim($appRoot, '/') . '/src/BoltMailV3';
    $files = [
        'interface' => $base . '/LiveSubmitAdapterV3.php',
        'disabled' => $base . '/DisabledLiveSubmitAdapterV3.php',
        'dry_run' => $base . '/DryRunLiveSubmitAdapterV3.php',
        'edxeix_live' => $base . '/EdxeixLiveSubmitAdapterV3.php',
    ];

    $out = [];
    foreach ($files as $key => $path) {
        $meta = v3gd_safe_file($path);
        $scan = [
            'contains_submit_method' => false,
            'contains_curl' => false,
            'contains_file_get_contents_http' => false,
            'contains_stream_context' => false,
            'contains_live_capable_true' => false,
            'contains_skeleton_not_implemented' => false,
        ];
        if (is_file($path) && is_readable($path)) {
            $raw = file_get_contents($path);
            if (is_string($raw)) {
                $scan['contains_submit_method'] = str_contains($raw, 'function submit(');
                $scan['contains_curl'] = stripos($raw, 'curl_') !== false;
                $scan['contains_file_get_contents_http'] = stripos($raw, 'file_get_contents(') !== false && stripos($raw, 'http') !== false;
                $scan['contains_stream_context'] = stripos($raw, 'stream_context_create') !== false;
                $scan['contains_live_capable_true'] = stripos($raw, 'isLiveCapable') !== false && (bool)preg_match('/isLiveCapable\s*\([^)]*\)\s*:\s*bool\s*\{[^}]*return\s+true\s*;/is', $raw);
                $scan['contains_skeleton_not_implemented'] = stripos($raw, 'skeleton_not_implemented') !== false || stripos($raw, 'not implemented') !== false;
            }
        }
        $out[$key] = array_merge($meta, ['scan' => $scan]);
    }

    return $out;
}

/** @return array<string,mixed> */
function v3gd_analyze_gate(array $config, array $adapterFiles): array
{
    $safe = is_array($config['safe'] ?? null) ? $config['safe'] : [];
    $enabled = v3gd_bool($safe['enabled'] ?? false);
    $mode = strtolower(trim((string)($safe['mode'] ?? 'missing')));
    $adapter = strtolower(trim((string)($safe['adapter'] ?? 'missing')));
    $hard = v3gd_bool($safe['hard_enable_live_submit'] ?? false);
    $ack = v3gd_bool($safe['acknowledgement_phrase_present'] ?? false);

    $blocks = [];
    if (!$enabled) {
        $blocks[] = 'master_gate: enabled is false';
    }
    if ($mode !== 'live') {
        $blocks[] = 'master_gate: mode is not live';
    }
    if ($adapter !== 'edxeix_live') {
        $blocks[] = 'master_gate: adapter is not edxeix_live';
    }
    if (!$hard) {
        $blocks[] = 'master_gate: hard_enable_live_submit is false';
    }
    if (!$ack) {
        $blocks[] = 'master_gate: acknowledgement phrase is not present';
    }

    $expectedClosed = !$enabled && in_array($mode, ['disabled', 'missing', ''], true) && in_array($adapter, ['disabled', 'missing', ''], true) && !$hard;
    $partialLiveSignals = [];
    if ($enabled) {
        $partialLiveSignals[] = 'enabled=true';
    }
    if ($mode === 'live') {
        $partialLiveSignals[] = 'mode=live';
    }
    if ($adapter === 'edxeix_live') {
        $partialLiveSignals[] = 'adapter=edxeix_live';
    }
    if ($hard) {
        $partialLiveSignals[] = 'hard_enable_live_submit=true';
    }

    $edxeixScan = $adapterFiles['edxeix_live']['scan'] ?? [];
    $adapterLooksLiveCapable = !empty($edxeixScan['contains_live_capable_true'])
        || !empty($edxeixScan['contains_curl'])
        || !empty($edxeixScan['contains_file_get_contents_http'])
        || !empty($edxeixScan['contains_stream_context']);

    $liveRisk = count($partialLiveSignals) >= 2 || ($adapterLooksLiveCapable && count($partialLiveSignals) >= 1);
    $fullLiveSwitchLooksOpen = $enabled && $mode === 'live' && $adapter === 'edxeix_live' && $hard && $ack;

    $driftBlocks = [];
    if (!$expectedClosed) {
        $driftBlocks[] = 'safe_closed_expected: gate config differs from disabled pre-live posture';
    }
    if ($liveRisk) {
        $driftBlocks[] = 'live_risk: partial or full live-submit switch signals detected';
    }
    if ($fullLiveSwitchLooksOpen) {
        $driftBlocks[] = 'critical: all master gate live switch fields appear open';
    }
    if ($adapterLooksLiveCapable && $adapter !== 'disabled') {
        $driftBlocks[] = 'adapter_review: real-live adapter appears capable or network-aware';
    }

    return [
        'enabled' => $enabled,
        'mode' => $mode,
        'adapter' => $adapter,
        'hard_enable_live_submit' => $hard,
        'acknowledgement_phrase_present' => $ack,
        'expected_closed_pre_live' => $expectedClosed,
        'master_gate_live_blocks' => $blocks,
        'partial_live_signals' => $partialLiveSignals,
        'adapter_looks_live_capable' => $adapterLooksLiveCapable,
        'full_live_switch_looks_open' => $fullLiveSwitchLooksOpen,
        'live_risk_detected' => $liveRisk,
        'drift_detected' => $driftBlocks !== [],
        'drift_blocks' => $driftBlocks,
    ];
}

/** @return array<string,mixed> */
function v3gd_artifact_snapshot(string $appRoot): array
{
    $bundleDir = rtrim($appRoot, '/') . '/storage/artifacts/v3_pre_live_proof_bundles';
    $packageDir = rtrim($appRoot, '/') . '/storage/artifacts/v3_live_submit_packages';
    $bundles = glob($bundleDir . '/bundle_*_summary.json');
    $packages = glob($packageDir . '/queue_*_edxeix_fields.json');
    $bundles = is_array($bundles) ? $bundles : [];
    $packages = is_array($packages) ? $packages : [];
    usort($bundles, static fn(string $a, string $b): int => (int)filemtime($b) <=> (int)filemtime($a));
    usort($packages, static fn(string $a, string $b): int => (int)filemtime($b) <=> (int)filemtime($a));

    return [
        'proof_bundle_dir' => [
            'path' => $bundleDir,
            'exists' => is_dir($bundleDir),
            'readable' => is_readable($bundleDir),
            'summary_json_count' => count($bundles),
            'latest' => array_slice(array_map('basename', $bundles), 0, 5),
        ],
        'live_package_dir' => [
            'path' => $packageDir,
            'exists' => is_dir($packageDir),
            'readable' => is_readable($packageDir),
            'edxeix_fields_json_count' => count($packages),
            'latest' => array_slice(array_map('basename', $packages), 0, 5),
        ],
    ];
}

/** @return array<string,mixed> */
function v3gd_report(): array
{
    $appRoot = rtrim((string)(getenv('GOVCABNET_APP_ROOT') ?: '/home/cabnet/gov.cabnet.app_app'), '/');
    $configPath = (string)(v3gd_arg_value('--config', V3_GATE_CONFIG_PATH) ?: V3_GATE_CONFIG_PATH);
    $config = v3gd_load_gate_config($configPath);
    $adapterFiles = v3gd_adapter_files($appRoot);
    $gate = v3gd_analyze_gate($config, $adapterFiles);
    $artifacts = v3gd_artifact_snapshot($appRoot);

    $finalBlocks = [];
    if (empty($config['loaded']) || empty($config['returned_array'])) {
        $finalBlocks[] = 'config: ' . (string)($config['error'] ?? 'not loaded');
    }
    foreach ($gate['drift_blocks'] as $block) {
        $finalBlocks[] = (string)$block;
    }

    $ok = $finalBlocks === [];

    return [
        'ok' => $ok,
        'version' => V3_GATE_DRIFT_VERSION,
        'mode' => 'read_only_live_gate_drift_guard',
        'started_at' => date('c'),
        'safety' => V3_GATE_DRIFT_SAFETY,
        'app_root' => $appRoot,
        'config' => $config,
        'gate_analysis' => $gate,
        'adapter_files' => $adapterFiles,
        'artifacts' => $artifacts,
        'final_blocks' => $finalBlocks,
        'operator_next_action' => $ok
            ? 'Gate remains in the expected disabled pre-live posture. Continue with read-only harnessing or wait for a real future row.'
            : 'Review final_blocks. Do not proceed toward live submit until drift is understood and intentionally approved.',
        'finished_at' => date('c'),
    ];
}

$report = v3gd_report();

if (v3gd_flag('--json')) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(!empty($report['ok']) ? 0 : 1);
}

$gate = is_array($report['gate_analysis'] ?? null) ? $report['gate_analysis'] : [];
$config = is_array($report['config'] ?? null) ? $report['config'] : [];
$artifacts = is_array($report['artifacts'] ?? null) ? $report['artifacts'] : [];

echo 'V3 live gate drift guard ' . V3_GATE_DRIFT_VERSION . PHP_EOL;
echo 'Mode: read_only_live_gate_drift_guard' . PHP_EOL;
echo 'Safety: ' . V3_GATE_DRIFT_SAFETY . PHP_EOL;
echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
echo 'Config: loaded=' . v3gd_yesno($config['loaded'] ?? false) . ' path=' . (string)($config['path'] ?? '') . PHP_EOL;
echo 'Gate: enabled=' . v3gd_yesno($gate['enabled'] ?? false)
    . ' mode=' . (string)($gate['mode'] ?? '-')
    . ' adapter=' . (string)($gate['adapter'] ?? '-')
    . ' hard=' . v3gd_yesno($gate['hard_enable_live_submit'] ?? false)
    . ' ack=' . v3gd_yesno($gate['acknowledgement_phrase_present'] ?? false) . PHP_EOL;
echo 'Expected disabled pre-live posture: ' . v3gd_yesno($gate['expected_closed_pre_live'] ?? false) . PHP_EOL;
echo 'Live risk detected: ' . v3gd_yesno($gate['live_risk_detected'] ?? false) . PHP_EOL;
echo 'Adapter looks live capable/network-aware: ' . v3gd_yesno($gate['adapter_looks_live_capable'] ?? false) . PHP_EOL;
echo 'Full live switch looks open: ' . v3gd_yesno($gate['full_live_switch_looks_open'] ?? false) . PHP_EOL;

$bundleDir = is_array($artifacts['proof_bundle_dir'] ?? null) ? $artifacts['proof_bundle_dir'] : [];
$packageDir = is_array($artifacts['live_package_dir'] ?? null) ? $artifacts['live_package_dir'] : [];
echo 'Proof bundles: ' . (string)($bundleDir['summary_json_count'] ?? 0) . ' | Live packages: ' . (string)($packageDir['edxeix_fields_json_count'] ?? 0) . PHP_EOL;

if (!empty($gate['partial_live_signals']) && is_array($gate['partial_live_signals'])) {
    echo 'Partial live signals:' . PHP_EOL;
    foreach ($gate['partial_live_signals'] as $signal) {
        echo '  - ' . (string)$signal . PHP_EOL;
    }
}

if (!empty($gate['master_gate_live_blocks']) && is_array($gate['master_gate_live_blocks'])) {
    echo 'Master gate live blockers:' . PHP_EOL;
    foreach ($gate['master_gate_live_blocks'] as $block) {
        echo '  - ' . (string)$block . PHP_EOL;
    }
}

if (!empty($report['final_blocks']) && is_array($report['final_blocks'])) {
    echo 'Final blocks:' . PHP_EOL;
    foreach ($report['final_blocks'] as $block) {
        echo '  - ' . (string)$block . PHP_EOL;
    }
}

echo 'Next action: ' . (string)($report['operator_next_action'] ?? '') . PHP_EOL;
exit(!empty($report['ok']) ? 0 : 1);
