<?php
/**
 * V3 adapter contract probe.
 *
 * Read-only safety probe for V3 live-submit adapter classes.
 * It instantiates the disabled, dry-run, and future real adapter skeletons
 * with a local fixture payload only. It does not call Bolt, EDXEIX, AADE,
 * database writes, production submission tables, queues, or V0.
 */

declare(strict_types=1);

const PRV3_ADAPTER_PROBE_VERSION = 'v3.0.56-v3-adapter-contract-probe';

/** @return array<string,mixed> */
function prv3_adapter_probe_run(): array
{
    $appRoot = dirname(__DIR__);
    $srcRoot = $appRoot . '/src/BoltMailV3';

    $files = [
        'interface' => $srcRoot . '/LiveSubmitAdapterV3.php',
        'disabled' => $srcRoot . '/DisabledLiveSubmitAdapterV3.php',
        'dry_run' => $srcRoot . '/DryRunLiveSubmitAdapterV3.php',
        'future_real' => $srcRoot . '/EdxeixLiveSubmitAdapterV3.php',
    ];

    $fileStatus = [];
    $events = [];
    foreach ($files as $key => $path) {
        $exists = is_file($path);
        $readable = $exists && is_readable($path);
        $fileStatus[$key] = [
            'path' => $path,
            'exists' => $exists,
            'readable' => $readable,
        ];
        if ($readable) {
            require_once $path;
        } else {
            $events[] = strtoupper($key) . '_FILE_NOT_READABLE: ' . $path;
        }
    }

    $fixturePayload = [
        'lessor' => '3814',
        'driver' => '17585',
        'vehicle' => '5949',
        'starting_point_id' => '6467495',
        'lessee_name' => 'V3 CONTRACT PROBE',
        'lessee_phone' => '+300000000000',
        'boarding_point' => 'Mikonos 846 00, Greece',
        'disembark_point' => 'Ntavias Parking, Mykonos Chora',
        'started_at' => '2099-01-01 10:00:00',
        'ended_at' => '2099-01-01 10:30:00',
        'price' => '44.00',
        'price_text' => '44.00 eur',
    ];

    $fixtureContext = [
        'queue_id' => 'contract_probe',
        'dedupe_key' => 'contract_probe_fixture',
        'lessor_id' => '3814',
        'vehicle_plate' => 'EHA2545',
        'source' => 'V3 adapter contract probe fixture',
    ];

    $classes = [
        'disabled' => 'Bridge\\BoltMailV3\\DisabledLiveSubmitAdapterV3',
        'dry_run' => 'Bridge\\BoltMailV3\\DryRunLiveSubmitAdapterV3',
        'future_real' => 'Bridge\\BoltMailV3\\EdxeixLiveSubmitAdapterV3',
    ];

    $adapters = [];
    foreach ($classes as $key => $class) {
        $adapterReport = [
            'key' => $key,
            'class' => $class,
            'class_exists' => class_exists($class),
            'instantiated' => false,
            'name' => '',
            'is_live_capable' => null,
            'submit_called_with_fixture' => false,
            'submit_returned' => false,
            'submitted' => null,
            'ok' => null,
            'blocked' => null,
            'dry_run' => null,
            'message' => '',
            'reason' => '',
            'payload_sha256' => '',
            'safe_for_closed_gate' => false,
            'error' => '',
        ];

        if (!class_exists($class)) {
            $adapterReport['error'] = 'class_missing';
            $events[] = strtoupper($key) . '_CLASS_MISSING';
            $adapters[$key] = $adapterReport;
            continue;
        }

        try {
            $adapter = new $class();
            $adapterReport['instantiated'] = true;
            if (!method_exists($adapter, 'name') || !method_exists($adapter, 'isLiveCapable') || !method_exists($adapter, 'submit')) {
                $adapterReport['error'] = 'contract_methods_missing';
                $events[] = strtoupper($key) . '_CONTRACT_METHODS_MISSING';
                $adapters[$key] = $adapterReport;
                continue;
            }

            $adapterReport['name'] = (string)$adapter->name();
            $adapterReport['is_live_capable'] = (bool)$adapter->isLiveCapable();
            $adapterReport['submit_called_with_fixture'] = true;
            $result = $adapter->submit($fixturePayload, $fixtureContext);
            $adapterReport['submit_returned'] = true;
            $adapterReport['submitted'] = (bool)($result['submitted'] ?? false);
            $adapterReport['ok'] = (bool)($result['ok'] ?? false);
            $adapterReport['blocked'] = (bool)($result['blocked'] ?? false);
            $adapterReport['dry_run'] = (bool)($result['dry_run'] ?? false);
            $adapterReport['message'] = (string)($result['message'] ?? '');
            $adapterReport['reason'] = (string)($result['reason'] ?? '');
            $adapterReport['payload_sha256'] = (string)($result['payload_sha256'] ?? '');

            // Closed-gate safety rule: the probe fixture must never result in submitted=true.
            $adapterReport['safe_for_closed_gate'] = ($adapterReport['submitted'] === false);
            if ($adapterReport['submitted'] === true) {
                $events[] = strtoupper($key) . '_UNSAFE_SUBMITTED_TRUE';
            }
        } catch (Throwable $e) {
            $adapterReport['error'] = $e->getMessage();
            $events[] = strtoupper($key) . '_EXCEPTION: ' . $e->getMessage();
        }

        $adapters[$key] = $adapterReport;
    }

    $requiredSafe = true;
    foreach ($adapters as $adapter) {
        if (empty($adapter['class_exists']) || empty($adapter['instantiated']) || empty($adapter['submit_returned']) || empty($adapter['safe_for_closed_gate'])) {
            $requiredSafe = false;
        }
    }

    return [
        'ok' => $requiredSafe,
        'version' => PRV3_ADAPTER_PROBE_VERSION,
        'mode' => 'read_only_adapter_contract_probe',
        'started_at' => gmdate('c'),
        'safety' => 'No Bolt call. No EDXEIX call. No AADE call. No DB writes. No queue status changes. No production submission tables. V0 untouched.',
        'app_root' => $appRoot,
        'files' => $fileStatus,
        'fixture_payload_keys' => array_keys($fixturePayload),
        'adapters' => $adapters,
        'events' => $events,
        'finished_at' => gmdate('c'),
    ];
}

/** @param mixed $value */
function prv3_adapter_probe_bool($value): string
{
    if ($value === null) {
        return 'n/a';
    }
    return $value ? 'yes' : 'no';
}

function prv3_adapter_probe_cli(): int
{
    $json = in_array('--json', $_SERVER['argv'] ?? [], true);
    $report = prv3_adapter_probe_run();

    if ($json) {
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        return !empty($report['ok']) ? 0 : 1;
    }

    echo 'V3 adapter contract probe ' . PRV3_ADAPTER_PROBE_VERSION . PHP_EOL;
    echo 'Mode: ' . $report['mode'] . PHP_EOL;
    echo 'Safety: ' . $report['safety'] . PHP_EOL;
    echo 'OK: ' . (!empty($report['ok']) ? 'yes' : 'no') . PHP_EOL;
    echo PHP_EOL;

    echo 'Files:' . PHP_EOL;
    foreach ($report['files'] as $key => $file) {
        echo '  ' . $key . ': exists=' . (!empty($file['exists']) ? 'yes' : 'no') . ' readable=' . (!empty($file['readable']) ? 'yes' : 'no') . PHP_EOL;
    }
    echo PHP_EOL;

    echo 'Adapters:' . PHP_EOL;
    foreach ($report['adapters'] as $adapter) {
        echo '  [' . $adapter['key'] . '] class=' . $adapter['class'] . PHP_EOL;
        echo '    class_exists=' . prv3_adapter_probe_bool($adapter['class_exists']) . ' instantiated=' . prv3_adapter_probe_bool($adapter['instantiated']) . ' name=' . ($adapter['name'] ?: '-') . PHP_EOL;
        echo '    live_capable=' . prv3_adapter_probe_bool($adapter['is_live_capable']) . ' submit_returned=' . prv3_adapter_probe_bool($adapter['submit_returned']) . ' submitted=' . prv3_adapter_probe_bool($adapter['submitted']) . PHP_EOL;
        echo '    ok=' . prv3_adapter_probe_bool($adapter['ok']) . ' blocked=' . prv3_adapter_probe_bool($adapter['blocked']) . ' dry_run=' . prv3_adapter_probe_bool($adapter['dry_run']) . ' safe_for_closed_gate=' . prv3_adapter_probe_bool($adapter['safe_for_closed_gate']) . PHP_EOL;
        if ((string)$adapter['reason'] !== '') {
            echo '    reason=' . $adapter['reason'] . PHP_EOL;
        }
        if ((string)$adapter['message'] !== '') {
            echo '    message=' . $adapter['message'] . PHP_EOL;
        }
        if ((string)$adapter['error'] !== '') {
            echo '    ERROR=' . $adapter['error'] . PHP_EOL;
        }
    }

    if (!empty($report['events'])) {
        echo PHP_EOL . 'Events:' . PHP_EOL;
        foreach ($report['events'] as $event) {
            echo '  - ' . $event . PHP_EOL;
        }
    }

    return !empty($report['ok']) ? 0 : 1;
}

if (PHP_SAPI === 'cli' && realpath((string)($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    exit(prv3_adapter_probe_cli());
}
