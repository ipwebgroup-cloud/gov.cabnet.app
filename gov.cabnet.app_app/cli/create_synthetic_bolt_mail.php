<?php

declare(strict_types=1);

$container = require __DIR__ . '/../src/bootstrap.php';

use Bridge\Mail\BoltMaildirScanner;
use Bridge\Mail\BoltPreRideImporter;
use Bridge\Mail\BoltSyntheticMailFactory;

$config = $container['config'];
$db = $container['db'];

function cli_arg(string $key, mixed $default = null): mixed
{
    global $argv;
    foreach (($argv ?? []) as $arg) {
        if ($arg === '--' . $key) {
            return true;
        }
        if (str_starts_with($arg, '--' . $key . '=')) {
            return substr($arg, strlen('--' . $key . '='));
        }
    }
    return $default;
}

function out(array $payload): void
{
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
}

$maildir = (string)$config->get('mail.bolt_bridge_maildir', '/home/cabnet/mail/gov.cabnet.app/bolt-bridge');
$timezone = new DateTimeZone((string)$config->get('app.timezone', 'Europe/Athens'));
$futureGuard = (int)$config->get('edxeix.future_start_guard_minutes', 2);
$importNow = (bool)cli_arg('import-now', false);

$options = [
    'lead_minutes' => cli_arg('lead', 15),
    'duration_minutes' => cli_arg('duration', 30),
    'customer_name' => cli_arg('customer', 'CABNET TEST DO NOT SUBMIT'),
    'customer_mobile' => cli_arg('mobile', '+300000000000'),
    'driver_name' => cli_arg('driver', 'Filippos Giannakopoulos'),
    'vehicle_plate' => cli_arg('vehicle', 'EHA2545'),
    'pickup_address' => cli_arg('pickup', 'Mikonos 846 00, Greece'),
    'dropoff_address' => cli_arg('dropoff', 'Chora TEST, Mykonos Chora'),
    'estimated_price' => cli_arg('price', '0.00 eur'),
];

try {
    $factory = new BoltSyntheticMailFactory($maildir, $timezone);
    $created = $factory->create($options);

    $payload = [
        'ok' => true,
        'created' => $created,
        'import_now' => $importNow,
        'future_guard_minutes' => $futureGuard,
        'note' => 'Synthetic maildir-only test. No Bolt API call, no EDXEIX jobs, no live submit.',
    ];

    if ($importNow) {
        $scanner = new BoltMaildirScanner($maildir);
        $importer = new BoltPreRideImporter($db, null, $timezone, $futureGuard);
        $payload['import_result'] = $importer->importFromScanner($scanner, 250, 30);
    }

    out($payload);
} catch (Throwable $e) {
    out([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
    exit(1);
}
