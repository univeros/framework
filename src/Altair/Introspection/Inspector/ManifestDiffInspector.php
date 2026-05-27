<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Introspection\Inspector;

use Altair\Introspection\Result\InspectionTable;

use const DIRECTORY_SEPARATOR;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Coarse manifest staleness check: walks the on-disk `.agent/` tree and
 * compares per-file SHA-256s against the in-memory manifests the host
 * just rebuilt.
 *
 * The full manifest regeneration lives in `univeros/agent-spec`; this
 * inspector consumes its output (a `path => content` map) and reports
 * three buckets: `stale`, `missing`, `extra`. Exit code policy lives in
 * the CLI command (`manifest:diff` exits non-zero when any bucket is
 * non-empty).
 *
 * Pure: no I/O writes, no manifest regeneration of its own. Hosts pass
 * in the regenerated content as a hashmap; that way this stays usable
 * from tests with synthetic input.
 */
final readonly class ManifestDiffInspector
{
    public function __construct(
        private string $manifestRoot,
    ) {}

    /**
     * @param array<string, string> $regenerated Relative path → expected file contents.
     */
    public function diff(array $regenerated): InspectionTable
    {
        $onDisk = $this->scanDisk();

        $rows = [];
        $stale = 0;
        $missing = 0;
        $extra = 0;

        foreach ($regenerated as $relative => $expected) {
            $expectedSha = hash('sha256', $expected);
            if (!isset($onDisk[$relative])) {
                $rows[] = [
                    'path' => $relative,
                    'status' => 'missing',
                    'on_disk_sha' => '',
                    'regenerated_sha' => $expectedSha,
                ];
                $missing++;
                continue;
            }

            if ($onDisk[$relative] !== $expectedSha) {
                $rows[] = [
                    'path' => $relative,
                    'status' => 'stale',
                    'on_disk_sha' => $onDisk[$relative],
                    'regenerated_sha' => $expectedSha,
                ];
                $stale++;
            }
        }

        foreach ($onDisk as $relative => $sha) {
            if (!\array_key_exists($relative, $regenerated)) {
                $rows[] = [
                    'path' => $relative,
                    'status' => 'extra',
                    'on_disk_sha' => $sha,
                    'regenerated_sha' => '',
                ];
                $extra++;
            }
        }

        usort($rows, static fn(array $a, array $b): int => [$a['status'], $a['path']] <=> [$b['status'], $b['path']]);

        return new InspectionTable(
            title: \sprintf('Manifest drift report (%s)', $this->manifestRoot),
            columns: ['path', 'status', 'on_disk_sha', 'regenerated_sha'],
            rows: $rows,
            extras: [
                'root' => $this->manifestRoot,
                'stale' => $stale,
                'missing' => $missing,
                'extra' => $extra,
                'in_sync' => $rows === [],
            ],
        );
    }

    /**
     * @return array<string, string> Relative path → sha256.
     */
    private function scanDisk(): array
    {
        if (!is_dir($this->manifestRoot)) {
            return [];
        }

        $out = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->manifestRoot, FilesystemIterator::SKIP_DOTS),
        );
        $prefix = rtrim($this->manifestRoot, '/\\') . DIRECTORY_SEPARATOR;

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            $absolute = (string) $file;
            $relative = str_starts_with($absolute, $prefix) ? substr($absolute, \strlen($prefix)) : $absolute;

            $sha = @hash_file('sha256', $absolute);
            if ($sha !== false) {
                $out[str_replace(DIRECTORY_SEPARATOR, '/', $relative)] = $sha;
            }
        }

        return $out;
    }
}
