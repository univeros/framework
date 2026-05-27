<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Journal\Cli;

use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * `bin/altair journal:list` — list journal entries (newest first).
 *
 * Named `journal:*` (not `spec:*`) to avoid collision with the
 * introspection sub-package's `spec:list` / `spec:show` commands —
 * those view raw YAML specs; these view scaffold-time history.
 */
#[Command(
    name: 'journal:list',
    description: 'List scaffold journal entries newest-first.',
)]
final readonly class HistoryCommand
{
    public function __construct(
        private Journal $journal,
    ) {}

    public function __invoke(
        #[Option(description: 'Show at most N entries (newest first).', short: 'n')]
        ?int $n = 50,
        #[Option(description: 'Only entries on or after this timestamp (any DateTime-parseable string).')]
        ?string $since = null,
        #[Option(description: 'Only entries whose spec path equals this value.')]
        ?string $spec = null,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
    ): int {
        $threshold = null;
        if ($since !== null) {
            try {
                $threshold = new DateTimeImmutable($since);
            } catch (Throwable $throwable) {
                echo \sprintf("Could not parse --since='%s': %s%s", $since, $throwable->getMessage(), PHP_EOL);

                return 2;
            }
        }

        $rows = [];
        foreach ($this->journal->tail($n) as $entry) {
            if ($threshold instanceof DateTimeImmutable && $entry->timestamp < $threshold) {
                continue;
            }

            if ($spec !== null && $entry->spec['path'] !== $spec) {
                continue;
            }

            $rows[] = $entry;
        }

        if ($rows === []) {
            if ($format !== 'json') {
                echo "No journal entries match.\n";
            } else {
                echo "[]\n";
            }

            return 0;
        }

        if ($format === 'json') {
            $payload = array_map(static fn(JournalEntry $e): array => [
                'id' => $e->id,
                'operation' => $e->operation->value,
                'timestamp' => $e->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
                'spec_path' => $e->spec['path'],
                'spec_sha256' => $e->spec['sha256'],
                'files_created' => \count($e->filesCreated),
                'files_modified' => \count($e->filesModified),
                'files_skipped' => \count($e->filesSkipped),
                'reverted_at' => $e->revertedAt?->format(DateTimeInterface::RFC3339_EXTENDED),
            ], $rows);
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";

            return 0;
        }

        foreach ($rows as $entry) {
            $flag = $entry->isReverted() ? ' [reverted]' : '';
            echo \sprintf(
                "%s  %-19s  %-8s  %s  (+%d ~%d -%d)%s\n",
                substr($entry->id, 0, 24),
                $entry->timestamp->format('Y-m-d H:i:s'),
                $entry->operation->value,
                $entry->spec['path'],
                \count($entry->filesCreated),
                \count($entry->filesModified),
                \count($entry->filesSkipped),
                $flag,
            );
        }

        return 0;
    }
}
