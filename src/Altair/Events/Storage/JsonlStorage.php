<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Events\Storage;

use Altair\Events\Contracts\EventStorageInterface;
use Altair\Events\Event;
use Altair\Events\Exception\StorageException;
use Generator;
use JsonException;
use Override;
use SplFileObject;
use Throwable;

/**
 * Newline-delimited JSON storage for the event log.
 *
 * Appends are guarded by an exclusive advisory lock (`flock LOCK_EX`),
 * which is sufficient for the common case of multiple `bin/altair`
 * processes writing into the same `.altair/events.jsonl`. Reads do not
 * take a lock — a torn write would surface as a single line that fails
 * to JSON-decode and the Reader is built to skip those gracefully.
 *
 * The parent directory is created on demand (mode 0775) so the host
 * application doesn't need to provision `.altair/` ahead of time.
 */
final readonly class JsonlStorage implements EventStorageInterface
{
    public function __construct(
        private string $path,
    ) {}

    #[Override]
    public function append(Event $event): void
    {
        $this->ensureParentDirectory();

        $line = $event->toJsonLine() . "\n";

        $handle = @fopen($this->path, 'ab');
        if ($handle === false) {
            throw new StorageException(\sprintf("Cannot open '%s' for append.", $this->path));
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                throw new StorageException(\sprintf("Cannot lock '%s' for append.", $this->path));
            }

            try {
                $written = fwrite($handle, $line);
                if ($written === false || $written !== \strlen($line)) {
                    throw new StorageException(\sprintf("Short write to '%s'.", $this->path));
                }

                fflush($handle);
            } finally {
                flock($handle, LOCK_UN);
            }
        } finally {
            fclose($handle);
        }
    }

    #[Override]
    public function readAll(): Generator
    {
        if (!is_file($this->path)) {
            return;
        }

        $file = new SplFileObject($this->path, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);

        foreach ($file as $raw) {
            if (!\is_string($raw)) {
                continue;
            }

            if (trim($raw) === '') {
                continue;
            }

            $event = $this->tryDecode($raw);
            if ($event instanceof Event) {
                yield $event;
            }
        }
    }

    #[Override]
    public function readReverse(): Generator
    {
        if (!is_file($this->path)) {
            return;
        }

        $lines = @file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        for ($i = \count($lines) - 1; $i >= 0; $i--) {
            $event = $this->tryDecode($lines[$i]);
            if ($event instanceof Event) {
                yield $event;
            }
        }
    }

    #[Override]
    public function count(): int
    {
        if (!is_file($this->path)) {
            return 0;
        }

        $count = 0;
        $file = new SplFileObject($this->path, 'r');
        $file->setFlags(SplFileObject::DROP_NEW_LINE | SplFileObject::SKIP_EMPTY);
        foreach ($file as $raw) {
            if (\is_string($raw) && trim($raw) !== '') {
                $count++;
            }
        }

        return $count;
    }

    public function path(): string
    {
        return $this->path;
    }

    private function tryDecode(string $raw): ?Event
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!\is_array($data)) {
            return null;
        }

        try {
            return Event::fromArray($data);
        } catch (Throwable) {
            return null;
        }
    }

    private function ensureParentDirectory(): void
    {
        $parent = \dirname($this->path);
        if ($parent !== '' && !is_dir($parent) && !@mkdir($parent, 0o775, true) && !is_dir($parent)) {
            throw new StorageException(\sprintf("Cannot create directory '%s'.", $parent));
        }
    }
}
