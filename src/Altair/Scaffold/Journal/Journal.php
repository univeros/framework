<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal;

use Altair\Scaffold\Journal\Exception\EntryNotFoundException;
use Altair\Scaffold\Journal\Exception\JournalException;
use Altair\Scaffold\Journal\Exception\RewindRefusedException;
use Altair\Scaffold\Journal\Storage\FilesystemStorage;
use DateTimeImmutable;
use DateTimeInterface;

use const DIRECTORY_SEPARATOR;

use Generator;

/**
 * High-level read/write/query facade over the {@see FilesystemStorage}.
 *
 * - `record()` persists a freshly-built {@see JournalEntry}
 * - `history()` / `tail()` walk the directory in either direction
 * - `findById()` resolves either a full id or a unique prefix (so an
 *   agent can pass the first 8 chars of the id without juggling the
 *   full timestamp form)
 * - `rewind()` restores the workspace to the pre-entry state, refusing
 *   when generated files have been hand-edited unless `force` is set
 *
 * Rewind / replay do their I/O against a {@see ProjectRoot} so unit
 * tests can swap in a tmp directory.
 */
final readonly class Journal
{
    public function __construct(
        private FilesystemStorage $storage,
        private string $projectRoot,
    ) {}

    public function record(JournalEntry $entry): string
    {
        return $this->storage->save($entry);
    }

    /**
     * Find an entry by full id, or by the shortest unambiguous prefix.
     */
    public function findById(string $idOrPrefix): JournalEntry
    {
        if ($this->storage->exists($idOrPrefix)) {
            return $this->storage->load($idOrPrefix);
        }

        $matches = [];
        foreach ($this->storage->readAll() as $entry) {
            if (str_starts_with($entry->id, $idOrPrefix)) {
                $matches[] = $entry;
            }
        }

        if ($matches === []) {
            throw new EntryNotFoundException(\sprintf("No journal entry matches '%s'.", $idOrPrefix));
        }

        if (\count($matches) > 1) {
            throw new JournalException(\sprintf(
                "Prefix '%s' is ambiguous — matches %d entries: %s",
                $idOrPrefix,
                \count($matches),
                implode(', ', array_map(static fn(JournalEntry $e): string => $e->id, $matches)),
            ));
        }

        return $matches[0];
    }

    /**
     * Newest-first iterator. Optional `$limit` (null = all).
     *
     * @return Generator<int, JournalEntry>
     */
    public function tail(?int $limit = null): Generator
    {
        $i = 0;
        foreach ($this->storage->readReverse() as $entry) {
            yield $entry;
            if ($limit !== null && ++$i >= $limit) {
                return;
            }
        }
    }

    /**
     * Oldest-first iterator (full history).
     *
     * @return Generator<int, JournalEntry>
     */
    public function history(): Generator
    {
        yield from $this->storage->readAll();
    }

    /**
     * Reverse the effect of an entry on the workspace.
     *
     * - `files_created` → deleted (if their on-disk sha matches the
     *    recorded sha; otherwise added to the "unsafe" list and either
     *    skipped or — with `$force` — deleted anyway).
     * - `files_modified` → restored from the embedded `content_before`.
     * - Entry is **not** deleted; instead `reverted_at` is appended so
     *   the history stays auditable.
     *
     * @return array{ deleted: list<string>, restored: list<string>, skipped: list<string> }
     */
    public function rewind(JournalEntry $entry, bool $force = false): array
    {
        if ($entry->isReverted()) {
            throw new JournalException(\sprintf("Entry '%s' was already reverted at %s.", $entry->id, $entry->revertedAt?->format(DateTimeInterface::RFC3339_EXTENDED) ?? '(?)'));
        }

        $unsafe = [];
        $deleted = [];
        $restored = [];
        $skipped = [];

        foreach ($entry->filesCreated as $snapshot) {
            $absolute = $this->absolute($snapshot->path);
            if (!is_file($absolute)) {
                $skipped[] = $snapshot->path;
                continue;
            }

            $currentSha = hash_file('sha256', $absolute);
            if ($snapshot->shaAfter !== null && $currentSha !== $snapshot->shaAfter) {
                $unsafe[] = $snapshot->path;
                if (!$force) {
                    $skipped[] = $snapshot->path;
                    continue;
                }
            }

            if (!@unlink($absolute)) {
                throw new JournalException(\sprintf("Cannot delete '%s' during rewind.", $absolute));
            }

            $deleted[] = $snapshot->path;
        }

        foreach ($entry->filesModified as $snapshot) {
            if ($snapshot->contentBefore === null) {
                $skipped[] = $snapshot->path;
                continue;
            }

            $absolute = $this->absolute($snapshot->path);
            if (is_file($absolute) && $snapshot->shaAfter !== null) {
                $currentSha = hash_file('sha256', $absolute);
                if ($currentSha !== $snapshot->shaAfter) {
                    $unsafe[] = $snapshot->path;
                    if (!$force) {
                        $skipped[] = $snapshot->path;
                        continue;
                    }
                }
            }

            $this->ensureParentDirectory($absolute);
            if (@file_put_contents($absolute, $snapshot->contentBefore, LOCK_EX) === false) {
                throw new JournalException(\sprintf("Cannot restore '%s' during rewind.", $absolute));
            }

            $restored[] = $snapshot->path;
        }

        if ($unsafe !== [] && !$force) {
            throw new RewindRefusedException(
                \sprintf(
                    "Refusing to rewind '%s': %d file(s) were hand-edited after scaffolding (%s). Re-run with --force to override.",
                    $entry->id,
                    \count($unsafe),
                    implode(', ', $unsafe),
                ),
                $unsafe,
            );
        }

        $this->storage->save($entry->withRevertedAt(new DateTimeImmutable('now')));

        return ['deleted' => $deleted, 'restored' => $restored, 'skipped' => $skipped];
    }

    public function storage(): FilesystemStorage
    {
        return $this->storage;
    }

    public function projectRoot(): string
    {
        return $this->projectRoot;
    }

    private function absolute(string $relative): string
    {
        return $this->projectRoot . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
    }

    private function ensureParentDirectory(string $absolute): void
    {
        $parent = \dirname($absolute);
        if (!is_dir($parent) && !@mkdir($parent, 0o775, true) && !is_dir($parent)) {
            throw new JournalException(\sprintf("Cannot create directory '%s'.", $parent));
        }
    }
}
