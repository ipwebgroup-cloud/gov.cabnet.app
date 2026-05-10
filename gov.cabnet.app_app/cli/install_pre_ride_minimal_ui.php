<?php
/**
 * gov.cabnet.app — install minimal pre-ride staff UI assets.
 * v6.6.16-minimal-ui
 *
 * This installer only inserts CSS/JS references into the existing public
 * pre-ride email tool. It makes a timestamped backup first.
 */

declare(strict_types=1);

$target = '/home/cabnet/public_html/gov.cabnet.app/ops/pre-ride-email-tool.php';
$cssHref = '/ops/assets/pre-ride-minimal-ui.css?v=6616';
$jsSrc = '/ops/assets/pre-ride-minimal-ui.js?v=6616';

if (!is_file($target)) {
    fwrite(STDERR, "Target not found: {$target}\n");
    exit(1);
}

$source = file_get_contents($target);
if ($source === false) {
    fwrite(STDERR, "Could not read target: {$target}\n");
    exit(1);
}

$backup = $target . '.bak_minimal_ui_' . date('Ymd_His');
if (!copy($target, $backup)) {
    fwrite(STDERR, "Could not create backup: {$backup}\n");
    exit(1);
}

$changed = false;

if (strpos($source, 'pre-ride-minimal-ui.css') === false) {
    $cssTag = "    <link rel=\"stylesheet\" href=\"{$cssHref}\">\n";
    if (stripos($source, '</head>') !== false) {
        $source = preg_replace('/<\/head>/i', $cssTag . '</head>', $source, 1) ?? $source;
        $changed = true;
    } else {
        $source = $cssTag . $source;
        $changed = true;
    }
}

if (strpos($source, 'pre-ride-minimal-ui.js') === false) {
    $jsTag = "    <script src=\"{$jsSrc}\"></script>\n";
    if (stripos($source, '</body>') !== false) {
        $source = preg_replace('/<\/body>/i', $jsTag . '</body>', $source, 1) ?? $source;
        $changed = true;
    } else {
        $source .= "\n" . $jsTag;
        $changed = true;
    }
}

if ($changed && file_put_contents($target, $source) === false) {
    fwrite(STDERR, "Could not write target: {$target}\nBackup kept at: {$backup}\n");
    exit(1);
}

echo "OK minimal UI installed.\n";
echo "Backup: {$backup}\n";
echo "Target: {$target}\n";
echo "CSS: {$cssHref}\n";
echo "JS: {$jsSrc}\n";
