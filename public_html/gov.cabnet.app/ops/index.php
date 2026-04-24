<?php

declare(strict_types=1);

$bootstrapPath = realpath(__DIR__ . '/../../../gov.cabnet.app_app/src/bootstrap.php');

if ($bootstrapPath === false || !is_file($bootstrapPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bootstrap file not found.\n";
    echo "Expected: " . __DIR__ . '/../../../gov.cabnet.app_app/src/bootstrap.php' . "\n";
    exit;
}

try {
    $container = require $bootstrapPath;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Bootstrap load failed.\n";
    echo $e->getMessage() . "\n";
    exit;
}

$config = $container['config'] ?? null;
$db = $container['db'] ?? null;

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function config_get($config, string $key, $default = null)
{
    if (!is_object($config) || !method_exists($config, 'get')) {
        return $default;
    }

    try {
        return $config->get($key, $default);
    } catch (Throwable $e) {
        try {
            $value = $config->get($key);
            return $value === null ? $default : $value;
        } catch (Throwable $e2) {
            return $default;
        }
    }
}

function base_url($config, string $path = ''): string
{
    $base = (string) config_get($config, 'app.base_url', '');
    return rtrim($base, '/') . $path;
}

function api_request($config, string $method, string $path, ?array $payload = null, bool $useInternalKey = true): array
{
    $url = base_url($config, $path);
    $ch = curl_init($url);

    $headers = [
        'Accept: application/json',
    ];

    if ($useInternalKey) {
        $headers[] = 'X-Internal-Api-Key: ' . (string) config_get($config, 'app.internal_api_key', '');
    }

    if ($payload !== null) {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $decoded = null;
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
    }

    return [
        'ok' => $error === '',
        'status' => $status,
        'error' => $error,
        'raw' => $raw,
        'json' => is_array($decoded) ? $decoded : null,
    ];
}

function db_is_pdo($db): bool
{
    return $db instanceof PDO;
}

function db_is_mysqli($db): bool
{
    return $db instanceof mysqli;
}

function db_fetch_scalar($db, string $sql): int
{
    if (db_is_pdo($db)) {
        $stmt = $db->query($sql);
        return $stmt ? (int) $stmt->fetchColumn() : 0;
    }

    if (db_is_mysqli($db)) {
        $result = $db->query($sql);
        if ($result instanceof mysqli_result) {
            $row = $result->fetch_row();
            return isset($row[0]) ? (int) $row[0] : 0;
        }
    }

    return 0;
}

function db_fetch_all($db, string $sql): array
{
    if (db_is_pdo($db)) {
        $stmt = $db->query($sql);
        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    if (db_is_mysqli($db)) {
        $result = $db->query($sql);
        if ($result instanceof mysqli_result) {
            $rows = [];
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            return $rows;
        }
    }

    return [];
}

function db_exec_prepared($db, string $sql, array $params): bool
{
    if (db_is_pdo($db)) {
        $stmt = $db->prepare($sql);
        return $stmt ? $stmt->execute($params) : false;
    }

    if (db_is_mysqli($db)) {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $types = '';
        $values = [];

        foreach ($params as $param) {
            if (is_int($param)) {
                $types .= 'i';
            } elseif (is_float($param)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
            $values[] = $param;
        }

        $bind = [$types];
        foreach ($values as $k => $v) {
            $bind[] = &$values[$k];
        }

        call_user_func_array([$stmt, 'bind_param'], $bind);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }

    return false;
}

function upsert_driver_mapping($db, array $data): bool
{
    if (db_is_pdo($db)) {
        $select = $db->prepare('SELECT id FROM mapping_drivers WHERE external_driver_id = ? LIMIT 1');
        $select->execute([$data['external_driver_id']]);
        $existingId = $select->fetchColumn();

        if ($existingId) {
            return db_exec_prepared(
                $db,
                'UPDATE mapping_drivers
                 SET source_system = ?, external_driver_name = ?, edxeix_driver_id = ?, is_active = 1, updated_at = NOW()
                 WHERE id = ?',
                [
                    $data['source_system'],
                    $data['external_driver_name'],
                    $data['edxeix_driver_id'],
                    (int) $existingId,
                ]
            );
        }

        return db_exec_prepared(
            $db,
            'INSERT INTO mapping_drivers
             (source_system, external_driver_id, external_driver_name, edxeix_driver_id, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
            [
                $data['source_system'],
                $data['external_driver_id'],
                $data['external_driver_name'],
                $data['edxeix_driver_id'],
            ]
        );
    }

    if (db_is_mysqli($db)) {
        $stmt = $db->prepare('SELECT id FROM mapping_drivers WHERE external_driver_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $externalDriverId = $data['external_driver_id'];
        $stmt->bind_param('s', $externalDriverId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($existing && isset($existing['id'])) {
            return db_exec_prepared(
                $db,
                'UPDATE mapping_drivers
                 SET source_system = ?, external_driver_name = ?, edxeix_driver_id = ?, is_active = 1, updated_at = NOW()
                 WHERE id = ?',
                [
                    $data['source_system'],
                    $data['external_driver_name'],
                    $data['edxeix_driver_id'],
                    (int) $existing['id'],
                ]
            );
        }

        return db_exec_prepared(
            $db,
            'INSERT INTO mapping_drivers
             (source_system, external_driver_id, external_driver_name, edxeix_driver_id, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
            [
                $data['source_system'],
                $data['external_driver_id'],
                $data['external_driver_name'],
                $data['edxeix_driver_id'],
            ]
        );
    }

    return false;
}

function upsert_vehicle_mapping($db, array $data): bool
{
    if (db_is_pdo($db)) {
        $select = $db->prepare('SELECT id FROM mapping_vehicles WHERE external_vehicle_id = ? LIMIT 1');
        $select->execute([$data['external_vehicle_id']]);
        $existingId = $select->fetchColumn();

        if ($existingId) {
            return db_exec_prepared(
                $db,
                'UPDATE mapping_vehicles
                 SET source_system = ?, plate = ?, edxeix_vehicle_id = ?, is_active = 1, updated_at = NOW()
                 WHERE id = ?',
                [
                    $data['source_system'],
                    $data['plate'],
                    $data['edxeix_vehicle_id'],
                    (int) $existingId,
                ]
            );
        }

        return db_exec_prepared(
            $db,
            'INSERT INTO mapping_vehicles
             (source_system, external_vehicle_id, plate, edxeix_vehicle_id, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
            [
                $data['source_system'],
                $data['external_vehicle_id'],
                $data['plate'],
                $data['edxeix_vehicle_id'],
            ]
        );
    }

    if (db_is_mysqli($db)) {
        $stmt = $db->prepare('SELECT id FROM mapping_vehicles WHERE external_vehicle_id = ? LIMIT 1');
        if (!$stmt) {
            return false;
        }
        $externalVehicleId = $data['external_vehicle_id'];
        $stmt->bind_param('s', $externalVehicleId);
        $stmt->execute();
        $result = $stmt->get_result();
        $existing = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($existing && isset($existing['id'])) {
            return db_exec_prepared(
                $db,
                'UPDATE mapping_vehicles
                 SET source_system = ?, plate = ?, edxeix_vehicle_id = ?, is_active = 1, updated_at = NOW()
                 WHERE id = ?',
                [
                    $data['source_system'],
                    $data['plate'],
                    $data['edxeix_vehicle_id'],
                    (int) $existing['id'],
                ]
            );
        }

        return db_exec_prepared(
            $db,
            'INSERT INTO mapping_vehicles
             (source_system, external_vehicle_id, plate, edxeix_vehicle_id, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, NOW(), NOW())',
            [
                $data['source_system'],
                $data['external_vehicle_id'],
                $data['plate'],
                $data['edxeix_vehicle_id'],
            ]
        );
    }

    return false;
}

$timezone = (string) config_get($config, 'app.timezone', 'Europe/Athens');
date_default_timezone_set($timezone);

$messages = [];
$errors = [];
$payloadPreview = '';
$lastActionResult = null;

$sessionFile = (string) config_get($config, 'edxeix.session_file', '');
$storagePath = (string) config_get($config, 'paths.storage', '');
$logsPath = (string) config_get($config, 'paths.logs', '');
$artifactsPath = (string) config_get($config, 'paths.artifacts', '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'save_session') {
            $cookieHeader = trim((string) ($_POST['cookie_header'] ?? ''));
            $csrfToken = trim((string) ($_POST['csrf_token'] ?? ''));

            if ($cookieHeader === '' || $csrfToken === '') {
                throw new RuntimeException('Απαιτούνται cookie_header και csrf_token.');
            }

            $result = api_request($config, 'POST', '/api/edxeix/session/update', [
                'cookie_header' => $cookieHeader,
                'csrf_token' => $csrfToken,
            ]);

            $lastActionResult = $result;

            if (($result['json']['ok'] ?? false) === true) {
                $messages[] = 'Το EDXEIX session αποθηκεύτηκε επιτυχώς.';
            } else {
                throw new RuntimeException('Αποτυχία αποθήκευσης session.');
            }
        }

        if ($action === 'save_driver_mapping') {
            $externalDriverId = trim((string) ($_POST['external_driver_id'] ?? ''));
            $externalDriverName = trim((string) ($_POST['external_driver_name'] ?? ''));
            $edxeixDriverId = trim((string) ($_POST['edxeix_driver_id'] ?? ''));

            if ($externalDriverId === '' || $externalDriverName === '' || $edxeixDriverId === '') {
                throw new RuntimeException('Συμπληρώστε όλα τα πεδία αντιστοίχισης οδηγού.');
            }

            if (!upsert_driver_mapping($db, [
                'source_system' => 'manual',
                'external_driver_id' => $externalDriverId,
                'external_driver_name' => $externalDriverName,
                'edxeix_driver_id' => $edxeixDriverId,
            ])) {
                throw new RuntimeException('Αποτυχία αποθήκευσης driver mapping.');
            }

            $messages[] = 'Η αντιστοίχιση οδηγού αποθηκεύτηκε.';
        }

        if ($action === 'save_vehicle_mapping') {
            $externalVehicleId = trim((string) ($_POST['external_vehicle_id'] ?? ''));
            $plate = trim((string) ($_POST['plate'] ?? ''));
            $edxeixVehicleId = trim((string) ($_POST['edxeix_vehicle_id'] ?? ''));

            if ($externalVehicleId === '' || $plate === '' || $edxeixVehicleId === '') {
                throw new RuntimeException('Συμπληρώστε όλα τα πεδία αντιστοίχισης οχήματος.');
            }

            if (!upsert_vehicle_mapping($db, [
                'source_system' => 'manual',
                'external_vehicle_id' => $externalVehicleId,
                'plate' => $plate,
                'edxeix_vehicle_id' => $edxeixVehicleId,
            ])) {
                throw new RuntimeException('Αποτυχία αποθήκευσης vehicle mapping.');
            }

            $messages[] = 'Η αντιστοίχιση οχήματος αποθηκεύτηκε.';
        }

        if ($action === 'create_manual_booking') {
            $driverCompound = (string) ($_POST['driver_compound'] ?? '');
            $vehicleCompound = (string) ($_POST['vehicle_compound'] ?? '');
            $startingPointKey = trim((string) ($_POST['starting_point_key'] ?? ''));
            $lesseeType = trim((string) ($_POST['lessee_type'] ?? 'natural'));
            $lesseeName = trim((string) ($_POST['lessee_name'] ?? ''));
            $boardingPoint = trim((string) ($_POST['boarding_point'] ?? ''));
            $coordinates = trim((string) ($_POST['coordinates'] ?? ''));
            $disembarkPoint = trim((string) ($_POST['disembark_point'] ?? ''));
            $draftedAt = trim((string) ($_POST['drafted_at'] ?? ''));
            $startedAt = trim((string) ($_POST['started_at'] ?? ''));
            $endedAt = trim((string) ($_POST['ended_at'] ?? ''));
            $price = trim((string) ($_POST['price'] ?? '82.00'));
            $brokerKey = trim((string) ($_POST['broker_key'] ?? ''));
            $autoQueue = isset($_POST['auto_queue']);
            $autoProcess = isset($_POST['auto_process']);

            if ($driverCompound === '' || $vehicleCompound === '' || $startingPointKey === '' || $lesseeName === '' || $boardingPoint === '' || $disembarkPoint === '' || $draftedAt === '' || $startedAt === '' || $endedAt === '') {
                throw new RuntimeException('Συμπληρώστε όλα τα υποχρεωτικά πεδία της δοκιμαστικής σύμβασης.');
            }

            if (!str_contains($driverCompound, '||') || !str_contains($vehicleCompound, '||')) {
                throw new RuntimeException('Μη έγκυρη επιλογή οδηγού ή οχήματος.');
            }

            [$driverExternalId, $driverName] = explode('||', $driverCompound, 2);
            [$vehicleExternalId, $vehiclePlate] = explode('||', $vehicleCompound, 2);

            $startedTs = strtotime($startedAt);
            $endedTs = strtotime($endedAt);
            $draftedTs = strtotime($draftedAt);
            $minStartTs = time() + (30 * 60);

            if ($startedTs === false || $endedTs === false || $draftedTs === false) {
                throw new RuntimeException('Μη έγκυρες ημερομηνίες.');
            }

            if ($startedTs < $minStartTs) {
                throw new RuntimeException('Η ώρα έναρξης πρέπει να είναι τουλάχιστον 30 λεπτά στο μέλλον.');
            }

            if ($endedTs <= $startedTs) {
                throw new RuntimeException('Η ώρα λήξης πρέπει να είναι μετά την ώρα έναρξης.');
            }

            $manualPayload = [
                'source' => 'manual',
                'source_trip_id' => 'ops-' . date('YmdHis'),
                'source_booking_id' => 'ops-booking-' . date('YmdHis'),
                'status' => 'confirmed',
                'customer_type' => $lesseeType,
                'customer_name' => $lesseeName,
                'customer_vat_number' => trim((string) ($_POST['lessee_vat'] ?? '')),
                'customer_representative' => trim((string) ($_POST['lessee_representative'] ?? '')),
                'driver_external_id' => $driverExternalId,
                'driver_name' => $driverName,
                'vehicle_external_id' => $vehicleExternalId,
                'vehicle_plate' => $vehiclePlate,
                'starting_point_key' => $startingPointKey,
                'boarding_point' => $boardingPoint,
                'coordinates' => $coordinates,
                'disembark_point' => $disembarkPoint,
                'drafted_at' => date('Y-m-d H:i:00', $draftedTs),
                'started_at' => date('Y-m-d H:i:00', $startedTs),
                'ended_at' => date('Y-m-d H:i:00', $endedTs),
                'price' => $price,
                'currency' => 'EUR',
                'broker_key' => $brokerKey,
            ];

            $payloadPreview = json_encode($manualPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

            $createResult = api_request($config, 'POST', '/api/bookings/manual', $manualPayload);
            $lastActionResult = $createResult;

            if (($createResult['json']['ok'] ?? false) !== true) {
                throw new RuntimeException('Αποτυχία δημιουργίας manual booking.');
            }

            $bookingId = (int) ($createResult['json']['booking_id'] ?? 0);
            if ($bookingId <= 0) {
                throw new RuntimeException('Δεν επιστράφηκε booking_id.');
            }

            $messages[] = 'Δημιουργήθηκε manual booking με ID ' . $bookingId . '.';

            if ($autoQueue) {
                $queueResult = api_request($config, 'POST', '/api/submissions/queue', [
                    'booking_id' => $bookingId,
                ]);
                $lastActionResult = $queueResult;

                if (($queueResult['json']['ok'] ?? false) !== true) {
                    throw new RuntimeException('Δημιουργήθηκε το booking, αλλά απέτυχε το queue.');
                }

                $jobId = (int) ($queueResult['json']['job_id'] ?? 0);
                $messages[] = 'Το booking μπήκε στην ουρά με job ID ' . $jobId . '.';

                if ($autoProcess) {
                    $processResult = api_request($config, 'POST', '/api/submissions/process', []);
                    $lastActionResult = $processResult;

                    if (($processResult['json']['ok'] ?? false) !== true) {
                        throw new RuntimeException('Το booking μπήκε στην ουρά, αλλά απέτυχε το process.');
                    }

                    $messages[] = 'Εκτελέστηκε processNext επιτυχώς.';
                }
            }
        }

        if ($action === 'process_next_job') {
            $processResult = api_request($config, 'POST', '/api/submissions/process', []);
            $lastActionResult = $processResult;

            if (($processResult['json']['ok'] ?? false) !== true) {
                throw new RuntimeException('Αποτυχία processNext.');
            }

            $messages[] = 'Εκτελέστηκε processNext επιτυχώς.';
        }

        if ($action === 'run_bolt_sync') {
            $syncResult = api_request($config, 'POST', '/api/bolt/sync', []);
            $lastActionResult = $syncResult;

            if (($syncResult['json']['ok'] ?? false) !== true) {
                throw new RuntimeException('Αποτυχία Bolt sync.');
            }

            $messages[] = 'Εκτελέστηκε Bolt sync επιτυχώς.';
        }
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
    }
}

$health = api_request($config, 'GET', '/health', null, false);

$driverMappings = db_fetch_all($db, 'SELECT * FROM mapping_drivers ORDER BY updated_at DESC, id DESC');
$vehicleMappings = db_fetch_all($db, 'SELECT * FROM mapping_vehicles ORDER BY updated_at DESC, id DESC');
$startingPointMappings = db_fetch_all($db, 'SELECT * FROM mapping_starting_points WHERE is_active = 1 ORDER BY id ASC');

$jobs = db_fetch_all($db, '
    SELECT
        j.id AS job_id,
        j.status AS job_status,
        j.retry_count,
        j.last_error,
        j.created_at AS job_created_at,
        b.id AS booking_id,
        b.source_trip_id,
        b.customer_name,
        b.driver_name,
        b.vehicle_plate,
        b.started_at,
        b.price
    FROM submission_jobs j
    LEFT JOIN normalized_bookings b ON b.id = j.normalized_booking_id
    ORDER BY j.id DESC
    LIMIT 20
');

$attempts = db_fetch_all($db, '
    SELECT
        a.id AS attempt_id,
        a.submission_job_id,
        a.response_status,
        a.success,
        a.remote_reference,
        a.created_at,
        j.status AS job_status,
        b.source_trip_id
    FROM submission_attempts a
    LEFT JOIN submission_jobs j ON j.id = a.submission_job_id
    LEFT JOIN normalized_bookings b ON b.id = j.normalized_booking_id
    ORDER BY a.id DESC
    LIMIT 15
');

$stats = [
    'drivers' => db_fetch_scalar($db, 'SELECT COUNT(*) FROM mapping_drivers'),
    'vehicles' => db_fetch_scalar($db, 'SELECT COUNT(*) FROM mapping_vehicles'),
    'bookings' => db_fetch_scalar($db, 'SELECT COUNT(*) FROM normalized_bookings'),
    'jobs_pending' => db_fetch_scalar($db, 'SELECT COUNT(*) FROM submission_jobs WHERE status = "pending"'),
    'jobs_submitted' => db_fetch_scalar($db, 'SELECT COUNT(*) FROM submission_jobs WHERE status = "submitted"'),
    'attempts' => db_fetch_scalar($db, 'SELECT COUNT(*) FROM submission_attempts'),
];

$sessionData = [];
if ($sessionFile !== '' && is_file($sessionFile)) {
    $rawSession = file_get_contents($sessionFile);
    $decoded = json_decode((string) $rawSession, true);
    if (is_array($decoded)) {
        $sessionData = $decoded;
    }
}

$sessionCookieHeader = (string) ($sessionData['cookie_header'] ?? '');
$sessionCsrfToken = (string) ($sessionData['csrf_token'] ?? '');

$now = new DateTimeImmutable('now');
$defaultDrafted = $now->format('Y-m-d\TH:i');
$defaultStarted = $now->modify('+45 minutes')->format('Y-m-d\TH:i');
$defaultEnded = $now->modify('+75 minutes')->format('Y-m-d\TH:i');

$healthOk = ($health['json']['ok'] ?? false) === true;
$sessionOk = $sessionCookieHeader !== '' && $sessionCsrfToken !== '';
$storageOk = $storagePath !== '' && is_dir($storagePath) && is_writable($storagePath);
$logsOk = $logsPath !== '' && is_dir($logsPath) && is_writable($logsPath);
$artifactsOk = $artifactsPath !== '' && is_dir($artifactsPath) && is_writable($artifactsPath);
?>
<!DOCTYPE html>
<html lang="el">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>
        gov.cabnet.app | Διαχειριστικό Γέφυρας EDXEIX / Bolt
    </title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root{
            --sidebar:#2d3350;
            --sidebar-dark:#252a43;
            --sidebar-accent:#4b5aa7;
            --border:#dee2e6;
            --bg:#f4f6f9;
            --card:#ffffff;
            --text:#212529;
            --muted:#6c757d;
            --primary:#2f5bea;
            --success:#28a745;
            --warning:#ffc107;
            --danger:#dc3545;
            --light:#f8f9fa;
        }

        * { box-sizing:border-box; }
        html, body { margin:0; padding:0; font-family:'Inter', sans-serif; background:var(--bg); color:var(--text); }
        body { min-height:100vh; }
        a { color:inherit; text-decoration:none; }

        .wrapper { min-height:100vh; }
        .main-header {
            position:fixed; top:0; left:0; right:0; height:60px; background:#fff; border-bottom:1px solid var(--border);
            display:flex; align-items:center; justify-content:space-between; padding:0 18px; z-index:1000;
        }
        .main-header .left, .main-header .right { display:flex; align-items:center; gap:18px; }
        .nav-link { font-size:14px; color:#334; font-weight:500; }
        .bell {
            width:36px; height:36px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center;
            background:#f3f4f7; border:1px solid var(--border); font-size:16px;
        }
        .logout-link { font-size:14px; font-weight:500; color:#555; }

        .main-sidebar {
            position:fixed; top:60px; left:0; bottom:0; width:290px; background:var(--sidebar); color:#fff;
            overflow-y:auto; box-shadow:2px 0 8px rgba(0,0,0,.08);
        }

        .brand-link {
            display:flex; align-items:center; gap:12px; background:#fff; color:#1d3b7a; padding:16px 18px;
            border-bottom:1px solid rgba(255,255,255,.08);
        }

        .brand-icon {
            width:52px; height:52px; border-radius:50%; background:#eef3ff; display:flex; align-items:center; justify-content:center;
            font-size:22px; font-weight:700; color:#2f5bea; border:1px solid #d7e1ff;
        }

        .brand-text { line-height:1.25; }
        .brand-text .small { font-size:12px; text-transform:uppercase; font-weight:700; color:#244a95; }
        .brand-text .sub { font-size:13px; color:#3257a1; }

        .sidebar { padding:16px 0 30px; }
        .user-panel { padding:0 18px 18px; border-bottom:1px solid rgba(255,255,255,.08); margin-bottom:10px; }
        .user-title { font-size:20px; font-weight:700; margin-bottom:6px; }
        .user-subtitle { font-size:13px; color:rgba(255,255,255,.72); line-height:1.5; }

        .nav-sidebar { list-style:none; padding:0; margin:0; }
        .nav-sidebar li { margin:4px 10px; }
        .nav-sidebar a {
            display:block; padding:12px 14px; border-radius:8px; color:#fff; font-size:15px; line-height:1.4; transition:.15s ease;
        }
        .nav-sidebar a:hover { background:rgba(255,255,255,.08); }
        .nav-sidebar a.active { background:var(--sidebar-accent); }

        .content-wrapper { margin-left:290px; padding-top:60px; min-height:100vh; }
        .content-header { background:transparent; padding:24px 28px 10px; }
        .content-header-row { display:flex; justify-content:space-between; align-items:flex-start; gap:20px; flex-wrap:wrap; }
        .page-title { margin:0; font-size:34px; font-weight:700; color:#2d2f33; }

        .breadcrumb { list-style:none; display:flex; gap:8px; padding:0; margin:8px 0 0; flex-wrap:wrap; color:#667085; font-size:14px; }
        .breadcrumb li::after { content:"/"; margin-left:8px; color:#9aa2af; }
        .breadcrumb li:last-child::after { content:""; margin:0; }

        .content { padding:8px 28px 40px; }
        .container-fluid { width:100%; }

        .row { display:flex; flex-wrap:wrap; margin:-10px; }
        .col-xl-8, .col-xl-4, .col-lg-6, .col-lg-12, .col-md-6, .col-md-4, .col-md-8, .col-12 {
            padding:10px; width:100%;
        }
        .col-xl-8 { width:66.6666%; }
        .col-xl-4 { width:33.3333%; }
        .col-lg-6 { width:50%; }
        .col-lg-12 { width:100%; }
        .col-md-6 { width:50%; }
        .col-md-4 { width:33.3333%; }
        .col-md-8 { width:66.6666%; }

        .card {
            background:var(--card); border:1px solid var(--border); border-radius:10px; overflow:hidden; box-shadow:0 1px 2px rgba(16,24,40,.04);
        }
        .card-header {
            padding:16px 20px; font-size:18px; font-weight:700; border-bottom:1px solid var(--border); background:#fff;
        }
        .card-body { padding:18px 20px; }
        .border-bottom { border-bottom:1px solid var(--border); }
        .section-note { font-size:13px; color:var(--muted); line-height:1.6; }

        .form-group { margin-bottom:16px; }
        .form-group.row { display:flex; flex-wrap:wrap; margin-bottom:16px; }
        .col-form-label { font-weight:600; color:#374151; padding-top:10px; }
        .text-md-right { text-align:right; }

        label { display:block; margin-bottom:8px; font-size:14px; font-weight:600; }
        .form-control, textarea, select, input[type="text"], input[type="number"], input[type="datetime-local"], input[type="password"] {
            width:100%; border:1px solid #ced4da; border-radius:8px; background:#fff; padding:11px 12px; font-size:14px; color:#212529;
            min-height:44px; outline:none; transition:border-color .15s ease, box-shadow .15s ease;
        }
        .form-control:focus, textarea:focus, select:focus, input:focus {
            border-color:#7aa6ff; box-shadow:0 0 0 3px rgba(47,91,234,.12);
        }
        textarea { min-height:110px; resize:vertical; }
        .small-textarea { min-height:78px; }

        .custom-radio-row { display:flex; flex-wrap:wrap; gap:18px; margin-bottom:14px; }
        .custom-radio { display:flex; align-items:center; gap:8px; font-size:14px; font-weight:600; }
        .input-help { margin-top:6px; font-size:12px; color:var(--muted); }

        .input-group { display:flex; align-items:stretch; }
        .input-group-prepend { display:flex; }
        .input-group-text {
            display:flex; align-items:center; justify-content:center; min-width:48px; padding:0 12px; border:1px solid #ced4da; border-right:0;
            border-radius:8px 0 0 8px; background:#f8f9fa; font-weight:700;
        }
        .input-group .form-control { border-radius:0 8px 8px 0; }

        .badge {
            display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:12px; font-weight:700;
        }
        .badge-success { background:#e8f7ed; color:#177a35; }
        .badge-warning { background:#fff7df; color:#9a6b00; }
        .badge-danger { background:#fdebec; color:#b42318; }
        .badge-info { background:#e8f0ff; color:#234db8; }

        .stat-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:12px; }
        .stat-card { border:1px solid var(--border); border-radius:10px; padding:14px; background:#fff; }
        .stat-label { font-size:12px; color:var(--muted); margin-bottom:8px; }
        .stat-value { font-size:20px; font-weight:700; }

        .btn-row { display:flex; justify-content:flex-end; gap:10px; flex-wrap:wrap; padding-top:4px; }
        .btn {
            display:inline-flex; align-items:center; justify-content:center; min-height:42px; padding:10px 16px; border-radius:8px; border:1px solid transparent;
            cursor:pointer; font-size:14px; font-weight:600; transition:.15s ease;
        }
        .btn-primary { background:var(--primary); color:#fff; }
        .btn-primary:hover { filter:brightness(.96); }
        .btn-default { background:#fff; border-color:#ced4da; color:#374151; }
        .btn-success { background:var(--success); color:#fff; }
        .btn-warning { background:var(--warning); color:#3d2b00; }
        .btn-danger { background:var(--danger); color:#fff; }

        .toolbar { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .table-wrap { overflow:auto; border:1px solid var(--border); border-radius:10px; background:#fff; }
        table { width:100%; border-collapse:collapse; min-width:760px; }
        th, td { border-bottom:1px solid var(--border); padding:12px 14px; text-align:left; font-size:14px; vertical-align:top; }
        th { background:#f8fafc; font-weight:700; }

        .mono { font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; word-break:break-all; }

        .alert { padding:14px 16px; border-radius:8px; border:1px solid transparent; margin-bottom:16px; font-size:14px; line-height:1.6; }
        .alert-info { background:#eef4ff; border-color:#d8e5ff; color:#244a95; }
        .alert-warning { background:#fff8e7; border-color:#ffe2a8; color:#7d5900; }
        .alert-success { background:#ebf9ef; border-color:#caecd3; color:#166534; }
        .alert-danger { background:#fdebec; border-color:#f5c2c7; color:#842029; }

        .main-footer { margin-left:290px; border-top:1px solid var(--border); background:#fff; padding:18px 28px 24px; color:#6b7280; font-size:13px; }
        .main-footer-grid { display:flex; gap:20px; justify-content:space-between; flex-wrap:wrap; }
        .muted { color:var(--muted); }

        .code-box {
            background:#0f172a; color:#d1e7ff; border-radius:10px; padding:14px; overflow:auto;
            font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:12px; line-height:1.6; white-space:pre-wrap;
        }

        .pill-tabs { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:14px; }
        .pill-tab { padding:8px 12px; border-radius:999px; border:1px solid var(--border); background:#fff; font-size:13px; font-weight:600; }
        .pill-tab.active { background:#eaf0ff; border-color:#bfd1ff; color:#224db9; }

        .kpi-green { color:#166534; }
        .kpi-red { color:#b42318; }
        .kpi-amber { color:#9a6b00; }

        @media (max-width: 1200px) {
            .col-xl-8, .col-xl-4, .col-lg-6, .col-md-6, .col-md-4, .col-md-8 { width:100%; }
        }

        @media (max-width: 992px) {
            .main-sidebar { position:relative; width:100%; top:60px; height:auto; }
            .content-wrapper, .main-footer { margin-left:0; }
            .content-wrapper { padding-top:60px; }
            .text-md-right { text-align:left; }
        }
    </style>
</head>
<body class="hold-transition sidebar-mini">
<div id="app" class="wrapper">

    <nav class="main-header navbar navbar-expand bg-white navbar-light border-bottom">
        <div class="left">
            <a class="nav-link" href="#">☰</a>
            <a class="nav-link" href="/ops/index.php">ΑΡΧΙΚΗ</a>
            <a class="nav-link" href="/health">HEALTH</a>
            <a class="nav-link" href="#diagnostics-box">ΔΙΑΓΝΩΣΤΙΚΑ</a>
            <a class="nav-link" href="#jobs-box">ΟΥΡΑ / JOBS</a>
        </div>

        <div class="right">
            <span class="bell">🔔</span>
            <span class="logout-link">Operations Console</span>
        </div>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a class="brand-link clearfix bg-white d-flex align-items-center" href="/ops/index.php">
            <div class="brand-icon">GC</div>
            <div class="brand-text">
                <div class="small">gov.cabnet.app</div>
                <div class="sub">Γέφυρα EDXEIX / Bolt</div>
            </div>
        </a>

        <div class="sidebar">
            <div class="user-panel">
                <div class="user-title">LUXLIMO Ι Κ Ε</div>
                <div class="user-subtitle">Εκμισθωτής Ε.Ι.Χ. Οχημάτων με οδηγό<br>Operations Console</div>
            </div>

            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column">
                    <li><a href="/ops/index.php" class="active">Κεντρικός πίνακας</a></li>
                    <li><a href="#session-box">Διαχείριση session EDXEIX</a></li>
                    <li><a href="#manual-booking-box">Νέα δοκιμαστική σύμβαση</a></li>
                    <li><a href="#driver-map-box">Αντιστοίχιση οδηγών</a></li>
                    <li><a href="#vehicle-map-box">Αντιστοίχιση οχημάτων</a></li>
                    <li><a href="#diagnostics-box">Διαγνωστικά</a></li>
                    <li><a href="#jobs-box">Jobs / Queue</a></li>
                    <li><a href="#attempts-box">Attempts / Logs</a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="content-header-row">
                    <div>
                        <h1 class="page-title">Ανάρτηση σύμβασης / Operations GUI</h1>
                        <ol class="breadcrumb">
                            <li><a href="/ops/index.php">Αρχική</a></li>
                            <li><a href="/ops/index.php">Διαχειριστικό</a></li>
                            <li><a href="/ops/index.php">Συμβάσεις δοκιμής</a></li>
                            <li>Ανάρτηση</li>
                        </ol>
                    </div>

                    <div>
                        <?php if ($healthOk): ?>
                            <span class="badge badge-success">FRAMEWORK ONLINE</span>
                        <?php else: ?>
                            <span class="badge badge-danger">FRAMEWORK ISSUE</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">

                <?php foreach ($messages as $message): ?>
                    <div class="alert alert-success"><?= h($message) ?></div>
                <?php endforeach; ?>

                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger"><?= h($error) ?></div>
                <?php endforeach; ?>

                <div class="alert alert-info">
                    Το GUI αυτό ακολουθεί τη λογική και την αισθητική του περιβάλλοντος EDXEIX, αλλά χρησιμοποιείται ως
                    εσωτερικό διαχειριστικό για το <strong>gov.cabnet.app</strong>. Όλες οι δοκιμαστικές αναρτήσεις πρέπει να έχουν
                    <strong>ώρα έναρξης τουλάχιστον 30 λεπτά στο μέλλον</strong>.
                </div>

                <div class="row" id="diagnostics-box">
                    <div class="col-xl-8">
                        <div class="card" id="manual-booking-box">
                            <div class="card-header">Ανάρτηση δοκιμαστικής σύμβασης ενοικίασης</div>

                            <div class="card-body border-bottom">
                                <div class="form-group row">
                                    <label for="broker_key" class="col-md-4 col-form-label text-md-right">
                                        Φορέας διαμεσολάβησης
                                    </label>

                                    <div class="col-md-8">
                                        <select class="form-control" id="broker_key" name="broker_key" form="manualBookingForm">
                                            <option value="" selected>Παρακαλούμε επιλέξτε</option>
                                            <option value="bolt">Bolt</option>
                                            <option value="manual">Manual Internal Test</option>
                                        </select>
                                        <div class="input-help">Προαιρετικό στο εσωτερικό test mode.</div>
                                    </div>
                                </div>
                            </div>

                            <form id="manualBookingForm" action="" method="POST">
                                <input type="hidden" name="action" value="create_manual_booking">

                                <fieldset class="card-body border-bottom">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <h4>Στοιχεία μισθωτή</h4>
                                            <div class="section-note">
                                                Εσωτερική δοκιμαστική φόρμα που ακολουθεί τη διάταξη EDXEIX.
                                            </div>
                                        </div>

                                        <div class="col-md-8">
                                            <div class="custom-radio-row">
                                                <label class="custom-radio">
                                                    <input type="radio" name="lessee_type" value="natural" checked>
                                                    Φυσικό πρόσωπο
                                                </label>

                                                <label class="custom-radio">
                                                    <input type="radio" name="lessee_type" value="legal">
                                                    Νομικό πρόσωπο
                                                </label>
                                            </div>

                                            <div class="form-group">
                                                <label for="lessee_name">Ονοματεπώνυμο Μισθωτή / Επιβάτη / Επικεφαλής Επιβατών</label>
                                                <input class="form-control" id="lessee_name" name="lessee_name" type="text" value="TEST PASSENGER">
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="lessee_vat">ΑΦΜ</label>
                                                        <input class="form-control" id="lessee_vat" name="lessee_vat" type="text" placeholder="Μόνο για νομικό πρόσωπο">
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="lessee_representative">Επικεφαλής Επιβατών</label>
                                                        <input class="form-control" id="lessee_representative" name="lessee_representative" type="text" placeholder="Μόνο για νομικό πρόσωπο">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </fieldset>

                                <div class="card-body border-bottom">
                                    <div class="form-group row">
                                        <label class="col-md-4 col-form-label text-md-right" for="driver_compound">
                                            Οδηγός
                                        </label>

                                        <div class="col-md-8">
                                            <select class="form-control" id="driver_compound" name="driver_compound" required>
                                                <option value="" disabled selected>Παρακαλούμε επιλέξτε</option>
                                                <?php foreach ($driverMappings as $driver): ?>
                                                    <option value="<?= h((string) $driver['external_driver_id'] . '||' . (string) $driver['external_driver_name']) ?>">
                                                        <?= h((string) $driver['external_driver_name']) ?> (EDXEIX: <?= h((string) $driver['edxeix_driver_id']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="vehicle_compound" class="col-md-4 col-form-label text-md-right">
                                            Όχημα
                                        </label>

                                        <div class="col-md-8">
                                            <select class="form-control" id="vehicle_compound" name="vehicle_compound" required>
                                                <option value="" disabled selected>Παρακαλούμε επιλέξτε</option>
                                                <?php foreach ($vehicleMappings as $vehicle): ?>
                                                    <option value="<?= h((string) $vehicle['external_vehicle_id'] . '||' . (string) $vehicle['plate']) ?>">
                                                        <?= h((string) $vehicle['plate']) ?> (EDXEIX: <?= h((string) $vehicle['edxeix_vehicle_id']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="starting_point_key" class="col-md-4 col-form-label text-md-right">
                                            Σημείο έναρξης
                                        </label>

                                        <div class="col-md-8">
                                            <select class="form-control" id="starting_point_key" name="starting_point_key" required>
                                                <option value="" disabled selected>Παρακαλούμε επιλέξτε</option>
                                                <?php foreach ($startingPointMappings as $point): ?>
                                                    <option value="<?= h((string) $point['internal_key']) ?>">
                                                        <?= h((string) $point['label']) ?> (EDXEIX: <?= h((string) $point['edxeix_starting_point_id']) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="boarding_point" class="col-md-4 col-form-label text-md-right">
                                            Σημείο επιβίβασης
                                        </label>

                                        <div class="col-md-8">
                                            <textarea class="form-control small-textarea" id="boarding_point" name="boarding_point">Mykonos Airport</textarea>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="coordinates" class="col-md-4 col-form-label text-md-right">
                                            Συντεταγμένες
                                        </label>

                                        <div class="col-md-8">
                                            <input class="form-control" id="coordinates" name="coordinates" type="text" value="" placeholder="π.χ. 37.4351,25.3489">
                                            <div class="input-help">Προαιρετικό στο test mode.</div>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="disembark_point" class="col-md-4 col-form-label text-md-right">
                                            Σημείο αποβίβασης
                                        </label>

                                        <div class="col-md-8">
                                            <textarea class="form-control small-textarea" id="disembark_point" name="disembark_point">Mykonos Town</textarea>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="drafted_at" class="col-md-4 col-form-label text-md-right">
                                            Ημερομηνία και ώρα κατάρτισης
                                        </label>

                                        <div class="col-md-8">
                                            <input class="form-control" id="drafted_at" name="drafted_at" type="datetime-local" value="<?= h($defaultDrafted) ?>">
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="started_at" class="col-md-4 col-form-label text-md-right">
                                            Ημερομηνία και ώρα έναρξης
                                        </label>

                                        <div class="col-md-8">
                                            <input class="form-control" id="started_at" name="started_at" type="datetime-local" value="<?= h($defaultStarted) ?>">
                                            <div class="input-help" id="futureRuleMessage">
                                                Η ώρα έναρξης πρέπει να είναι τουλάχιστον 30 λεπτά στο μέλλον.
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="ended_at" class="col-md-4 col-form-label text-md-right">
                                            Ημερομηνία και ώρα λήξης
                                        </label>

                                        <div class="col-md-8">
                                            <input class="form-control" id="ended_at" name="ended_at" type="datetime-local" value="<?= h($defaultEnded) ?>">
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label for="price" class="col-md-4 col-form-label text-md-right">
                                            Τίμημα
                                        </label>

                                        <div class="col-md-8">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">€</span>
                                                </div>
                                                <input class="form-control" id="price" name="price" type="number" value="82.00" step="0.01" min="82">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group row">
                                        <label class="col-md-4 col-form-label text-md-right">Εκτέλεση</label>
                                        <div class="col-md-8">
                                            <label class="custom-radio" style="margin-bottom:8px;">
                                                <input type="checkbox" name="auto_queue" value="1" checked>
                                                Αυτόματο queue μετά τη δημιουργία
                                            </label>
                                            <label class="custom-radio">
                                                <input type="checkbox" name="auto_process" value="1">
                                                Αυτόματο processNext μετά το queue
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <div class="btn-row">
                                        <button class="btn btn-warning" type="button" id="previewPayloadBtn">Preview payload</button>
                                        <button class="btn btn-primary" type="submit">Καταχώριση δοκιμής</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-xl-4">
                        <div class="card">
                            <div class="card-header">Διαγνωστικά συστήματος</div>
                            <div class="card-body">
                                <div class="stat-grid">
                                    <div class="stat-card">
                                        <div class="stat-label">Framework</div>
                                        <div class="stat-value <?= $healthOk ? 'kpi-green' : 'kpi-red' ?>"><?= $healthOk ? 'Online' : 'Issue' ?></div>
                                        <div class="badge <?= $healthOk ? 'badge-success' : 'badge-danger' ?>"><?= $healthOk ? 'OK' : 'FAIL' ?></div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-label">Database</div>
                                        <div class="stat-value kpi-green">Connected</div>
                                        <div class="badge badge-success">OK</div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-label">EDXEIX Session</div>
                                        <div class="stat-value <?= $sessionOk ? 'kpi-green' : 'kpi-amber' ?>"><?= $sessionOk ? 'Loaded' : 'Missing' ?></div>
                                        <div class="badge <?= $sessionOk ? 'badge-success' : 'badge-warning' ?>"><?= $sessionOk ? 'Ready' : 'Manual' ?></div>
                                    </div>
                                    <div class="stat-card">
                                        <div class="stat-label">Bolt Token</div>
                                        <div class="stat-value kpi-amber">Configured</div>
                                        <div class="badge badge-warning">Check live</div>
                                    </div>
                                </div>

                                <div style="height:16px"></div>

                                <div class="toolbar">
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="process_next_job">
                                        <button class="btn btn-default" type="submit">Run next job</button>
                                    </form>

                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="run_bolt_sync">
                                        <button class="btn btn-default" type="submit">Run Bolt sync</button>
                                    </form>
                                </div>

                                <div class="alert alert-warning">
                                    Οι live αναρτήσεις πρέπει να χρησιμοποιούν έναρξη τουλάχιστον <strong>+30 λεπτά</strong> ώστε να υπάρχει χρόνος διαγραφής / διόρθωσης στο EDXEIX.
                                </div>

                                <div class="table-wrap">
                                    <table>
                                        <tbody>
                                        <tr><th>Storage path</th><td class="mono"><?= h($storagePath) ?></td></tr>
                                        <tr><th>Logs path</th><td class="mono"><?= h($logsPath) ?></td></tr>
                                        <tr><th>Artifacts path</th><td class="mono"><?= h($artifactsPath) ?></td></tr>
                                        <tr><th>Session file</th><td class="mono"><?= h($sessionFile) ?></td></tr>
                                        <tr><th>Storage writable</th><td><?= $storageOk ? 'Yes' : 'No' ?></td></tr>
                                        <tr><th>Logs writable</th><td><?= $logsOk ? 'Yes' : 'No' ?></td></tr>
                                        <tr><th>Artifacts writable</th><td><?= $artifactsOk ? 'Yes' : 'No' ?></td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="card" id="session-box" style="margin-top:20px;">
                            <div class="card-header">EDXEIX Session Sync</div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="save_session">

                                    <div class="form-group">
                                        <label for="csrf_token_box">CSRF Token</label>
                                        <input class="form-control" id="csrf_token_box" name="csrf_token" type="text" value="<?= h($sessionCsrfToken) ?>">
                                    </div>

                                    <div class="form-group">
                                        <label for="cookie_header_box">Cookie Header</label>
                                        <textarea class="form-control" id="cookie_header_box" name="cookie_header" placeholder="XSRF-TOKEN=...; mhtroo_forevn_..._session=..."><?= h($sessionCookieHeader) ?></textarea>
                                    </div>

                                    <div class="btn-row">
                                        <button class="btn btn-success" type="submit">Αποθήκευση session</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card" style="margin-top:20px;">
                            <div class="card-header">Latest payload preview</div>
                            <div class="card-body">
                                <div class="code-box" id="payloadPreview"><?= h($payloadPreview !== '' ? $payloadPreview : json_encode([
    'source' => 'manual',
    'driver_external_id' => 'drv-test-001',
    'vehicle_external_id' => 'veh-test-001',
    'starting_point_key' => 'edra_mas',
    'boarding_point' => 'Mykonos Airport',
    'disembark_point' => 'Mykonos Town',
    'price' => '82.00',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></div>
                            </div>
                        </div>

                        <?php if ($lastActionResult !== null): ?>
                            <div class="card" style="margin-top:20px;">
                                <div class="card-header">Last action result</div>
                                <div class="card-body">
                                    <div class="code-box"><?= h(json_encode($lastActionResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row" style="margin-top:4px;">
                    <div class="col-lg-6">
                        <div class="card" id="driver-map-box">
                            <div class="card-header">Αντιστοίχιση οδηγών</div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="save_driver_mapping">

                                    <div class="form-group">
                                        <label for="external_driver_id">External Driver ID</label>
                                        <input class="form-control" id="external_driver_id" name="external_driver_id" type="text" value="drv-test-001">
                                    </div>

                                    <div class="form-group">
                                        <label for="external_driver_name">External Driver Name</label>
                                        <input class="form-control" id="external_driver_name" name="external_driver_name" type="text" value="ΒΙΔΑΚΗΣ ΝΙΚΟΛΑΟΣ">
                                    </div>

                                    <div class="form-group">
                                        <label for="edxeix_driver_id">EDXEIX Driver ID</label>
                                        <input class="form-control" id="edxeix_driver_id" name="edxeix_driver_id" type="text" value="1658">
                                    </div>

                                    <div class="btn-row">
                                        <button class="btn btn-success" type="submit">Αποθήκευση mapping</button>
                                    </div>
                                </form>

                                <?php if ($driverMappings): ?>
                                    <div style="height:16px"></div>
                                    <div class="table-wrap">
                                        <table>
                                            <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>External Driver ID</th>
                                                <th>Name</th>
                                                <th>EDXEIX Driver ID</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach (array_slice($driverMappings, 0, 10) as $row): ?>
                                                <tr>
                                                    <td><?= h((string) $row['id']) ?></td>
                                                    <td class="mono"><?= h((string) $row['external_driver_id']) ?></td>
                                                    <td><?= h((string) $row['external_driver_name']) ?></td>
                                                    <td><?= h((string) $row['edxeix_driver_id']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="card" id="vehicle-map-box">
                            <div class="card-header">Αντιστοίχιση οχημάτων</div>
                            <div class="card-body">
                                <form method="post" action="">
                                    <input type="hidden" name="action" value="save_vehicle_mapping">

                                    <div class="form-group">
                                        <label for="external_vehicle_id">External Vehicle ID</label>
                                        <input class="form-control" id="external_vehicle_id" name="external_vehicle_id" type="text" value="veh-test-001">
                                    </div>

                                    <div class="form-group">
                                        <label for="plate">Plate</label>
                                        <input class="form-control" id="plate" name="plate" type="text" value="ΕΗΑ2545">
                                    </div>

                                    <div class="form-group">
                                        <label for="edxeix_vehicle_id">EDXEIX Vehicle ID</label>
                                        <input class="form-control" id="edxeix_vehicle_id" name="edxeix_vehicle_id" type="text" value="5949">
                                    </div>

                                    <div class="btn-row">
                                        <button class="btn btn-success" type="submit">Αποθήκευση mapping</button>
                                    </div>
                                </form>

                                <?php if ($vehicleMappings): ?>
                                    <div style="height:16px"></div>
                                    <div class="table-wrap">
                                        <table>
                                            <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>External Vehicle ID</th>
                                                <th>Plate</th>
                                                <th>EDXEIX Vehicle ID</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach (array_slice($vehicleMappings, 0, 10) as $row): ?>
                                                <tr>
                                                    <td><?= h((string) $row['id']) ?></td>
                                                    <td class="mono"><?= h((string) $row['external_vehicle_id']) ?></td>
                                                    <td><?= h((string) $row['plate']) ?></td>
                                                    <td><?= h((string) $row['edxeix_vehicle_id']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top:4px;">
                    <div class="col-lg-12">
                        <div class="card" id="jobs-box">
                            <div class="card-header">Jobs / Queue</div>
                            <div class="card-body">
                                <div class="pill-tabs">
                                    <div class="pill-tab active">Pending: <?= h((string) $stats['jobs_pending']) ?></div>
                                    <div class="pill-tab">Submitted: <?= h((string) $stats['jobs_submitted']) ?></div>
                                    <div class="pill-tab">Bookings: <?= h((string) $stats['bookings']) ?></div>
                                    <div class="pill-tab">Attempts: <?= h((string) $stats['attempts']) ?></div>
                                </div>

                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Job ID</th>
                                            <th>Booking ID</th>
                                            <th>Source Trip</th>
                                            <th>Passenger</th>
                                            <th>Driver</th>
                                            <th>Vehicle</th>
                                            <th>Started At</th>
                                            <th>Price</th>
                                            <th>Status</th>
                                            <th>Retry</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!$jobs): ?>
                                            <tr><td colspan="10">Δεν υπάρχουν jobs ακόμα.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($jobs as $job): ?>
                                                <tr>
                                                    <td><?= h((string) $job['job_id']) ?></td>
                                                    <td><?= h((string) $job['booking_id']) ?></td>
                                                    <td class="mono"><?= h((string) $job['source_trip_id']) ?></td>
                                                    <td><?= h((string) $job['customer_name']) ?></td>
                                                    <td><?= h((string) $job['driver_name']) ?></td>
                                                    <td><?= h((string) $job['vehicle_plate']) ?></td>
                                                    <td><?= h((string) $job['started_at']) ?></td>
                                                    <td><?= h((string) $job['price']) ?></td>
                                                    <td><?= h((string) $job['job_status']) ?></td>
                                                    <td><?= h((string) $job['retry_count']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div style="height:16px"></div>

                                <div class="btn-row">
                                    <form method="post" action="">
                                        <input type="hidden" name="action" value="process_next_job">
                                        <button class="btn btn-warning" type="submit">Run next job</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row" style="margin-top:4px;">
                    <div class="col-lg-12">
                        <div class="card" id="attempts-box">
                            <div class="card-header">Attempts / Logs</div>
                            <div class="card-body">
                                <div class="table-wrap">
                                    <table>
                                        <thead>
                                        <tr>
                                            <th>Attempt ID</th>
                                            <th>Job ID</th>
                                            <th>Source Trip</th>
                                            <th>HTTP Status</th>
                                            <th>Success</th>
                                            <th>Remote Reference</th>
                                            <th>Created At</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php if (!$attempts): ?>
                                            <tr><td colspan="7">Δεν υπάρχουν attempts ακόμα.</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($attempts as $attempt): ?>
                                                <tr>
                                                    <td><?= h((string) $attempt['attempt_id']) ?></td>
                                                    <td><?= h((string) $attempt['submission_job_id']) ?></td>
                                                    <td class="mono"><?= h((string) $attempt['source_trip_id']) ?></td>
                                                    <td><?= h((string) $attempt['response_status']) ?></td>
                                                    <td><?= ((int) $attempt['success'] === 1) ? 'Yes' : 'No' ?></td>
                                                    <td class="mono"><?= h((string) $attempt['remote_reference']) ?></td>
                                                    <td><?= h((string) $attempt['created_at']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <footer class="main-footer d-flex align-items-center">
        <div class="main-footer-grid">
            <div>
                <strong>&copy; 2026</strong> gov.cabnet.app
                <br>
                <small class="muted">Operations GUI inspired by the EDXEIX administrative layout</small>
            </div>

            <div>
                <div>EDXEIX bridge diagnostics • Bolt integration • Manual test controls</div>
                <div class="muted">All production submissions must pass the future-start guard.</div>
            </div>
        </div>
    </footer>
</div>

<script>
(function () {
    const startedAt = document.getElementById('started_at');
    const futureRuleMessage = document.getElementById('futureRuleMessage');
    const previewBtn = document.getElementById('previewPayloadBtn');
    const payloadPreview = document.getElementById('payloadPreview');

    function validateFutureRule() {
        if (!startedAt) return true;

        const now = new Date();
        const minStart = new Date(now.getTime() + 30 * 60000);
        const selected = startedAt.value ? new Date(startedAt.value) : null;

        if (!selected || isNaN(selected.getTime())) {
            futureRuleMessage.textContent = 'Η ώρα έναρξης πρέπει να είναι τουλάχιστον 30 λεπτά στο μέλλον.';
            futureRuleMessage.style.color = '#7d5900';
            return false;
        }

        if (selected < minStart) {
            futureRuleMessage.textContent = 'Μη έγκυρη ώρα έναρξης. Απαιτείται τουλάχιστον +30 λεπτά.';
            futureRuleMessage.style.color = '#b42318';
            startedAt.style.borderColor = '#dc3545';
            return false;
        }

        futureRuleMessage.textContent = 'Η ώρα έναρξης είναι έγκυρη για ασφαλές test.';
        futureRuleMessage.style.color = '#166534';
        startedAt.style.borderColor = '#ced4da';
        return true;
    }

    function previewPayload() {
        const driver = document.getElementById('driver_compound');
        const vehicle = document.getElementById('vehicle_compound');

        const payload = {
            source: 'manual',
            lessee_type: document.querySelector('input[name="lessee_type"]:checked')?.value || 'natural',
            lessee_name: document.getElementById('lessee_name')?.value || '',
            driver_compound: driver ? driver.value : '',
            vehicle_compound: vehicle ? vehicle.value : '',
            starting_point_key: document.getElementById('starting_point_key')?.value || '',
            boarding_point: document.getElementById('boarding_point')?.value || '',
            coordinates: document.getElementById('coordinates')?.value || '',
            disembark_point: document.getElementById('disembark_point')?.value || '',
            drafted_at: document.getElementById('drafted_at')?.value || '',
            started_at: document.getElementById('started_at')?.value || '',
            ended_at: document.getElementById('ended_at')?.value || '',
            price: document.getElementById('price')?.value || ''
        };

        if (payloadPreview) {
            payloadPreview.textContent = JSON.stringify(payload, null, 2);
        }
    }

    if (startedAt) {
        startedAt.addEventListener('change', validateFutureRule);
        validateFutureRule();
    }

    if (previewBtn) {
        previewBtn.addEventListener('click', previewPayload);
    }

    const manualForm = document.getElementById('manualBookingForm');
    if (manualForm) {
        manualForm.addEventListener('submit', function (e) {
            if (!validateFutureRule()) {
                e.preventDefault();
                alert('Η ώρα έναρξης πρέπει να είναι τουλάχιστον 30 λεπτά στο μέλλον.');
            }
        });
    }
})();
</script>
</body>
</html>