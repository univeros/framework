<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Profiling\Storage;

use Altair\Profiling\Model\ProfileReport;

/**
 * Persists profile reports as one JSON file per profile under
 * `<projectRoot>/.altair/profiles/`. The directory is gitignored — profiles
 * are local artefacts.
 *
 * Listing reads only the file-name metadata and a single decoded header line,
 * so `profile:list` against a hundred stored profiles never deserialises a
 * megabyte of tree. Save rotates the directory to {@see $maxKept} newest
 * profiles by mtime so long-running developer machines do not accumulate
 * unbounded disk usage.
 */
final readonly class FilesystemProfileStorage
{
    public function __construct(
        private string $directory,
        private int $maxKept = 100,
    ) {}

    public function save(ProfileReport $report): void
    {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o755, true);
        }

        $path = $this->path($report->id);
        file_put_contents($path, json_encode($report->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->rotate();
    }

    public function load(string $id): ?ProfileReport
    {
        $path = $this->path($id);
        if (!is_file($path)) {
            return null;
        }

        $contents = (string) file_get_contents($path);
        $decoded = json_decode($contents, true);

        return \is_array($decoded) ? ProfileReport::fromArray($decoded) : null;
    }

    public function delete(string $id): void
    {
        $path = $this->path($id);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /**
     * @return list<ProfileSummary> newest first
     */
    public function list(int $limit = 50): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = glob($this->directory . '/*.json') ?: [];
        usort($files, static fn(string $a, string $b): int => (int) filemtime($b) <=> (int) filemtime($a));
        $files = \array_slice($files, 0, max(0, $limit));

        $summaries = [];
        foreach ($files as $file) {
            $summary = $this->summaryFromFile($file);
            if ($summary instanceof ProfileSummary) {
                $summaries[] = $summary;
            }
        }

        return $summaries;
    }

    private function rotate(): void
    {
        $files = glob($this->directory . '/*.json') ?: [];
        if (\count($files) <= $this->maxKept) {
            return;
        }

        usort($files, static fn(string $a, string $b): int => (int) filemtime($a) <=> (int) filemtime($b));
        $toDelete = \array_slice($files, 0, \count($files) - $this->maxKept);

        foreach ($toDelete as $file) {
            @unlink($file);
        }
    }

    private function summaryFromFile(string $file): ?ProfileSummary
    {
        $contents = (string) file_get_contents($file);
        $decoded = json_decode($contents, true);

        if (!\is_array($decoded) || !isset($decoded['id'])) {
            return null;
        }

        return new ProfileSummary(
            (string) $decoded['id'],
            (string) ($decoded['target'] ?? '<unknown>'),
            (string) ($decoded['created_at'] ?? ''),
            (int) ($decoded['total_samples'] ?? 0),
            (int) ($decoded['duration_ms'] ?? 0),
            (string) ($decoded['backend'] ?? ''),
        );
    }

    private function path(string $id): string
    {
        return $this->directory . '/' . $id . '.json';
    }
}
