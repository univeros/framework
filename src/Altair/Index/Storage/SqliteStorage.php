<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Storage;

use Altair\Index\Model\ParsedFile;
use Altair\Index\Model\Symbol;
use Altair\Index\Model\Usage;
use PDO;
use Throwable;

/**
 * The write side of the index: persists a {@see ParsedFile}, tracks per-file
 * content hashes for incremental rebuilds, and owns schema creation.
 *
 * Persisting a file is idempotent and isolated: it clears any prior rows for
 * that path inside a transaction before inserting the new ones, so re-indexing
 * a changed file never leaves stale symbols or usages behind.
 */
final readonly class SqliteStorage
{
    public function __construct(private PDO $pdo) {}

    public function initialise(): void
    {
        Schema::create($this->pdo);
    }

    public function persistFile(ParsedFile $file): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->purge($file->path);
            $this->insertSymbols($file->symbols);
            $this->insertUsages($file->usages);
            $this->stampHash($file->path, $file->hash);
            $this->pdo->commit();
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();

            throw $throwable;
        }
    }

    public function removeFile(string $path): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->purge($path);
            $this->pdo->prepare('DELETE FROM files WHERE path = :p')->execute([':p' => $path]);
            $this->pdo->commit();
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();

            throw $throwable;
        }
    }

    public function truncate(): void
    {
        $this->pdo->beginTransaction();

        try {
            foreach (['usages', 'symbols', 'files'] as $table) {
                $this->pdo->exec('DELETE FROM ' . $table);
            }

            $this->pdo->commit();
        } catch (Throwable $throwable) {
            $this->pdo->rollBack();

            throw $throwable;
        }
    }

    /**
     * @return array<string, string>
     */
    public function fileHashes(): array
    {
        $statement = $this->pdo->prepare('SELECT path, hash FROM files');
        $statement->execute();

        /** @var list<array{path: string, hash: string}> $rows */
        $rows = $statement->fetchAll();

        $hashes = [];
        foreach ($rows as $row) {
            $hashes[$row['path']] = $row['hash'];
        }

        return $hashes;
    }

    public function setMeta(string $key, string $value): void
    {
        $this->pdo
            ->prepare('INSERT OR REPLACE INTO meta (key, value) VALUES (:k, :v)')
            ->execute([':k' => $key, ':v' => $value]);
    }

    public function getMeta(string $key): ?string
    {
        $statement = $this->pdo->prepare('SELECT value FROM meta WHERE key = :k');
        $statement->execute([':k' => $key]);

        $value = $statement->fetchColumn();

        return \is_string($value) ? $value : null;
    }

    public function symbolCount(): int
    {
        return $this->count('symbols');
    }

    public function usageCount(): int
    {
        return $this->count('usages');
    }

    private function count(string $table): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM ' . $table);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    private function purge(string $path): void
    {
        $this->pdo->prepare('DELETE FROM symbols WHERE file = :p')->execute([':p' => $path]);
        $this->pdo->prepare('DELETE FROM usages WHERE used_in_file = :p')->execute([':p' => $path]);
    }

    /**
     * @param list<Symbol> $symbols
     */
    private function insertSymbols(array $symbols): void
    {
        $statement = $this->pdo->prepare(
            'INSERT OR REPLACE INTO symbols (fqn, kind, file, line, visibility, is_readonly, is_static)
             VALUES (:fqn, :kind, :file, :line, :visibility, :readonly, :static)',
        );

        foreach ($symbols as $symbol) {
            $statement->execute([
                ':fqn' => $symbol->fqn,
                ':kind' => $symbol->kind->value,
                ':file' => $symbol->file,
                ':line' => $symbol->line,
                ':visibility' => $symbol->visibility,
                ':readonly' => $symbol->isReadonly ? 1 : 0,
                ':static' => $symbol->isStatic ? 1 : 0,
            ]);
        }
    }

    /**
     * @param list<Usage> $usages
     */
    private function insertUsages(array $usages): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO usages (target_fqn, used_in_file, used_in_line, usage_kind, context)
             VALUES (:fqn, :file, :line, :kind, :context)',
        );

        foreach ($usages as $usage) {
            $statement->execute([
                ':fqn' => $usage->fqn,
                ':file' => $usage->file,
                ':line' => $usage->line,
                ':kind' => $usage->kind->value,
                ':context' => $usage->context,
            ]);
        }
    }

    private function stampHash(string $path, string $hash): void
    {
        $this->pdo
            ->prepare('INSERT OR REPLACE INTO files (path, hash) VALUES (:p, :h)')
            ->execute([':p' => $path, ':h' => $hash]);
    }
}
