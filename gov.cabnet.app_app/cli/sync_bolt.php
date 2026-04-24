<?php

$container = require __DIR__ . '/../src/bootstrap.php';

use Bridge\Repository\RawPayloadRepository;
use Bridge\Repository\BookingRepository;
use Bridge\Bolt\BoltTokenService;
use Bridge\Bolt\BoltApiClient;
use Bridge\Bolt\BoltSyncService;
use Bridge\Domain\BookingNormalizer;

$config = $container['config'];
$db = $container['db'];
$http = $container['http'];
$logger = $container['logger'];

$service = new BoltSyncService(
    $config,
    $logger,
    new BoltApiClient($config, $http, new BoltTokenService($config, $http)),
    new RawPayloadRepository($db),
    new BookingRepository($db),
    new BookingNormalizer()
);

$result = $service->sync();

echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
