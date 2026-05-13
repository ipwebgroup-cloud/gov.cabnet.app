<?php
declare(strict_types=1);

/**
 * Read-only V3 Fast Pipeline Pulse dashboard.
 * No DB writes. No EDXEIX calls. No AADE calls.
 */

$version = 'v3.0.37-fast-pipeline-pulse-dashboard';

$appRoot = realpath(__DIR__ . '/../../../gov.cabnet.app_app');
if ($appRoot === false) {
    $appRoot = '/home/cabnet/gov.cabnet.app_app';
}

$logFile = $appRoot . '/logs/pre_ride_email_v3_fast_pipeline_pulse.log';
$pipelineLogFile = $appRoot . '/logs/pre_ride_email_v3_fast_pipeline.log';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function read_tail_lines(string $file, int $maxLines = 160): array
{
    if (!is_file($file) || !is_readable($file)) {
        return [];
    }
    $lines = file($file, FILE_IGNORE_NEW_LINES);
    if (!is_array($lines)) {
        return [];
    }
    return array_slice($lines, -$maxLines);
}

function log_age_info(string $file): array
{
    if (!is_file($file)) {
        return ['exists' => false, 'age' => null, 'mtime' => null, 'fresh' => false];
    }
    $mtime = filemtime($file);
    $age = $mtime ? time() - $mtime : null;
    return [
        'exists' => true,
        'age' => $age,
        'mtime' => $mtime ? date('Y-m-d H:i:s T', $mtime) : null,
        'fresh' => ($age !== null && $age <= 180),
    ];
}

$pulseInfo = log_age_info($logFile);
$pipelineInfo = log_age_info($pipelineLogFile);
$pulseTail = read_tail_lines($logFile);
$pipelineTail = read_tail_lines($pipelineLogFile, 80);

$latestPulseSummary = '';
foreach (array_reverse($pulseTail) as $line) {
    if (strpos($line, 'Pulse summary:') !== false || strpos($line, 'V3 fast pipeline pulse cron finish') !== false) {
        $latestPulseSummary = $line;
        break;
    }
}

$latestPipelineSummary = '';
foreach (array_reverse($pipelineTail) as $line) {
    if (strpos($line, 'Steps:') !== false || strpos($line, 'Safety:') !== false) {
        $latestPipelineSummary = $line;
        break;
    }
}

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>V3 Fast Pipeline Pulse</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="refresh" content="20">
  <style>
    body{margin:0;background:#f4f7fb;color:#001a44;font-family:Arial,Helvetica,sans-serif}
    .top{background:#071225;color:#fff;padding:14px 28px;font-weight:700}
    .top a{color:#fff;text-decoration:none;margin-right:18px}
    .wrap{max-width:1180px;margin:18px auto;padding:0 18px}
    .card{background:#fff;border:1px solid #d6e2f2;border-radius:14px;padding:18px;margin:14px 0;box-shadow:0 8px 20px rgba(9,24,54,.04)}
    .pill{display:inline-block;border-radius:999px;padding:7px 11px;font-size:12px;font-weight:700;margin:4px 6px 4px 0}
    .ok{background:#d8f8df;color:#006b2d}.warn{background:#fff3cd;color:#8a5a00}.bad{background:#ffe1e1;color:#a30000}.blue{background:#e8f0ff;color:#0638a8}
    .grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
    .metric{background:#f8fbff;border:1px solid #d6e2f2;border-radius:12px;padding:14px}
    .metric b{display:block;font-size:28px;margin-bottom:4px}
    pre{background:#071225;color:#eaf2ff;border-radius:10px;padding:14px;overflow:auto;white-space:pre-wrap;font-size:12px;line-height:1.35}
    @media(max-width:800px){.grid{grid-template-columns:1fr}.top a{display:inline-block;margin:4px 10px 4px 0}}
  </style>
</head>
<body>
<div class="top">
  <a href="/ops/pre-ride-email-v3-queue-watch.php">V3 Watch</a>
  <a href="/ops/pre-ride-email-v3-fast-pipeline.php">V3 Fast Pipeline</a>
  <a href="/ops/pre-ride-email-v3-fast-pipeline-pulse.php">V3 Pulse</a>
  <a href="/ops/pre-ride-email-v3-automation-readiness.php">V3 Readiness</a>
</div>
<div class="wrap">
  <div class="card">
    <h1>V3 Fast Pipeline Pulse</h1>
    <p>Read-only visibility for the rapid V3 pipeline pulse. The pulse cron runs the existing V3 fast pipeline several times inside one cron minute to reduce waiting time after a Bolt pre-ride email arrives.</p>
    <span class="pill blue">V3 ISOLATED</span>
    <span class="pill ok">READ ONLY</span>
    <span class="pill ok">NO EDXEIX CALL</span>
    <span class="pill ok">NO AADE CALL</span>
    <span class="pill blue"><?=h($version)?></span>
  </div>

  <div class="card">
    <h2>Status</h2>
    <div class="grid">
      <div class="metric"><b><?= $pulseInfo['exists'] ? 'yes' : 'no' ?></b>Pulse log exists</div>
      <div class="metric"><b><?= $pulseInfo['fresh'] ? 'yes' : 'no' ?></b>Pulse fresh &lt;= 180 sec</div>
      <div class="metric"><b><?= $pulseInfo['age'] === null ? '-' : (int)$pulseInfo['age'] ?></b>Pulse age seconds</div>
      <div class="metric"><b><?= $pipelineInfo['fresh'] ? 'yes' : 'no' ?></b>Fast pipeline fresh</div>
    </div>
    <p><b>Pulse log:</b> <?=h(str_replace('/home/cabnet/', '', $logFile))?></p>
    <p><b>Latest pulse summary:</b> <?=h($latestPulseSummary !== '' ? $latestPulseSummary : '-')?></p>
    <p><b>Latest pipeline summary:</b> <?=h($latestPipelineSummary !== '' ? $latestPipelineSummary : '-')?></p>
  </div>

  <div class="card">
    <h2>Suggested cron command</h2>
    <pre>* * * * * /usr/local/bin/php /home/cabnet/gov.cabnet.app_app/cli/pre_ride_email_v3_fast_pipeline_pulse_cron_worker.php &gt;&gt; /home/cabnet/gov.cabnet.app_app/logs/pre_ride_email_v3_fast_pipeline_pulse.log 2&gt;&amp;1</pre>
  </div>

  <div class="card">
    <h2>Pulse log tail</h2>
    <pre><?=h(implode("\n", $pulseTail) ?: 'No pulse log lines yet.')?></pre>
  </div>
</div>
</body>
</html>
