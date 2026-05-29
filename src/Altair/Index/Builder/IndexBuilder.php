<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Builder;

use Altair\Index\Model\ParsedFile;
use Altair\Index\Parser\PhpFileWalker;
use Altair\Index\Parser\YamlSpecWalker;
use Altair\Index\Storage\SqliteStorage;

/**
 * Drives a full or incremental rebuild of the symbol-usage index.
 *
 * A full build truncates and re-walks everything. An incremental build hashes
 * each scanned file, re-walks only those whose hash changed since the last
 * build, and drops files that have disappeared — so the common edit-one-file
 * case stays cheap. Walking is content-hash driven, not mtime driven, so a
 * touched-but-unchanged file is correctly skipped.
 */
final readonly class IndexBuilder
{
    public function __construct(
        private IndexConfig $config,
        private SqliteStorage $storage,
        private SourceScanner $scanner,
        private PhpFileWalker $phpWalker = new PhpFileWalker(),
        private YamlSpecWalker $specWalker = new YamlSpecWalker(),
    ) {}

    public function build(bool $incremental = false): BuildResult
    {
        $start = hrtime(true);
        $this->storage->initialise();

        if (!$incremental) {
            $this->storage->truncate();
        }

        $known = $incremental ? $this->storage->fileHashes() : [];
        $php = $this->scanner->phpFiles();
        $specs = $this->scanner->specFiles();
        $current = [...$php, ...$specs];

        $removed = $this->dropMissing($known, $current);

        $indexed = 0;
        $skipped = 0;
        foreach ($php as $relative) {
            $this->walkInto($relative, $known, $this->phpWalker->walk(...), $indexed, $skipped);
        }

        foreach ($specs as $relative) {
            $this->walkInto($relative, $known, $this->specWalker->walk(...), $indexed, $skipped);
        }

        $this->storage->setMeta('last_built_at', date(DATE_ATOM));

        return new BuildResult(
            $incremental,
            \count($current),
            $indexed,
            $skipped,
            $removed,
            $this->storage->symbolCount(),
            $this->storage->usageCount(),
            (int) ((hrtime(true) - $start) / 1_000_000),
        );
    }

    /**
     * @param array<string, string>             $known
     * @param callable(string, string): ParsedFile $walk
     */
    private function walkInto(string $relative, array $known, callable $walk, int &$indexed, int &$skipped): void
    {
        $content = @file_get_contents($this->config->absolute($relative));
        if ($content === false) {
            return;
        }

        if (($known[$relative] ?? null) === ParsedFile::hash($content)) {
            ++$skipped;

            return;
        }

        $this->storage->persistFile($walk($relative, $content));
        ++$indexed;
    }

    /**
     * @param array<string, string> $known
     * @param list<string>          $current
     */
    private function dropMissing(array $known, array $current): int
    {
        $present = array_fill_keys($current, true);

        $removed = 0;
        foreach (array_keys($known) as $path) {
            if (!isset($present[$path])) {
                $this->storage->removeFile($path);
                ++$removed;
            }
        }

        return $removed;
    }
}
