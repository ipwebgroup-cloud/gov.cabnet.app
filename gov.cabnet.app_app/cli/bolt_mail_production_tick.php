<?php
/**
 * gov.cabnet.app — production tick for Bolt mail flow.
 *
 * Runs mail import and immediate mail processing in one process order.
 * Does not itself call EDXEIX live submit.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Athens');

$php = '/usr/local/bin/php';

$commands = [
    $php . ' /home/cabnet/gov.cabnet.app_app/cli/import_bolt_mail.php --limit=250 --days=2',
    $php . ' /home/cabnet/gov.cabnet.app_app/cli/auto_bolt_mail_dry_run.php --limit=50 --json',
];

echo '[' . date(DATE_ATOM) . "] Bolt mail production tick start\n";

foreach ($commands as $cmd) {
    echo "RUN {$cmd}\n";
    passthru($cmd, $code);
    echo "EXIT {$code}\n";
}

echo '[' . date(DATE_ATOM) . "] Bolt mail production tick end\n";
