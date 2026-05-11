<?php
/**
 * gov.cabnet.app — Operator login.
 */

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow', true);

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
if (!is_file($bootstrap)) {
    http_response_code(500);
    echo 'Private app bootstrap not found.';
    exit;
}

try {
    $ctx = require $bootstrap;
    $auth = new Bridge\Auth\OpsAuth($ctx['db']->connection(), [
        'session_name' => (string)$ctx['config']->get('ops_auth.session_name', 'gov_cabnet_ops_session'),
        'login_path' => (string)$ctx['config']->get('ops_auth.login_path', '/ops/login.php'),
        'after_login_path' => (string)$ctx['config']->get('ops_auth.after_login_path', '/ops/pre-ride-email-tool.php'),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Login bootstrap failed.';
    exit;
}

$next = (string)($_GET['next'] ?? $_POST['next'] ?? '');
$error = '';

if ($auth->isLoggedIn()) {
    header('Location: ' . $auth->afterLoginPath($next), true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCsrf((string)($_POST['csrf'] ?? ''))) {
        $error = 'Security token expired. Please try again.';
    } else {
        $result = $auth->login((string)($_POST['login'] ?? ''), (string)($_POST['password'] ?? ''));
        if (!empty($result['ok'])) {
            header('Location: ' . $auth->afterLoginPath($next), true, 302);
            exit;
        }
        $error = (string)($result['error'] ?? 'Login failed.');
    }
}

$csrf = $auth->csrfToken();
$loggedOut = isset($_GET['logged_out']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Operator Login | gov.cabnet.app</title>
    <style>
        :root{--bg:#f3f6fb;--panel:#fff;--ink:#07152f;--muted:#41577a;--line:#d7e1ef;--nav:#081225;--blue:#2563eb;--green:#07875a;--red:#b42318;--gold:#d4922d}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:linear-gradient(180deg,#081225 0%,#17233d 44%,#f3f6fb 44%,#f3f6fb 100%);color:var(--ink);font-family:Arial,Helvetica,sans-serif}.wrap{width:min(460px,calc(100% - 28px));margin:0 auto;padding-top:9vh}.brand{color:#fff;margin-bottom:22px}.brand strong{display:block;font-size:30px}.brand span{color:#dbeafe}.card{background:var(--panel);border:1px solid var(--line);border-radius:16px;padding:24px;box-shadow:0 20px 50px rgba(8,18,37,.22)}h1{font-size:26px;margin:0 0 8px}p{color:var(--muted);line-height:1.45}.field{margin:15px 0}label{display:block;font-weight:700;margin-bottom:7px;color:#27385f}input{width:100%;border:1px solid var(--line);border-radius:10px;padding:13px 12px;font-size:16px}.btn{width:100%;border:0;border-radius:10px;background:var(--blue);color:#fff;font-weight:800;padding:13px 16px;font-size:16px;cursor:pointer}.alert{border-radius:10px;padding:12px;margin:12px 0}.alert.bad{background:#fee2e2;color:#991b1b;border:1px solid #fecaca}.alert.good{background:#dcfce7;color:#166534;border:1px solid #bbf7d0}.small{font-size:13px;color:var(--muted);margin-top:16px}.safety{border-left:5px solid var(--gold);background:#fff7ed;color:#9a3412;padding:12px;border-radius:10px;margin-top:16px;font-size:13px;line-height:1.4}
    </style>
</head>
<body>
<main class="wrap">
    <div class="brand"><strong>gov.cabnet.app</strong><span>Bolt → EDXEIX operator access</span></div>
    <section class="card">
        <h1>Operator login</h1>
        <p>Sign in to access the protected operations tools.</p>
        <?php if ($loggedOut): ?><div class="alert good">You have been logged out.</div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert bad"><?= Bridge\Auth\OpsAuth::h($error) ?></div><?php endif; ?>
        <form method="post" action="/ops/login.php" autocomplete="off">
            <input type="hidden" name="csrf" value="<?= Bridge\Auth\OpsAuth::h($csrf) ?>">
            <input type="hidden" name="next" value="<?= Bridge\Auth\OpsAuth::h($next) ?>">
            <div class="field">
                <label for="login">Username or email</label>
                <input id="login" name="login" type="text" inputmode="email" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" required>
            </div>
            <button class="btn" type="submit">Sign in</button>
        </form>
        <div class="safety">EDXEIX submission remains operator-confirmed. This login only replaces IP restriction; it does not enable automatic live submission.</div>
        <div class="small">No shared credentials should be sent by email or committed to Git.</div>
    </section>
</main>
</body>
</html>
