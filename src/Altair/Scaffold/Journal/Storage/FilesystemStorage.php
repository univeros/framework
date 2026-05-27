<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal\Storage;

use Altair\Scaffold\Journal\Exception\EntryNotFoundException;
use Altair\Scaffold\Journal\Exception\JournalException;
use Altair\Scaffold\Journal\JournalEntry;

use const DIRECTORY_SEPARATOR;

use Generator;
use JsonException;
use Throwable;

/**
 * One JSON file per entry under `.altair/journal/`.
 *
 * Writes are atomic — tmp + rename guards against torn reads if two
 * `bin/altair spec scaffold` processes run concurrently. Reads tolerate
 * malformed files (one bad file doesn't break iteration).
 */
final readonly class FilesystemStorage
{
    public function __construct(
        private string $directory,
    ) {}

    public function save(JournalEntry $entry): string
    {
        $this->ensureDirectory();

        $path = $this->pathFor($entry->id);
        $tmp = $path . '.tmp.' . bin2hex(random_bytes(4));

        if (@file_put_contents($tmp, $entry->toJson(), LOCK_EX) === false) {
            throw new JournalException(\sprintf("Cannot write journal tmp file '%s'.", $tmp));
        }

        if (!@rename($tmp, $path)) {
            @unlink($tmp);
            throw new JournalException(\sprintf("Cannot rename '%s' → '%s'.", $tmp, $path));
        }

        return $path;
    }

    public function load(string $id): JournalEntry
    {
        $path = $this->pathFor($id);
        if (!is_file($path)) {
            throw new EntryNotFoundException(\sprintf("Journal entry '%s' not found at %s.", $id, $path));
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new JournalException(\sprintf("Cannot read journal entry '%s'.", $id));
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw new JournalException(\sprintf("Journal entry '%s' is not valid JSON: %s", $id, $jsonException->getMessage()), 0, $jsonException);
        }

        return JournalEntry::fromArray($data);
    }

    /**
     * Iterate entries oldest → newest.
     *
     * @return Generator<int, JournalEntry>
     */
    public function readAll(): Generator
    {
        foreach ($this->idsSorted() as $id) {
            try {
                yield $this->load($id);
            } catch (Throwable) {
                continue;
            }
        }
    }

    /**
     * Iterate entries newest → oldest.
     *
     * @return Generator<int, JournalEntry>
     */
    public function readReverse(): Generator
    {
        $ids = $this->idsSorted();
        for ($i = \count($ids) - 1; $i >= 0; $i--) {
            try {
                yield $this->load($ids[$i]);
            } catch (Throwable) {
                continue;
            }
        }
    }

    public function exists(string $id): bool
    {
        return is_file($this->pathFor($id));
    }

    public function path(): string
    {
        return $this->directory;
    }

    public function pathFor(string $id): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . $id . '.json';
    }

    /**
     * @return list<string> Entry ids, lexicographically sorted (which equals
     *                     chronologically sorted because the id starts with a
     *                     `Ymd\THis\Z` timestamp).
     */
    private function idsSorted(): array
    {
        if (!is_dir($this->directory)) {
            return [];
        }

        $files = @scandir($this->directory) ?: [];
        $ids = [];
        foreach ($files as $name) {
            if (str_ends_with($name, '.json') && !str_contains($name, '.tmp.')) {
                $ids[] = substr($name, 0, -\strlen('.json'));
            }
        }

        sort($ids);

        return $ids;
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw new JournalException(\sprintf("Cannot create journal directory '%s'.", $this->directory));
        }
    }
}
