<?php

namespace Bridge\Mail;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class BoltMaildirScanner
{
    private string $maildir;
    private int $maxBytes;

    public function __construct(string $maildir, int $maxBytes = 1048576)
    {
        $this->maildir = rtrim($maildir, '/');
        $this->maxBytes = $maxBytes;
    }

    public function maildir(): string
    {
        return $this->maildir;
    }

    /**
     * @return array<int,array{path:string,basename:string,mtime:int,size:int}>
     */
    public function candidateFiles(int $limit = 200, int $daysBack = 14): array
    {
        if (!is_dir($this->maildir)) {
            return [];
        }

        $cutoff = time() - max(1, $daysBack) * 86400;
        $roots = [];
        foreach (['new', 'cur'] as $subdir) {
            $path = $this->maildir . '/' . $subdir;
            if (is_dir($path)) {
                $roots[] = $path;
            }
        }

        $files = [];
        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file instanceof SplFileInfo || !$file->isFile()) {
                    continue;
                }

                $mtime = $file->getMTime();
                $size = $file->getSize();
                if ($mtime < $cutoff || $size <= 0 || $size > $this->maxBytes) {
                    continue;
                }

                $path = $file->getPathname();
                $sample = @file_get_contents($path, false, null, 0, min($size, 65536));
                if (!is_string($sample)) {
                    continue;
                }

                if (!$this->looksLikeBoltRideEmail($sample)) {
                    continue;
                }

                $files[] = [
                    'path' => $path,
                    'basename' => basename($path),
                    'mtime' => $mtime,
                    'size' => $size,
                ];
            }
        }

        usort($files, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return array_slice($files, 0, max(1, $limit));
    }

    public function readFile(string $path): ?string
    {
        if (!is_file($path) || filesize($path) > $this->maxBytes) {
            return null;
        }

        $raw = @file_get_contents($path);
        return is_string($raw) ? $raw : null;
    }

    private function looksLikeBoltRideEmail(string $sample): bool
    {
        $decoded = quoted_printable_decode($sample);
        return stripos($decoded, 'Ride details') !== false
            || (stripos($decoded, 'Operator:') !== false && stripos($decoded, 'Estimated pick-up time:') !== false);
    }
}
