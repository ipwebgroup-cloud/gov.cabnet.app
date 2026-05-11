<?php
/**
 * gov.cabnet.app — Operator logout.
 */

declare(strict_types=1);

$bootstrap = dirname(__DIR__, 3) . '/gov.cabnet.app_app/src/bootstrap.php';
if (is_file($bootstrap)) {
    try {
        $ctx = require $bootstrap;
        $auth = new Bridge\Auth\OpsAuth($ctx['db']->connection(), [
            'session_name' => (string)$ctx['config']->get('ops_auth.session_name', 'gov_cabnet_ops_session'),
            'login_path' => (string)$ctx['config']->get('ops_auth.login_path', '/ops/login.php'),
        ]);
        $auth->logout();
    } catch (Throwable) {
    }
}

header('Location: /ops/login.php?logged_out=1', true, 302);
exit;
