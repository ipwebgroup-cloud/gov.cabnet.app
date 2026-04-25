<?php
/**
 * gov.cabnet.app — LAB Dry-Run Cleanup Tool
 *
 * SAFETY CONTRACT:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Default mode is preview/read-only.
 * - Delete mode requires POST and an exact confirmation phrase.
 * - Only targets LAB/local/test/never-submit-live normalized bookings and their linked local dry-run queue/attempt rows.
 * - Refuses cleanup if a linked attempt does not clearly indicate dry-run/no-live behavior.
 */

declare(strict_types=1);

require_once '/home/cabnet/gov.cabnet.app_app/lib/bolt_sync_lib.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Robots-Tag: noindex, nofollow', true);

const GOV_LAB_CLEANUP_CONFIRM = 'DELETE LOCAL LAB DRY RUN DATA';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function cleanup_has_col(mysqli $db, string $table, string $column): bool
{
    if (!gov_bridge_table_exists($db, $table)) {
        return false;
    }
    $columns = gov_bridge_table_columns($db, $table);
    return isset($columns[$column]);
}

function cleanup_ref(string $alias, string $column): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return $prefix . gov_bridge_quote_identifier($column);
}

function cleanup_booking_condition(mysqli $db, string $alias = ''): string
{
    if (!gov_bridge_table_exists($db, 'normalized_bookings')) {
        return '1=0';
    }

    $columns = gov_bridge_table_columns($db, 'normalized_bookings');
    $conditions = [];

    foreach (['source_system', 'source_type', 'source'] as $column) {
        if (isset($columns[$column])) {
            $ref = cleanup_ref($alias, $column);
            $conditions[] = "LOWER($ref) LIKE '%lab%'";
            $conditions[] = "LOWER($ref) LIKE '%local_test%'";
        }
    }

    foreach (['order_reference', 'external_order_id', 'external_reference', 'source_trip_id', 'source_booking_id', 'source_trip_reference'] as $column) {
        if (isset($columns[$column])) {
            $conditions[] = cleanup_ref($alias, $column) . " LIKE 'LAB-%'";
        }
    }

    if (isset($columns['is_test_booking'])) {
        $conditions[] = cleanup_ref($alias, 'is_test_booking') . ' = 1';
    }
    if (isset($columns['never_submit_live'])) {
        $conditions[] = cleanup_ref($alias, 'never_submit_live') . ' = 1';
    }
    if (isset($columns['test_booking_created_by'])) {
        $conditions[] = '(' . cleanup_ref($alias, 'test_booking_created_by') . ' IS NOT NULL AND ' . cleanup_ref($alias, 'test_booking_created_by') . " <> '')";
    }

    if (!$conditions) {
        return '1=0';
    }

    return '(' . implode(' OR ', $conditions) . ')';
}

function cleanup_attempt_dry_run_condition(mysqli $db, string $alias = 'a'): string
{
    if (!gov_bridge_table_exists($db, 'submission_attempts')) {
        return '1=0';
    }

    $columns = gov_bridge_table_columns($db, 'submission_attempts');
    $numericSafe = [];
    if (isset($columns['response_status'])) {
        $numericSafe[] = '(' . cleanup_ref($alias, 'response_status') . ' IS NULL OR ' . cleanup_ref($alias, 'response_status') . ' = 0)';
    }
    if (isset($columns['http_status'])) {
        $numericSafe[] = '(' . cleanup_ref($alias, 'http_status') . ' IS NULL OR ' . cleanup_ref($alias, 'http_status') . ' = 0)';
    }
    if (isset($columns['success'])) {
        $numericSafe[] = '(' . cleanup_ref($alias, 'success') . ' IS NULL OR ' . cleanup_ref($alias, 'success') . ' = 0)';
    }
    if (isset($columns['is_dry_run'])) {
        $numericSafe[] = cleanup_ref($alias, 'is_dry_run') . ' = 1';
    }

    $textSafe = [];
    foreach (['response_body', 'response_payload_json', 'response_json', 'notes', 'error_message', 'status', 'state', 'attempt_status', 'mode'] as $column) {
        if (!isset($columns[$column])) {
            continue;
        }
        $ref = cleanup_ref($alias, $column);
        $textSafe[] = "$ref LIKE '%DRY RUN%'";
        $textSafe[] = "$ref LIKE '%dry_run%'";
        $textSafe[] = "$ref LIKE '%No EDXEIX HTTP request%'";
        $textSafe[] = "$ref LIKE '%would_submit_to_edxeix%false%'";
        $textSafe[] = "$ref LIKE '%live_submission_allowed%false%'";
        $textSafe[] = "$ref IN ('blocked_by_preflight','dry_run_validated','staged_dry_run')";
    }

    if (!$numericSafe && !$textSafe) {
        return '1=0';
    }

    $parts = [];
    if ($numericSafe) {
        $parts[] = '(' . implode(' OR ', $numericSafe) . ')';
    }
    if ($textSafe) {
        $parts[] = '(' . implode(' OR ', $textSafe) . ')';
    }

    // Require at least one numeric/no-success hint and one dry-run/no-live text hint when both are available.
    // This avoids deleting ambiguous historical attempts.
    return '(' . implode(' AND ', $parts) . ')';
}

function cleanup_count(mysqli $db, string $sql): int
{
    $row = gov_bridge_fetch_one($db, $sql);
    return (int)($row['c'] ?? 0);
}

function cleanup_delete(mysqli $db, string $sql): int
{
    $stmt = $db->prepare($sql);
    $stmt->execute();
    return max(0, (int)$stmt->affected_rows);
}

function cleanup_fetch_bookings(mysqli $db, int $limit = 50): array
{
    if (!gov_bridge_table_exists($db, 'normalized_bookings')) {
        return [];
    }
    $condition = cleanup_booking_condition($db, 'b');
    return gov_bridge_fetch_all(
        $db,
        'SELECT b.* FROM normalized_bookings b WHERE ' . $condition . ' ORDER BY b.id DESC LIMIT ' . (int)$limit
    );
}

function cleanup_state(mysqli $db): array
{
    $bookingCondition = cleanup_booking_condition($db, 'b');
    $attemptDry = cleanup_attempt_dry_run_condition($db, 'a');

    $state = [
        'lab_bookings' => 0,
        'linked_jobs' => 0,
        'linked_attempts' => 0,
        'dry_run_linked_attempts' => 0,
        'unsafe_linked_attempts' => 0,
        'bookings_preview' => [],
        'tables' => [
            'normalized_bookings' => gov_bridge_table_exists($db, 'normalized_bookings'),
            'submission_jobs' => gov_bridge_table_exists($db, 'submission_jobs'),
            'submission_attempts' => gov_bridge_table_exists($db, 'submission_attempts'),
        ],
    ];

    if ($state['tables']['normalized_bookings']) {
        $state['lab_bookings'] = cleanup_count($db, 'SELECT COUNT(*) AS c FROM normalized_bookings b WHERE ' . $bookingCondition);
        $state['bookings_preview'] = cleanup_fetch_bookings($db, 50);
    }

    if ($state['tables']['submission_jobs'] && $state['tables']['normalized_bookings']) {
        $state['linked_jobs'] = cleanup_count(
            $db,
            'SELECT COUNT(DISTINCT j.id) AS c FROM submission_jobs j JOIN normalized_bookings b ON b.id = j.normalized_booking_id WHERE ' . $bookingCondition
        );
    }

    if ($state['tables']['submission_attempts'] && $state['tables']['submission_jobs'] && $state['tables']['normalized_bookings']) {
        $join = ' FROM submission_attempts a JOIN submission_jobs j ON j.id = a.submission_job_id JOIN normalized_bookings b ON b.id = j.normalized_booking_id WHERE ' . $bookingCondition;
        $state['linked_attempts'] = cleanup_count($db, 'SELECT COUNT(DISTINCT a.id) AS c' . $join);
        $state['dry_run_linked_attempts'] = cleanup_count($db, 'SELECT COUNT(DISTINCT a.id) AS c' . $join . ' AND ' . $attemptDry);
        $state['unsafe_linked_attempts'] = cleanup_count($db, 'SELECT COUNT(DISTINCT a.id) AS c' . $join . ' AND NOT (' . $attemptDry . ')');
    }

    return $state;
}

function cleanup_execute(mysqli $db): array
{
    $before = cleanup_state($db);
    if ($before['unsafe_linked_attempts'] > 0) {
        return [
            'ok' => false,
            'deleted_attempts' => 0,
            'deleted_jobs' => 0,
            'deleted_bookings' => 0,
            'error' => 'Cleanup blocked because one or more linked attempt rows are not clearly marked as dry-run/no-live.',
            'before' => $before,
            'after' => $before,
        ];
    }

    $bookingCondition = cleanup_booking_condition($db, 'b');
    $attemptDry = cleanup_attempt_dry_run_condition($db, 'a');

    $deletedAttempts = 0;
    $deletedJobs = 0;
    $deletedBookings = 0;

    $db->begin_transaction();
    try {
        if (gov_bridge_table_exists($db, 'submission_attempts') && gov_bridge_table_exists($db, 'submission_jobs') && gov_bridge_table_exists($db, 'normalized_bookings')) {
            $deletedAttempts = cleanup_delete(
                $db,
                'DELETE a FROM submission_attempts a JOIN submission_jobs j ON j.id = a.submission_job_id JOIN normalized_bookings b ON b.id = j.normalized_booking_id WHERE ' . $bookingCondition . ' AND ' . $attemptDry
            );
        }

        if (gov_bridge_table_exists($db, 'submission_jobs') && gov_bridge_table_exists($db, 'normalized_bookings')) {
            $deletedJobs = cleanup_delete(
                $db,
                'DELETE j FROM submission_jobs j JOIN normalized_bookings b ON b.id = j.normalized_booking_id WHERE ' . $bookingCondition
            );
        }

        if (gov_bridge_table_exists($db, 'normalized_bookings')) {
            $deletedBookings = cleanup_delete(
                $db,
                'DELETE b FROM normalized_bookings b WHERE ' . $bookingCondition
            );
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        return [
            'ok' => false,
            'deleted_attempts' => $deletedAttempts,
            'deleted_jobs' => $deletedJobs,
            'deleted_bookings' => $deletedBookings,
            'error' => $e->getMessage(),
            'before' => $before,
            'after' => cleanup_state($db),
        ];
    }

    return [
        'ok' => true,
        'deleted_attempts' => $deletedAttempts,
        'deleted_jobs' => $deletedJobs,
        'deleted_bookings' => $deletedBookings,
        'error' => null,
        'before' => $before,
        'after' => cleanup_state($db),
    ];
}

$result = null;
$error = null;
$state = null;

try {
    $config = gov_bridge_load_config();
    if (!empty($config['app']['timezone'])) {
        date_default_timezone_set((string)$config['app']['timezone']);
    }

    $db = gov_bridge_db();
    $state = cleanup_state($db);

    $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $confirm = trim((string)($_POST['confirm'] ?? ''));
    if ($isPost) {
        if ($confirm !== GOV_LAB_CLEANUP_CONFIRM) {
            $error = 'Confirmation phrase did not match. Nothing was deleted.';
        } else {
            $result = cleanup_execute($db);
            $state = cleanup_state($db);
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

function row_value(array $row, array $keys, $default = '')
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>LAB Dry-Run Cleanup | gov.cabnet.app</title>
    <style>
        :root { --bg:#f3f6fb; --card:#fff; --ink:#07152f; --muted:#41577a; --line:#d7e1ef; --nav:#081225; --blue:#2563eb; --green:#07875a; --orange:#b85c00; --red:#b42318; --slate:#334155; }
        * { box-sizing:border-box; }
        body { margin:0; background:var(--bg); color:var(--ink); font-family:Arial, Helvetica, sans-serif; }
        .nav { background:var(--nav); color:#fff; min-height:56px; display:flex; align-items:center; gap:18px; padding:0 26px; position:sticky; top:0; z-index:5; overflow:auto; }
        .nav strong, .nav a { white-space:nowrap; }
        .nav a { color:#fff; text-decoration:none; font-size:15px; opacity:.92; }
        .nav a:hover { opacity:1; text-decoration:underline; }
        .wrap { width:min(1440px, calc(100% - 48px)); margin:26px auto 60px; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:18px; margin-bottom:18px; box-shadow:0 10px 26px rgba(8,18,37,.04); }
        h1 { font-size:34px; margin:0 0 12px; }
        h2 { font-size:23px; margin:0 0 14px; }
        p { color:var(--muted); line-height:1.45; }
        .hero { border-left:7px solid var(--orange); }
        .hero.clean { border-left-color:var(--green); }
        .hero.blocked { border-left-color:var(--red); }
        .grid { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; margin-top:14px; }
        .metric { border:1px solid var(--line); border-radius:10px; padding:14px; background:#f8fbff; min-height:80px; }
        .metric strong { display:block; font-size:30px; line-height:1.05; word-break:break-word; }
        .metric span { color:var(--muted); font-size:14px; }
        .badge { display:inline-block; padding:5px 9px; border-radius:999px; font-size:12px; font-weight:700; margin:1px 3px 1px 0; }
        .badge-good { background:#dcfce7; color:#166534; } .badge-warn { background:#fff7ed; color:#b45309; } .badge-bad { background:#fee2e2; color:#991b1b; } .badge-neutral { background:#eaf1ff; color:#1e40af; }
        .actions { display:flex; flex-wrap:wrap; gap:10px; margin-top:14px; }
        .btn { display:inline-block; padding:11px 15px; border-radius:8px; color:#fff; text-decoration:none; font-weight:700; border:0; cursor:pointer; font-size:15px; background:var(--blue); }
        .btn.green { background:var(--green); } .btn.orange { background:var(--orange); } .btn.red { background:var(--red); } .btn.dark { background:var(--slate); }
        .alert { padding:12px 14px; border-radius:10px; margin:12px 0; border-left:5px solid var(--orange); background:#fff7ed; color:#7c2d12; }
        .alert.good { border-left-color:var(--green); background:#ecfdf3; color:#166534; }
        .alert.bad { border-left-color:var(--red); background:#fee2e2; color:#991b1b; }
        .table-wrap { overflow:auto; border:1px solid var(--line); border-radius:10px; }
        table { width:100%; border-collapse:collapse; min-width:900px; }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); vertical-align:top; font-size:14px; }
        th { background:#f8fafc; font-size:12px; text-transform:uppercase; letter-spacing:.02em; }
        code { background:#eef2ff; padding:2px 5px; border-radius:5px; }
        input[type=text] { width:100%; max-width:560px; padding:12px 13px; border:1px solid var(--line); border-radius:8px; font-size:15px; }
        .small { font-size:13px; color:var(--muted); }
        @media (max-width:1100px) { .grid { grid-template-columns:repeat(2, minmax(0,1fr)); } }
        @media (max-width:720px) { .grid { grid-template-columns:1fr; } .wrap { width:calc(100% - 24px); margin-top:14px; } .nav { padding:0 14px; } }
    </style>
</head>
<body>
<nav class="nav">
    <strong>GC gov.cabnet.app</strong>
    <a href="/ops/index.php">Operations Console</a>
    <a href="/ops/bolt-live.php">Bolt Live</a>
    <a href="/ops/jobs.php">Jobs Queue</a>
    <a href="/ops/readiness.php">Readiness</a>
    <a href="/ops/test-booking.php">Local Test Booking</a>
    <a href="/ops/cleanup-lab.php">Cleanup LAB</a>
    <a href="/bolt_readiness_audit.php">Readiness JSON</a>
</nav>
<main class="wrap">
    <?php $unsafe = (int)($state['unsafe_linked_attempts'] ?? 0); $hasRows = ((int)($state['lab_bookings'] ?? 0) + (int)($state['linked_jobs'] ?? 0) + (int)($state['linked_attempts'] ?? 0)) > 0; ?>
    <section class="card hero <?= !$hasRows ? 'clean' : ($unsafe > 0 ? 'blocked' : '') ?>">
        <h1>LAB Dry-Run Cleanup</h1>
        <p>This tool removes local LAB/test dry-run data after validation. It does not call Bolt, does not call EDXEIX, and does not touch normal Bolt rows.</p>
        <p><strong>Safety:</strong> <span class="badge badge-good">No Bolt call</span> <span class="badge badge-good">No EDXEIX call</span> <span class="badge badge-warn">Preview by default</span> <span class="badge badge-bad">Exact phrase required to delete</span></p>
        <?php if ($error): ?><div class="alert bad"><strong>Error:</strong> <?= h($error) ?></div><?php endif; ?>
        <?php if ($result): ?>
            <?php if (!empty($result['ok'])): ?>
                <div class="alert good"><strong>Cleanup complete.</strong> Deleted <?= (int)$result['deleted_attempts'] ?> attempts, <?= (int)$result['deleted_jobs'] ?> jobs, and <?= (int)$result['deleted_bookings'] ?> LAB/test bookings.</div>
            <?php else: ?>
                <div class="alert bad"><strong>Cleanup blocked.</strong> <?= h($result['error'] ?? 'Unknown cleanup error') ?></div>
            <?php endif; ?>
        <?php endif; ?>
        <div class="grid">
            <div class="metric"><strong><?= h($state['lab_bookings'] ?? 0) ?></strong><span>LAB/test normalized bookings</span></div>
            <div class="metric"><strong><?= h($state['linked_jobs'] ?? 0) ?></strong><span>Linked local jobs</span></div>
            <div class="metric"><strong><?= h($state['linked_attempts'] ?? 0) ?></strong><span>Linked attempts</span></div>
            <div class="metric"><strong><?= h($state['unsafe_linked_attempts'] ?? 0) ?></strong><span>Unsafe/unclassified attempts</span></div>
        </div>
        <div class="actions">
            <a class="btn" href="/ops/cleanup-lab.php">Refresh Preview</a>
            <a class="btn dark" href="/ops/readiness.php">Open Readiness</a>
            <a class="btn orange" href="/ops/jobs.php">Open Jobs Queue</a>
        </div>
    </section>

    <section class="card">
        <h2>Cleanup Action</h2>
        <?php if (!$hasRows): ?>
            <div class="alert good">No LAB/test dry-run records were found. Nothing needs cleanup.</div>
        <?php elseif ($unsafe > 0): ?>
            <div class="alert bad">Cleanup is blocked because at least one linked attempt row is not clearly marked as dry-run/no-live. Mark or inspect the row before deleting anything.</div>
        <?php else: ?>
            <p>To delete only the LAB/test dry-run data shown below, type this exact phrase:</p>
            <p><code><?= h(GOV_LAB_CLEANUP_CONFIRM) ?></code></p>
            <form method="post" action="/ops/cleanup-lab.php" autocomplete="off">
                <input type="text" name="confirm" placeholder="<?= h(GOV_LAB_CLEANUP_CONFIRM) ?>" aria-label="Confirmation phrase">
                <div class="actions">
                    <button class="btn red" type="submit">Delete LAB Dry-Run Data</button>
                </div>
                <p class="small">Delete order: linked dry-run attempts → linked local jobs → LAB/test normalized bookings.</p>
            </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Rows That Would Be Deleted</h2>
        <?php if (empty($state['bookings_preview'])): ?>
            <p>No LAB/test normalized bookings found.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Source</th><th>Order Ref</th><th>Status</th><th>Started</th><th>Driver</th><th>Vehicle</th><th>Test Flags</th></tr></thead>
                    <tbody>
                    <?php foreach ($state['bookings_preview'] as $row): ?>
                        <tr>
                            <td><?= h($row['id'] ?? '') ?></td>
                            <td><?= h(row_value($row, ['source_system', 'source_type', 'source'], '')) ?></td>
                            <td><?= h(row_value($row, ['order_reference', 'external_order_id', 'source_trip_id', 'source_trip_reference'], '')) ?></td>
                            <td><?= h(row_value($row, ['status', 'order_status'], '')) ?></td>
                            <td><?= h(row_value($row, ['started_at'], '')) ?></td>
                            <td><?= h(row_value($row, ['driver_name', 'driver_external_id'], '')) ?></td>
                            <td><?= h(row_value($row, ['vehicle_plate', 'plate', 'vehicle_external_id'], '')) ?></td>
                            <td>
                                <?php if ((string)($row['is_test_booking'] ?? '0') === '1'): ?><span class="badge badge-warn">is_test_booking</span><?php endif; ?>
                                <?php if ((string)($row['never_submit_live'] ?? '0') === '1'): ?><span class="badge badge-bad">never_submit_live</span><?php endif; ?>
                                <?php if (!empty($row['test_booking_created_by'])): ?><span class="badge badge-neutral"><?= h($row['test_booking_created_by']) ?></span><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
