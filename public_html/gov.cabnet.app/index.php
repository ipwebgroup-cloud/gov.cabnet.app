<?php

$container = require __DIR__ . '/../../gov.cabnet.app_app/src/bootstrap.php';

use Bridge\Repository\BookingRepository;
use Bridge\Repository\JobRepository;
use Bridge\Repository\AttemptRepository;
use Bridge\Repository\MappingRepository;
use Bridge\Repository\RawPayloadRepository;
use Bridge\Bolt\BoltTokenService;
use Bridge\Bolt\BoltApiClient;
use Bridge\Bolt\BoltSyncService;
use Bridge\Domain\BookingNormalizer;
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

$bookingRepo = new BookingRepository($db);
$jobRepo = new JobRepository($db);
$attemptRepo = new AttemptRepository($db);
$mappingRepo = new MappingRepository($db);
$rawPayloadRepo = new RawPayloadRepository($db);
$normalizer = new BookingNormalizer();
$mappingService = new MappingService($mappingRepo);
$sessionStore = new SessionStore($config->get('edxeix.session_file'));
$formReader = new EdxeixFormReader($config, $http, $sessionStore);
$payloadBuilder = new EdxeixPayloadBuilder($config);
$submitter = new EdxeixSubmitter($config, $http, $sessionStore);
$submissionService = new SubmissionService(
    $logger,
    $bookingRepo,
    $jobRepo,
    $attemptRepo,
    $mappingService,
    $formReader,
    $payloadBuilder,
    $submitter
);

$boltTokenService = new BoltTokenService($config, $http);
$boltApi = new BoltApiClient($config, $http, $boltTokenService);
$boltSync = new BoltSyncService($config, $logger, $boltApi, $rawPayloadRepo, $bookingRepo, $normalizer);

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function requestJson(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $json = json_decode($raw, true);
    return is_array($json) ? $json : [];
}

function requireInternalApiKey(Bridge\Config $config): void
{
    $expected = (string) $config->get('app.internal_api_key');
    $received = (string) ($_SERVER['HTTP_X_INTERNAL_API_KEY'] ?? '');

    if ($expected === '' || !hash_equals($expected, $received)) {
        jsonResponse(['ok' => false, 'error' => 'Unauthorized'], 401);
    }
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($path === '/' && $method === 'GET') {
    jsonResponse([
        'ok' => true,
        'service' => 'gov.cabnet.app',
        'status' => 'online',
        'endpoints' => [
            '/health',
            '/api/edxeix/session/update',
            '/api/bookings/manual',
            '/api/submissions/queue',
            '/api/submissions/process',
            '/api/jobs',
            '/api/attempts',
            '/api/bolt/sync',
        ],
    ]);
}

if ($path === '/health' && $method === 'GET') {
    jsonResponse(['ok' => true, 'service' => 'gov.cabnet.app']);
}

if (str_starts_with($path, '/api/')) {
    requireInternalApiKey($config);
}

if ($path === '/api/edxeix/session/update' && $method === 'POST') {
    $payload = requestJson();
    $sessionStore->write([
        'cookie_header' => (string) ($payload['cookie_header'] ?? ''),
        'csrf_token' => (string) ($payload['csrf_token'] ?? ''),
    ]);

    jsonResponse(['ok' => true]);
}

if ($path === '/api/bookings/manual' && $method === 'POST') {
    $payload = requestJson();
    $normalized = $normalizer->fromManualPayload($payload);

    if ($bookingRepo->findByDedupeHash($normalized['dedupe_hash'])) {
        jsonResponse(['ok' => true, 'duplicate' => true]);
    }

    $id = $bookingRepo->create($normalized);
    jsonResponse(['ok' => true, 'booking_id' => $id], 201);
}

if ($path === '/api/submissions/queue' && $method === 'POST') {
    $payload = requestJson();
    $bookingId = (int) ($payload['booking_id'] ?? 0);

    if ($bookingId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Missing booking_id'], 422);
    }

    $jobId = $jobRepo->queue($bookingId);
    jsonResponse(['ok' => true, 'job_id' => $jobId], 201);
}

if ($path === '/api/submissions/process' && $method === 'POST') {
    $result = $submissionService->processNext('http-api');
    jsonResponse(['ok' => true, 'result' => $result]);
}

if ($path === '/api/jobs' && $method === 'GET') {
    $status = (string) ($_GET['status'] ?? '');
    jsonResponse(['ok' => true, 'jobs' => $jobRepo->list($status)]);
}

if ($path === '/api/attempts' && $method === 'GET') {
    $jobId = (int) ($_GET['job_id'] ?? 0);
    if ($jobId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Missing job_id'], 422);
    }

    jsonResponse(['ok' => true, 'attempts' => $attemptRepo->listByJob($jobId)]);
}

if ($path === '/api/bolt/sync' && $method === 'POST') {
    $result = $boltSync->sync();
    jsonResponse(['ok' => true, 'result' => $result]);
}

jsonResponse(['ok' => false, 'error' => 'Not found', 'path' => $path], 404);