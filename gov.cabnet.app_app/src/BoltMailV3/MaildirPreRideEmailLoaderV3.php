<?php
/**
 * gov.cabnet.app — Maildir pre-ride email loader v3 isolated.
 *
 * Safety:
 * - Read-only filesystem access.
 * - No email deletion/move/mark-read.
 * - No DB write.
 * - No network calls.
 */

declare(strict_types=1);

namespace Bridge\BoltMailV3;

final class MaildirPreRideEmailLoaderV3
{
    public const VERSION = 'v3.0.2-isolated-candidate-scanner';
    private const MAX_FILE_BYTES = 250000;
    private const MAX_FILES_PER_DIR = 80;

    /**
     * @param array<int,string> $extraDirs
     * @return array{ok:bool,email_text:string,source:string,source_mtime:string,error:string,checked_dirs:array<int,string>,loader_version:string}
     */
    public function loadLatest(array $extraDirs = []): array
    {
        $list = $this->loadCandidates($extraDirs, 1);
        $candidate = $list['candidates'][0] ?? null;
        if (is_array($candidate)) {
            return [
                'ok' => true,
                'email_text' => (string)($candidate['email_text'] ?? ''),
                'source' => (string)($candidate['source'] ?? ''),
                'source_mtime' => (string)($candidate['source_mtime'] ?? ''),
                'error' => '',
                'checked_dirs' => $list['checked_dirs'],
                'loader_version' => self::VERSION,
            ];
        }

        return [
            'ok' => false,
            'email_text' => '',
            'source' => '',
            'source_mtime' => '',
            'error' => $list['error'],
            'checked_dirs' => $list['checked_dirs'],
            'loader_version' => self::VERSION,
        ];
    }

    /**
     * Return recent matching pre-ride emails, newest first, without moving, deleting,
     * marking-read, storing, or logging any message. Public paths are sanitized to
     * safe Maildir tail names only; raw server paths are never returned.
     *
     * @param array<int,string> $extraDirs
     * @return array{ok:bool,candidates:array<int,array<string,mixed>>,error:string,checked_dirs:array<int,string>,loader_version:string}
     */
    public function loadCandidates(array $extraDirs = [], int $limit = 10): array
    {
        $limit = max(1, min(25, $limit));
        $dirs = $this->candidateDirs($extraDirs);
        $files = $this->candidateFiles($dirs);
        $candidates = [];

        foreach ($files as $file) {
            $raw = $this->readLimited($file);
            if ($raw === '') {
                continue;
            }
            $body = $this->decodeEmailBody($raw);
            if (!$this->looksLikeBoltPreRide($body)) {
                continue;
            }
            $mtime = filemtime($file) ?: time();
            $candidates[] = [
                'email_text' => $body,
                'source' => $this->safeSourceName($file),
                'source_mtime' => date('Y-m-d H:i:s', $mtime),
                'source_mtime_epoch' => $mtime,
                'loader_version' => self::VERSION,
            ];
            if (count($candidates) >= $limit) {
                break;
            }
        }

        return [
            'ok' => count($candidates) > 0,
            'candidates' => $candidates,
            'error' => count($candidates) > 0 ? '' : 'No matching Bolt pre-ride email was found in the configured/default Maildir paths.',
            'checked_dirs' => $this->safeDirs($dirs),
            'loader_version' => self::VERSION,
        ];
    }

    /** @param array<int,string> $dirs @return array<int,string> */
    private function candidateFiles(array $dirs): array
    {
        $files = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir) || !is_readable($dir)) {
                continue;
            }
            $found = glob(rtrim($dir, '/') . '/*') ?: [];
            usort($found, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
            foreach (array_slice($found, 0, self::MAX_FILES_PER_DIR) as $file) {
                if (is_file($file) && is_readable($file)) {
                    $files[] = $file;
                }
            }
        }

        usort($files, static fn(string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0));
        return array_values(array_unique($files));
    }

    /** @param array<int,string> $extraDirs @return array<int,string> */
    private function candidateDirs(array $extraDirs): array
    {
        $dirs = [];
        foreach ($extraDirs as $dir) {
            if (is_string($dir) && trim($dir) !== '') {
                $dirs[] = trim($dir);
            }
        }

        $env = getenv('GOV_CABNET_PRERIDE_MAILDIR_V3');
        if (is_string($env) && trim($env) !== '') {
            foreach (explode(PATH_SEPARATOR, $env) as $dir) {
                if (trim($dir) !== '') {
                    $dirs[] = trim($dir);
                }
            }
        }

        $base = '/home/cabnet/mail/gov.cabnet.app';
        $mailboxes = ['bolt-bridge', 'bolt', 'pre-ride', 'preride', 'edxeix'];
        foreach ($mailboxes as $box) {
            foreach (['new', 'cur'] as $folder) {
                $dirs[] = $base . '/' . $box . '/' . $folder;
            }
        }

        return array_values(array_unique($dirs));
    }

    private function readLimited(string $file): string
    {
        $size = filesize($file);
        if ($size === false || $size <= 0) {
            return '';
        }
        $handle = fopen($file, 'rb');
        if (!$handle) {
            return '';
        }
        $raw = fread($handle, min((int)$size, self::MAX_FILE_BYTES));
        fclose($handle);
        return is_string($raw) ? $raw : '';
    }

    private function decodeEmailBody(string $raw): string
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        $headers = '';
        $body = $raw;
        if (str_contains($raw, "\n\n")) {
            [$headers, $body] = explode("\n\n", $raw, 2);
        }

        $contentType = $this->headerValue($headers, 'Content-Type');
        $transferEncoding = strtolower($this->headerValue($headers, 'Content-Transfer-Encoding'));

        if (stripos($contentType, 'multipart/') !== false && preg_match('/boundary="?([^";]+)"?/i', $contentType, $m)) {
            $boundary = $m[1];
            $parts = explode('--' . $boundary, $body);
            $best = '';
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '' || $part === '--') {
                    continue;
                }
                $partHeaders = '';
                $partBody = $part;
                if (str_contains($part, "\n\n")) {
                    [$partHeaders, $partBody] = explode("\n\n", $part, 2);
                }
                $partType = $this->headerValue($partHeaders, 'Content-Type');
                $partEncoding = strtolower($this->headerValue($partHeaders, 'Content-Transfer-Encoding'));
                if (stripos($partType, 'text/plain') !== false) {
                    return $this->cleanDecodedText($this->decodeTransfer($partBody, $partEncoding));
                }
                if ($best === '' && stripos($partType, 'text/html') !== false) {
                    $best = $this->cleanDecodedText($this->decodeTransfer($partBody, $partEncoding));
                }
            }
            if ($best !== '') {
                return $best;
            }
        }

        return $this->cleanDecodedText($this->decodeTransfer($body, $transferEncoding));
    }

    private function headerValue(string $headers, string $name): string
    {
        if ($headers === '') {
            return '';
        }
        $lines = preg_split('/\n/', $headers) ?: [];
        $combined = [];
        foreach ($lines as $line) {
            if (preg_match('/^[ \t]/', $line) && !empty($combined)) {
                $combined[count($combined) - 1] .= ' ' . trim($line);
            } else {
                $combined[] = trim($line);
            }
        }
        foreach ($combined as $line) {
            if (stripos($line, $name . ':') === 0) {
                return trim(substr($line, strlen($name) + 1));
            }
        }
        return '';
    }

    private function decodeTransfer(string $body, string $encoding): string
    {
        $body = trim($body);
        if ($encoding === 'base64') {
            $decoded = base64_decode($body, true);
            return is_string($decoded) ? $decoded : $body;
        }
        if ($encoding === 'quoted-printable' || preg_match('/=[0-9A-F]{2}/i', $body)) {
            return quoted_printable_decode($body);
        }
        return $body;
    }

    private function cleanDecodedText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (preg_match('/<[^>]+>/', $text) === 1) {
            // Preserve block boundaries from HTML-only Bolt/Gmail messages.
            $text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text) ?? $text;
            $text = preg_replace('/<\s*\/\s*(p|div|li|tr|h[1-6])\s*>/i', "\n", $text) ?? $text;
            $text = preg_replace('/<\s*(p|div|li|tr|h[1-6])\b[^>]*>/i', "\n", $text) ?? $text;
            $text = strip_tags($text);
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xC2\xA0", ' ', $text);
        $text = preg_replace('/[\t ]+/', ' ', $text) ?? $text;
        $text = preg_replace('/[ ]*\n[ ]*/', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function looksLikeBoltPreRide(string $body): bool
    {
        $bodyLower = function_exists('mb_strtolower') ? mb_strtolower($body, 'UTF-8') : strtolower($body);
        $required = ['operator:', 'customer:', 'driver:', 'vehicle:', 'pickup:', 'drop-off:'];
        $hits = 0;
        foreach ($required as $needle) {
            if (str_contains($bodyLower, $needle)) {
                $hits++;
            }
        }
        return $hits >= 4 && (str_contains($bodyLower, 'estimated pick') || str_contains($bodyLower, 'start time:'));
    }

    private function safeSourceName(string $file): string
    {
        $parts = explode('/', str_replace('\\', '/', $file));
        return implode('/', array_slice($parts, -4));
    }

    /** @param array<int,string> $dirs @return array<int,string> */
    private function safeDirs(array $dirs): array
    {
        $safe = [];
        foreach ($dirs as $dir) {
            $parts = explode('/', str_replace('\\', '/', $dir));
            $safe[] = implode('/', array_slice($parts, -5));
        }
        return array_values(array_unique($safe));
    }
}
