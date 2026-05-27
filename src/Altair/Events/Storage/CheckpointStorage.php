<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Storage;

use Altair\Events\Exception\InvalidArgumentException;
use Altair\Events\Exception\StorageException;
use DateTimeImmutable;
use DateTimeInterface;

use const DIRECTORY_SEPARATOR;

use FilesystemIterator;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Named bookmarks into the event stream.
 *
 * One file per checkpoint at `.altair/checkpoints/<name>.json`. Names
 * map 1:1 to filenames, so we restrict them to characters that are
 * safe on every filesystem we care about.
 */
final readonly class CheckpointStorage
{
    private const string NAME_PATTERN = '/^[A-Za-z0-9_.\-\/]+$/';

    public function __construct(
        private string $directory,
    ) {}

    public function create(string $name, string $eventId): void
    {
        $this->guardName($name);
        $this->ensureDirectory($name);

        $payload = [
            'name' => $name,
            'event_id' => $eventId,
            'created_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::RFC3339_EXTENDED),
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
        } catch (JsonException $e) {
            throw new StorageException('Checkpoint payload not JSON-encodable: ' . $e->getMessage(), 0, $e);
        }

        $path = $this->pathFor($name);
        if (@file_put_contents($path, $json, LOCK_EX) === false) {
            throw new StorageException(\sprintf("Cannot write checkpoint '%s' → '%s'.", $name, $path));
        }
    }

    public function exists(string $name): bool
    {
        $this->guardName($name);

        return is_file($this->pathFor($name));
    }

    /**
     * @return array{ name: string, event_id: string, created_at: string }
     */
    public function read(string $name): array
    {
        $this->guardName($name);

        $path = $this->pathFor($name);
        if (!is_file($path)) {
            throw new InvalidArgumentException(\sprintf("Checkpoint '%s' does not exist.", $name));
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new StorageException(\sprintf("Cannot read checkpoint '%s'.", $name));
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new StorageException("Checkpoint '{$name}' is not valid JSON: " . $e->getMessage(), 0, $e);
        }

        foreach (['name', 'event_id', 'created_at'] as $required) {
            if (!isset($decoded[$required]) || !\is_string($decoded[$required])) {
                throw new StorageException(\sprintf("Checkpoint '%s' is missing field '%s'.", $name, $required));
            }
        }

        return [
            'name' => $decoded['name'],
            'event_id' => $decoded['event_id'],
            'created_at' => $decoded['created_at'],
        ];
    }

    public function delete(string $name): bool
    {
        $this->guardName($name);
        $path = $this->pathFor($name);

        return is_file($path) && @unlink($path);
    }

    /**
     * @return list<string> Names of all stored checkpoints, sorted alphabetically.
     */
    public function list(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $names = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'json') {
                continue;
            }
            $relative = ltrim(substr((string) $file, \strlen($this->directory)), DIRECTORY_SEPARATOR);
            $name = substr($relative, 0, -\strlen('.json'));
            // Normalise back-slashes (Windows) to forward-slashes so the
            // public-facing name matches what was passed to create().
            $names[] = str_replace(DIRECTORY_SEPARATOR, '/', $name);
        }

        sort($names);

        return $names;
    }

    public function directory(): string
    {
        return $this->directory;
    }

    private function pathFor(string $name): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $name . '.json';
    }

    private function guardName(string $name): void
    {
        if ($name === '' || !preg_match(self::NAME_PATTERN, $name)) {
            throw new InvalidArgumentException(
                \sprintf("Invalid checkpoint name '%s'. Allowed: alphanumeric, '_', '.', '-', '/'.", $name),
            );
        }
        if (str_contains($name, '..')) {
            throw new InvalidArgumentException("Checkpoint name must not contain '..'.");
        }
    }

    private function ensureDirectory(string $name): void
    {
        $target = \dirname($this->pathFor($name));
        if (!is_dir($target) && !@mkdir($target, 0o775, true) && !is_dir($target)) {
            throw new StorageException(\sprintf("Cannot create directory '%s'.", $target));
        }
    }
}
