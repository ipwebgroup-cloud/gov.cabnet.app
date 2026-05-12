<?php
/**
 * gov.cabnet.app — Safe Handoff Package Validator
 *
 * Read-only validator for generated safe handoff ZIP packages.
 * It inspects ZIP entries and selected small text files to catch obvious
 * packaging mistakes before a package is trusted or shared.
 *
 * Safety contract:
 * - Does not call Bolt.
 * - Does not call EDXEIX.
 * - Does not call AADE.
 * - Does not write database rows.
 * - Does not extract the ZIP.
 * - Does not scan or print DATABASE_EXPORT.sql content.
 */

declare(strict_types=1);

namespace Bridge\Support;

use RuntimeException;
use ZipArchive;

final class SafeHandoffPackageValidator
{
    /** @var list<string> */
    private array $requiredEntries = [
        'PACKAGE_MANIFEST.md',
        'gov.cabnet.app_config_examples/README_SANITIZED_CONFIG.txt',
    ];

    /** @return array<string,mixed> */
    public function validate(string $zipPath): array
    {
        $out = [
            'ok' => false,
            'zip_path' => $zipPath,
            'zip_name' => basename($zipPath),
            'size_bytes' => is_file($zipPath) ? (int)filesize($zipPath) : 0,
            'sha256' => is_file($zipPath) ? (string)hash_file('sha256', $zipPath) : '',
            'entry_count' => 0,
            'has_database_export' => false,
            'has_sanitized_config_examples' => false,
            'has_real_config_directory' => false,
            'required_missing' => [],
            'dangerous_entries' => [],
            'warnings' => [],
            'checks' => [],
            'sample_entries' => [],
        ];

        if (!class_exists(ZipArchive::class)) {
            throw new RuntimeException('PHP ZipArchive extension is not available.');
        }
        if (!is_file($zipPath) || !is_readable($zipPath)) {
            throw new RuntimeException('Package file is missing or not readable.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('Unable to open ZIP package.');
        }

        try {
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string)$zip->getNameIndex($i);
                if ($name === '') {
                    continue;
                }
                $entries[] = $name;
            }

            sort($entries, SORT_NATURAL | SORT_FLAG_CASE);
            $entrySet = array_fill_keys($entries, true);
            $out['entry_count'] = count($entries);
            $out['sample_entries'] = array_slice($entries, 0, 80);
            $out['has_database_export'] = isset($entrySet['DATABASE_EXPORT.sql']);

            foreach ($this->requiredEntries as $required) {
                if (!isset($entrySet[$required])) {
                    $out['required_missing'][] = $required;
                }
            }

            foreach ($entries as $entry) {
                $lower = strtolower($entry);
                if (str_starts_with($lower, 'gov.cabnet.app_config_examples/')) {
                    $out['has_sanitized_config_examples'] = true;
                }
                if (str_starts_with($lower, 'gov.cabnet.app_config/')) {
                    $out['has_real_config_directory'] = true;
                    $out['dangerous_entries'][] = $entry;
                    continue;
                }
                if ($this->isDangerousEntry($entry)) {
                    $out['dangerous_entries'][] = $entry;
                }
            }

            $this->checkManifest($zip, $out);
            $this->checkSanitizedConfigExamples($zip, $entries, $out);

            $out['checks'][] = $out['has_database_export']
                ? 'DATABASE_EXPORT.sql is present; treat package as private operational material.'
                : 'DATABASE_EXPORT.sql is not present.';

            if ($out['has_real_config_directory']) {
                $out['warnings'][] = 'Real config directory is present in ZIP. This package should not be used.';
            }
            if (!$out['has_sanitized_config_examples']) {
                $out['warnings'][] = 'Sanitized config examples are missing.';
            }
            if ($out['dangerous_entries']) {
                $out['warnings'][] = 'Dangerous/suspicious entries were found.';
            }
            if ($out['required_missing']) {
                $out['warnings'][] = 'Required package entries are missing.';
            }

            $out['dangerous_entries'] = array_values(array_unique($out['dangerous_entries']));
            $out['warnings'] = array_values(array_unique($out['warnings']));
            $out['ok'] = !$out['required_missing'] && !$out['dangerous_entries'] && $out['has_sanitized_config_examples'];

            return $out;
        } finally {
            $zip->close();
        }
    }

    private function isDangerousEntry(string $entry): bool
    {
        $lower = strtolower(str_replace('\\', '/', $entry));
        $base = strtolower(basename($lower));

        $badSegments = [
            '/.git/', '/cache/', '/tmp/', '/temp/', '/sessions/', '/session/', '/mail/', '/maildir/',
            '/logs/', '/log/', '/backup/', '/backups/', '/access-logs/', '/.cpanel/', '/.trash/',
        ];
        foreach ($badSegments as $segment) {
            if (str_contains('/' . $lower, $segment)) {
                return true;
            }
        }

        if (in_array($base, ['.env', '.env.local', '.env.production', 'error_log', 'debug.log', 'cookies.txt', 'cookie.txt', 'id_rsa', 'id_rsa.pub'], true)) {
            return true;
        }

        if (preg_match('/\.(log|bak|backup|old|tmp|swp|swo)$/i', $base)) {
            return true;
        }

        if (preg_match('/(secret|credential|cookie|session_dump|raw_dump|private_key)/i', $entry)) {
            return true;
        }

        return false;
    }

    /** @param array<string,mixed> $out */
    private function checkManifest(ZipArchive $zip, array &$out): void
    {
        $manifest = $zip->getFromName('PACKAGE_MANIFEST.md');
        if (!is_string($manifest) || trim($manifest) === '') {
            $out['warnings'][] = 'PACKAGE_MANIFEST.md is empty or unreadable.';
            return;
        }

        if (!str_contains($manifest, 'Real server-only config files are not copied')) {
            $out['warnings'][] = 'Manifest does not include expected real-config exclusion notice.';
        }
        if (!str_contains($manifest, 'gov.cabnet.app_config_examples')) {
            $out['warnings'][] = 'Manifest does not reference sanitized config examples.';
        }
        $out['checks'][] = 'PACKAGE_MANIFEST.md is readable.';
    }

    /**
     * @param list<string> $entries
     * @param array<string,mixed> $out
     */
    private function checkSanitizedConfigExamples(ZipArchive $zip, array $entries, array &$out): void
    {
        $checked = 0;
        foreach ($entries as $entry) {
            $lower = strtolower($entry);
            if (!str_starts_with($lower, 'gov.cabnet.app_config_examples/')) {
                continue;
            }
            if (!preg_match('/\.(php|txt|md)$/i', $entry)) {
                continue;
            }
            $content = $zip->getFromName($entry);
            if (!is_string($content)) {
                continue;
            }
            $checked++;
            if (str_ends_with($lower, '.php')) {
                if (!str_contains($content, '__SERVER_ONLY_SECRET__') && !str_contains($content, '__REPLACE_ON_SERVER__') && !str_contains($content, '__RECREATE_SERVER_ONLY_CONFIG_MANUALLY__')) {
                    $out['warnings'][] = 'Sanitized config example may lack placeholders: ' . $entry;
                }
                if (preg_match('/password\s*=>\s*[\'\"][^_][^\'\"]{5,}[\'\"]/i', $content)) {
                    $out['dangerous_entries'][] = $entry . ' (possible unsanitized password value)';
                }
            }
        }
        $out['checks'][] = 'Sanitized config example files checked: ' . $checked;
    }
}
