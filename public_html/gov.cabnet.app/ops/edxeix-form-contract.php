<?php
/**
 * gov.cabnet.app — EDXEIX Form Contract Verifier v3.2
 *
 * Purpose:
 * - Compare local EDXEIX payload preview keys against the authenticated EDXEIX lease form field contract observed by GET-only matrix.
 * - Confirm the app can shape a compatible payload without POSTing or submitting.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not POST.
 * - Reads local config/session metadata and recent normalized bookings only.
 * - Does not write database rows or files.
 * - Does not stage jobs.
 * - Does not update mappings.
 * - Does not print cookies, token values, raw session JSON, or passenger secrets.
 * - Live EDXEIX submission remains disabled.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex,nofollow', true);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

function efc_h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function efc_badge(string $text, string $type = 'neutral'): string { return '<span class="badge badge-' . efc_h($type) . '">' . efc_h($text) . '</span>'; }
function efc_yes(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? efc_badge($yes, 'good') : efc_badge($no, 'bad'); }
function efc_warn(bool $v, string $yes = 'YES', string $no = 'NO'): string { return $v ? efc_badge($yes, 'warn') : efc_badge($no, 'good'); }
function efc_metric($value, string $label): string { return '<div class="metric"><strong>' . efc_h((string)$value) . '</strong><span>' . efc_h($label) . '</span></div>'; }

function efc_value(array $row, array $keys, $default = '') {
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') return $row[$key];
    }
    return $default;
}

function efc_boolish($value): bool {
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','on'], true);
}

function efc_terminal(string $status): bool {
    $s = strtolower(trim($status));
    if ($s === '') return false;
    $terminal = ['finished','completed','client_cancelled','driver_cancelled','driver_cancelled_after_accept','cancelled','canceled','expired','rejected','failed'];
    return in_array($s, $terminal, true) || strpos($s, 'cancel') !== false || strpos($s, 'finished') !== false || strpos($s, 'complete') !== false;
}

function efc_safe_url_display($url): string {
    $url = trim((string)$url);
    if ($url === '') return '';
    $parts = parse_url($url);
    if (!is_array($parts)) return '[configured]';
    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $path = $parts['path'] ?? '';
    if ($host === '') return '[configured]';
    return $scheme . '://' . $host . $path;
}

function efc_find_session_path(array $config): string {
    $edx = is_array($config['edxeix'] ?? null) ? $config['edxeix'] : [];
    foreach (['session_file', 'session_cookie_file', 'cookie_file'] as $key) {
        if (!empty($edx[$key]) && is_string($edx[$key]) && basename((string)$edx[$key]) === 'edxeix_session.json') {
            return (string)$edx[$key];
        }
    }
    $paths = function_exists('gov_bridge_paths') ? gov_bridge_paths() : [];
    if (!empty($paths['runtime'])) {
        return rtrim((string)$paths['runtime'], '/') . '/edxeix_session.json';
    }
    return '/home/cabnet/gov.cabnet.app_app/storage/runtime/edxeix_session.json';
}

function efc_session_metadata(array $config): array {
    $path = efc_find_session_path($config);
    $out = [
        'basename' => basename($path),
        'path_hint' => dirname($path),
        'exists' => is_file($path),
        'readable' => is_readable($path),
        'modified_at' => is_file($path) ? date('Y-m-d H:i:s', (int)@filemtime($path)) : '',
        'age_seconds' => is_file($path) ? max(0, time() - (int)@filemtime($path)) : null,
        'json_valid' => false,
        'csrf_token_present' => false,
        'cookie_like_present' => false,
        'safe_metadata' => [],
        'json_keys' => [],
    ];

    if (!$out['exists'] || !$out['readable']) return $out;
    $raw = @file_get_contents($path);
    $json = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($json)) return $out;

    $out['json_valid'] = true;
    $out['json_keys'] = array_slice(array_keys($json), 0, 50);
    $out['csrf_token_present'] = efc_has_key_like($json, ['csrf_token', 'csrf', 'xsrf', '_token']);
    $out['cookie_like_present'] = efc_has_key_like($json, ['cookie', 'session']);

    foreach (['saved_at','updated_at','source','source_url','detected_form_action','fixed_submit_url_used','extension_version','note'] as $key) {
        if (!array_key_exists($key, $json)) continue;
        if (in_array($key, ['source_url','detected_form_action','fixed_submit_url_used'], true)) {
            $out['safe_metadata'][$key] = efc_safe_url_display((string)$json[$key]);
        } else {
            $out['safe_metadata'][$key] = is_scalar($json[$key]) ? (string)$json[$key] : '[non-scalar metadata]';
        }
    }

    return $out;
}

function efc_has_key_like($value, array $needles): bool {
    if (!is_array($value)) return false;
    foreach ($value as $key => $item) {
        foreach ($needles as $needle) {
            if (stripos((string)$key, $needle) !== false && $item !== null && $item !== '') return true;
        }
        if (is_array($item) && efc_has_key_like($item, $needles)) return true;
    }
    return false;
}

function efc_flatten_keys($value, string $prefix = ''): array {
    $keys = [];
    if (!is_array($value)) return $keys;

    foreach ($value as $key => $item) {
        $key = (string)$key;
        if ($key === '_mapping_status') continue;
        $flat = $prefix === '' ? $key : $prefix . '[' . $key . ']';
        $keys[$flat] = true;
        if (is_array($item)) {
            foreach (efc_flatten_keys($item, $flat) as $nested => $true) {
                $keys[$nested] = true;
            }
        }
    }

    return $keys;
}

function efc_contract(): array {
    return [
        'required' => [
            '_token' => 'session_csrf',
            'broker' => 'payload',
            'lessor' => 'payload',
            'lessee[type]' => 'payload',
            'lessee[name]' => 'payload',
            'driver' => 'payload',
            'vehicle' => 'payload',
            'starting_point_id' => 'payload',
            'boarding_point' => 'payload',
            'disembark_point' => 'payload',
            'drafted_at' => 'payload',
            'started_at' => 'payload',
            'ended_at' => 'payload',
            'price' => 'payload',
        ],
        'optional_or_empty_allowed' => [
            'lessee[vat_number]' => 'payload',
            'lessee[legal_representative]' => 'payload',
            'coordinates' => 'payload',
        ],
        'observed_form_fields' => [
            '_token',
            'broker',
            'lessor',
            'lessee[type]',
            'lessee[name]',
            'lessee[vat_number]',
            'lessee[legal_representative]',
            'coordinates',
            'drafted_at',
            'started_at',
            'ended_at',
            'price',
            'driver',
            'vehicle',
            'starting_point_id',
            'boarding_point',
            'disembark_point',
        ],
        'observed_source' => 'GET-only target matrix confirmed LEASE_FORM_CANDIDATE on /dashboard/lease-agreement/create',
    ];
}

function efc_analyze_booking(mysqli $db, array $booking, array $config, array $session, array $contract): array {
    $preview = function_exists('gov_build_edxeix_preview_payload') ? gov_build_edxeix_preview_payload($db, $booking) : [];
    $flat = efc_flatten_keys($preview);
    $flatKeys = array_keys($flat);

    $requiredStatus = [];
    $missingRequired = [];
    foreach ($contract['required'] as $field => $source) {
        if ($field === '_token') {
            $present = !empty($session['csrf_token_present']);
        } else {
            $present = isset($flat[$field]);
        }
        $requiredStatus[$field] = [
            'source' => $source,
            'present' => $present,
        ];
        if (!$present) $missingRequired[] = $field;
    }

    $optionalStatus = [];
    foreach ($contract['optional_or_empty_allowed'] as $field => $source) {
        $optionalStatus[$field] = [
            'source' => $source,
            'present' => isset($flat[$field]),
        ];
    }

    $mapping = is_array($preview['_mapping_status'] ?? null) ? $preview['_mapping_status'] : [];
    $status = (string)efc_value($booking, ['order_status','status'], '');
    $startedAt = (string)efc_value($booking, ['started_at'], '');
    $orderRef = (string)efc_value($booking, ['order_reference','external_order_id','external_reference','source_trip_reference','source_trip_id'], '');
    $source = strtolower((string)efc_value($booking, ['source_system','source_type','source'], ''));
    $refUpper = strtoupper($orderRef);
    $lab = strpos($source, 'lab') !== false || strpos($refUpper, 'LAB-') === 0;
    $test = efc_boolish($booking['is_test_booking'] ?? false);
    $never = efc_boolish($booking['never_submit_live'] ?? false) || $test;

    $driverMapped = !empty($mapping['driver_mapped']);
    $vehicleMapped = !empty($mapping['vehicle_mapped']);
    $futureGuard = !empty($mapping['passes_future_guard']);
    $terminal = efc_terminal($status);

    $blockers = [];
    if (!$driverMapped) $blockers[] = 'driver_not_mapped';
    if (!$vehicleMapped) $blockers[] = 'vehicle_not_mapped';
    if ($startedAt === '') $blockers[] = 'missing_started_at';
    elseif (!$futureGuard) $blockers[] = 'started_at_not_30_min_future';
    if ($terminal) $blockers[] = 'terminal_order_status';
    if ($lab) $blockers[] = 'lab_row_blocked';
    if ($never) $blockers[] = 'never_submit_live';
    if (!empty($missingRequired)) $blockers[] = 'form_contract_missing_required_fields';

    return [
        'id' => $booking['id'] ?? null,
        'order_reference' => $orderRef !== '' ? substr($orderRef, 0, 16) . '…' : '',
        'status' => $status,
        'started_at' => $startedAt,
        'driver_name' => efc_value($booking, ['driver_name','external_driver_name'], ''),
        'plate' => efc_value($booking, ['vehicle_plate','plate'], ''),
        'payload_built' => !empty($preview),
        'payload_flat_keys' => $flatKeys,
        'required_status' => $requiredStatus,
        'optional_status' => $optionalStatus,
        'missing_required' => $missingRequired,
        'contract_ready' => !empty($preview) && empty($missingRequired),
        'mapping_ready' => $driverMapped && $vehicleMapped,
        'future_guard_passed' => $futureGuard,
        'terminal_status' => $terminal,
        'live_submission_allowed' => empty($blockers),
        'blockers' => $blockers,
    ];
}

function efc_json_response(array $payload): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
}

$format = strtolower(trim((string)($_GET['format'] ?? 'html')));
$limit = max(1, min(30, (int)($_GET['limit'] ?? 10)));

$state = [
    'config_loaded' => false,
    'db_loaded' => false,
    'session' => [],
    'contract' => efc_contract(),
    'rows' => [],
    'error' => null,
];

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) date_default_timezone_set((string)$config['app']['timezone']);
    $state['config_loaded'] = true;
    $state['session'] = efc_session_metadata($config);
    $db = gov_bridge_db();
    $state['db_loaded'] = true;
    $bookings = gov_recent_rows($db, 'normalized_bookings', $limit);
    foreach ($bookings as $booking) {
        $state['rows'][] = efc_analyze_booking($db, $booking, $config, $state['session'], $state['contract']);
    }
} catch (Throwable $e) {
    $state['error'] = $e->getMessage();
}

$rowsTotal = count($state['rows']);
$payloadsBuilt = count(array_filter($state['rows'], static fn($r) => !empty($r['payload_built'])));
$contractReadyRows = count(array_filter($state['rows'], static fn($r) => !empty($r['contract_ready'])));
$eligibleRows = count(array_filter($state['rows'], static fn($r) => !empty($r['live_submission_allowed'])));
$missingAny = [];
foreach ($state['rows'] as $row) {
    foreach (($row['missing_required'] ?? []) as $field) {
        $missingAny[$field] = true;
    }
}

$contractReady = $state['config_loaded'] && $state['db_loaded'] && !empty($state['session']['csrf_token_present']) && $payloadsBuilt > 0 && empty($missingAny);
$liveReady = $contractReady && $eligibleRows > 0;

$decision = 'FORM_CONTRACT_NOT_READY';
$decisionType = 'bad';
$decisionText = 'The local payload contract does not yet match the observed EDXEIX lease form contract.';
if ($liveReady) {
    $decision = 'FORM_CONTRACT_READY_WITH_ELIGIBLE_CANDIDATE';
    $decisionType = 'warn';
    $decisionText = 'The form contract is ready and an eligible candidate appears present. Do not submit without explicit final approval.';
} elseif ($contractReady) {
    $decision = 'FORM_CONTRACT_READY_BUT_NO_ELIGIBLE_CANDIDATE';
    $decisionType = 'good';
    $decisionText = 'The local payload shape matches the observed EDXEIX lease form fields, but no future-safe eligible candidate is available.';
} elseif ($payloadsBuilt > 0) {
    $decision = 'PAYLOAD_BUILDS_BUT_CONTRACT_HAS_GAPS';
    $decisionType = 'warn';
    $decisionText = 'Payload previews build, but one or more observed required form fields are missing.';
}

$payload = [
    'ok' => $state['error'] === null,
    'script' => 'ops/edxeix-form-contract.php',
    'generated_at' => date('c'),
    'safety_contract' => [
        'calls_bolt' => false,
        'calls_edxeix' => false,
        'posts_to_edxeix' => false,
        'reads_database' => true,
        'writes_database' => false,
        'writes_files' => false,
        'stages_jobs' => false,
        'updates_mappings' => false,
        'prints_secrets' => false,
        'raw_session_json_returned' => false,
        'passenger_payload_values_returned' => false,
        'live_edxeix_submission' => 'disabled_not_used',
        'purpose' => 'compare local payload field names to observed EDXEIX form field contract',
    ],
    'decision' => [
        'code' => $decision,
        'type' => $decisionType,
        'text' => $decisionText,
    ],
    'checks' => [
        'config_loaded' => $state['config_loaded'],
        'database_read_ok' => $state['db_loaded'],
        'payload_builder_available' => function_exists('gov_build_edxeix_preview_payload'),
        'session_csrf_token_present' => !empty($state['session']['csrf_token_present']),
        'session_cookie_like_present' => !empty($state['session']['cookie_like_present']),
        'rows_checked' => $rowsTotal,
        'payloads_built' => $payloadsBuilt,
        'contract_ready_rows' => $contractReadyRows,
        'eligible_live_candidates' => $eligibleRows,
        'contract_ready' => $contractReady,
    ],
    'observed_form_contract' => $state['contract'],
    'session_metadata' => [
        'basename' => $state['session']['basename'] ?? '',
        'path_hint' => $state['session']['path_hint'] ?? '',
        'modified_at' => $state['session']['modified_at'] ?? '',
        'csrf_token_present' => !empty($state['session']['csrf_token_present']),
        'cookie_like_present' => !empty($state['session']['cookie_like_present']),
        'safe_metadata' => $state['session']['safe_metadata'] ?? [],
    ],
    'missing_required_fields_across_rows' => array_keys($missingAny),
    'rows' => $state['rows'],
    'error' => $state['error'],
    'links' => [
        'html' => '/ops/edxeix-form-contract.php',
        'json' => '/ops/edxeix-form-contract.php?format=json',
        'target_matrix_json' => '/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json',
        'submit_readiness_json' => '/ops/edxeix-submit-readiness.php?format=json',
        'preflight_review' => '/ops/preflight-review.php',
        'extension_verify' => '/ops/extension-session-write-verification.php',
        'route_index' => '/ops/route-index.php',
    ],
];

if ($format === 'json') efc_json_response($payload);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>EDXEIX Form Contract Verifier | gov.cabnet.app</title>
    <link rel="stylesheet" href="/assets/css/gov-ops-edxeix.css?v=3.2">
</head>
<body>
<div class="gov-topbar">
    <div class="gov-brand">
        <div class="gov-brand-crest">ΕΔ</div>
        <div class="gov-brand-text"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operational console</span></div>
    </div>
    <div class="gov-top-links">
        <a href="/ops/home.php">Αρχική</a>
        <a href="/ops/extension-session-write-verification.php">Extension Verify</a>
        <a href="/ops/edxeix-target-matrix.php">Target Matrix</a>
        <a href="/ops/preflight-review.php">Preflight</a>
        <a class="gov-logout" href="/ops/route-index.php">Route Index</a>
    </div>
</div>

<div class="gov-shell">
    <aside class="gov-sidebar">
        <h3>Form Contract</h3>
        <p>Compare local payload keys to EDXEIX lease form fields</p>
        <div class="gov-side-group">
            <div class="gov-side-group-title">EDXEIX preparation</div>
            <a class="gov-side-link active" href="/ops/edxeix-form-contract.php">Form Contract</a>
            <a class="gov-side-link" href="/ops/extension-session-write-verification.php">Extension Write Verify</a>
            <a class="gov-side-link" href="/ops/edxeix-target-matrix.php">Target Matrix</a>
            <a class="gov-side-link" href="/ops/edxeix-submit-readiness.php">Submit Readiness</a>
            <div class="gov-side-group-title">Safety</div>
            <a class="gov-side-link" href="/ops/preflight-review.php">Preflight Review</a>
            <a class="gov-side-link" href="/ops/route-index.php">Route Index</a>
        </div>
        <div class="gov-side-note">No EDXEIX POST. No EDXEIX GET. Reads local previews only.</div>
    </aside>

    <div class="gov-content">
        <div class="gov-page-header">
            <div>
                <h1 class="gov-page-title">Έλεγχος συμβατότητας φόρμας EDXEIX</h1>
                <div class="gov-breadcrumbs">Αρχική / Διαχειριστικό / Έλεγχος συμβατότητας φόρμας</div>
            </div>
            <div class="gov-tabs">
                <a class="gov-tab active" href="/ops/edxeix-form-contract.php">Καρτέλα</a>
                <a class="gov-tab" href="/ops/edxeix-form-contract.php?format=json">JSON</a>
                <a class="gov-tab" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Matrix JSON</a>
                <a class="gov-tab" href="/ops/edxeix-submit-readiness.php?format=json">Readiness JSON</a>
            </div>
        </div>

        <main class="wrap wrap-shell">
            <section class="safety">
                <strong>READ-ONLY FORM CONTRACT CHECK.</strong>
                This page does not call Bolt, does not call EDXEIX, does not POST, does not write database rows/files, and does not print session secrets or passenger payload values.
            </section>

            <section class="card hero <?= efc_h($decisionType) ?>">
                <h1>EDXEIX Form Contract Verifier</h1>
                <p><?= efc_h($decisionText) ?></p>
                <div>
                    <?= efc_badge($decision, $decisionType) ?>
                    <?= efc_badge('NO EDXEIX POST', 'good') ?>
                    <?= efc_badge('LIVE SUBMIT OFF', 'good') ?>
                    <?= efc_badge('NO SECRET VALUES', 'good') ?>
                </div>
                <div class="grid" style="margin-top:14px">
                    <?= efc_metric($contractReady ? 'yes' : 'no', 'Contract ready') ?>
                    <?= efc_metric($contractReadyRows . '/' . $rowsTotal, 'Rows contract-ready') ?>
                    <?= efc_metric($payloadsBuilt, 'Payload previews built') ?>
                    <?= efc_metric($eligibleRows, 'Eligible live candidates') ?>
                </div>
                <div class="actions">
                    <a class="btn" href="/ops/edxeix-form-contract.php?format=json">Open JSON</a>
                    <a class="btn warn" href="/ops/edxeix-target-matrix.php?probe=1&follow=1&format=json">Run Target Matrix JSON</a>
                    <a class="btn dark" href="/ops/edxeix-submit-readiness.php?format=json">Submit Readiness JSON</a>
                </div>
            </section>

            <section class="two">
                <div class="card">
                    <h2>Contract checks</h2>
                    <div class="kv">
                        <div class="k">Config loaded</div><div><?= efc_yes($state['config_loaded']) ?></div>
                        <div class="k">Database read OK</div><div><?= efc_yes($state['db_loaded']) ?></div>
                        <div class="k">Payload builder</div><div><?= efc_yes(function_exists('gov_build_edxeix_preview_payload')) ?></div>
                        <div class="k">Session CSRF/token</div><div><?= efc_yes(!empty($state['session']['csrf_token_present'])) ?></div>
                        <div class="k">Session cookie-like data</div><div><?= efc_yes(!empty($state['session']['cookie_like_present'])) ?></div>
                        <div class="k">Session modified</div><div><strong><?= efc_h($state['session']['modified_at'] ?? '') ?></strong></div>
                    </div>
                    <?php if (!empty($state['error'])): ?>
                        <p class="badline"><strong><?= efc_h($state['error']) ?></strong></p>
                    <?php endif; ?>
                </div>

                <div class="card">
                    <h2>Observed EDXEIX form source</h2>
                    <p><?= efc_h($state['contract']['observed_source']) ?></p>
                    <p>Confirmed form action target from session metadata:</p>
                    <div class="kv">
                        <?php foreach (($state['session']['safe_metadata'] ?? []) as $key => $value): ?>
                            <div class="k"><?= efc_h($key) ?></div><div><?= efc_h($value) ?></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <section class="card">
                <h2>Observed form field contract</h2>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Field</th><th>Type</th><th>Source expected</th></tr></thead>
                        <tbody>
                            <?php foreach ($state['contract']['required'] as $field => $source): ?>
                                <tr><td><code><?= efc_h($field) ?></code></td><td><?= efc_badge('required', 'warn') ?></td><td><?= efc_h($source) ?></td></tr>
                            <?php endforeach; ?>
                            <?php foreach ($state['contract']['optional_or_empty_allowed'] as $field => $source): ?>
                                <tr><td><code><?= efc_h($field) ?></code></td><td><?= efc_badge('optional/empty allowed', 'neutral') ?></td><td><?= efc_h($source) ?></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h2>Recent payload contract rows</h2>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Status</th>
                                <th>Started</th>
                                <th>Driver</th>
                                <th>Plate</th>
                                <th>Payload</th>
                                <th>Contract</th>
                                <th>Mapping</th>
                                <th>Future</th>
                                <th>Terminal</th>
                                <th>Live safe</th>
                                <th>Missing required</th>
                                <th>Blockers</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($state['rows'] as $row): ?>
                            <tr>
                                <td><?= efc_h((string)($row['id'] ?? '')) ?></td>
                                <td><?= efc_h((string)$row['status']) ?></td>
                                <td><?= efc_h((string)$row['started_at']) ?></td>
                                <td><?= efc_h((string)$row['driver_name']) ?></td>
                                <td><?= efc_h((string)$row['plate']) ?></td>
                                <td><?= !empty($row['payload_built']) ? efc_badge('YES','good') : efc_badge('NO','bad') ?></td>
                                <td><?= !empty($row['contract_ready']) ? efc_badge('YES','good') : efc_badge('NO','bad') ?></td>
                                <td><?= !empty($row['mapping_ready']) ? efc_badge('YES','good') : efc_badge('NO','bad') ?></td>
                                <td><?= !empty($row['future_guard_passed']) ? efc_badge('YES','good') : efc_badge('NO','warn') ?></td>
                                <td><?= !empty($row['terminal_status']) ? efc_badge('YES','warn') : efc_badge('NO','good') ?></td>
                                <td><?= !empty($row['live_submission_allowed']) ? efc_badge('YES','warn') : efc_badge('NO','good') ?></td>
                                <td><?= efc_h(implode(', ', (array)$row['missing_required'])) ?></td>
                                <td><?= efc_h(implode(', ', (array)$row['blockers'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card">
                <h2>Next decision</h2>
                <ol class="timeline">
                    <li>If this page shows <code>FORM_CONTRACT_READY_BUT_NO_ELIGIBLE_CANDIDATE</code>, the submit payload shape is ready.</li>
                    <li>Do not submit existing completed/historical/cancelled rows.</li>
                    <li>Wait for a real future-safe Bolt candidate, then rerun preflight and submit readiness.</li>
                    <li>Only after explicit approval should a guarded final POST handler be considered.</li>
                </ol>
            </section>
        </main>
    </div>
</div>
</body>
</html>
