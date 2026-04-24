<?php

$container = require __DIR__ . '/../src/bootstrap.php';

use Bridge\Repository\BookingRepository;
use Bridge\Repository\JobRepository;
use Bridge\Repository\AttemptRepository;
use Bridge\Repository\MappingRepository;
use Bridge\Domain\MappingService;
use Bridge\Domain\SubmissionService;
use Bridge\Edxeix\SessionStore;
use Bridge\Edxeix\EdxeixFormReader;
use Bridge\Edxeix\EdxeixPayloadBuilder;
use Bridge\Edxeix\EdxeixSubmitter;

$config = $container['config'];
$db = $container['db'];
$http = $container['http'];
$logger = $container['logger'];

$service = new SubmissionService(
    $logger,
    new BookingRepository($db),
    new JobRepository($db),
    new AttemptRepository($db),
    new MappingService(new MappingRepository($db)),
    new EdxeixFormReader($config, $http, new SessionStore($config->get('edxeix.session_file'))),
    new EdxeixPayloadBuilder($config),
    new EdxeixSubmitter($config, $http, new SessionStore($config->get('edxeix.session_file')))
);

$result = $service->processNext('cli-worker');

echo json_encode(['ok' => true, 'result' => $result], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
