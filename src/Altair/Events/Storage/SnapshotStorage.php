<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Storage;

use Altair\Events\Exception\StorageException;

use const DIRECTORY_SEPARATOR;

use JsonException;

/**
 * Sidecar JSON-blob storage for events whose change set is too large to
 * inline on a single `.jsonl` line (e.g. a 200-file rector run).
 *
 * One file per event, written atomically (tmp + rename) into
 * `.altair/snapshots/<event_id>.json`. The main log entry references it
 * via `changes.snapshot_ref`.
 */
final readonly class SnapshotStorage
{
    public function __construct(
        private string $directory,
    ) {}

    /**
     * @param array<string, mixed> $payload
     */
    public function write(string $eventId, array $payload): string
    {
        $this->ensureDirectory();

        $path = $this->pathFor($eventId);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new StorageException('Snapshot payload is not JSON-encodable: ' . $e->getMessage(), 0, $e);
        }

        if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new StorageException(\sprintf("Cannot write snapshot tmp file '%s'.", $tmp));
        }
        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new StorageException(\sprintf("Cannot rename snapshot '%s' → '%s'.", $tmp, $path));
        }

        return $this->relativeRef($eventId);
    }

    /**
     * @return array<string, mixed>|null Decoded payload, or null if the file
     *                                    does not exist or is unreadable.
     */
    public function read(string $eventId): ?array
    {
        $path = $this->pathFor($eventId);
        if (!is_file($path)) {
            return null;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
    }

    public function delete(string $eventId): bool
    {
        $path = $this->pathFor($eventId);

        return is_file($path) && @unlink($path);
    }

    public function directory(): string
    {
        return $this->directory;
    }

    private function pathFor(string $eventId): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $eventId . '.json';
    }

    private function relativeRef(string $eventId): string
    {
        return 'snapshots/' . $eventId . '.json';
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new StorageException(\sprintf("Cannot create directory '%s'.", $this->directory));
        }
    }
}
