<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Mcp\Tool\Generation;

use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Mcp\Attribute\McpTool;
use Altair\Mcp\Contracts\McpToolInterface;
use Altair\Mcp\Exception\GuardrailException;
use Altair\Mcp\Exception\McpException;
use Altair\Mcp\Guard\ServerMode;
use Altair\Mcp\Support\EventLog;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;
use Override;

#[McpTool(
    name: 'framework__rewind_spec',
    description: 'Undo a scaffold operation, restoring files to their pre-scaffold state.',
    inputSchema: __DIR__ . '/../../Schema/rewind-spec-input.json',
    outputSchema: __DIR__ . '/../../Schema/object-output.json',
)]
final readonly class RewindSpecTool implements McpToolInterface
{
    public function __construct(
        private ServerMode $mode,
        private EventLog $events,
        private ?Journal $journal = null,
    ) {}

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    #[Override]
    public function call(array $input): array
    {
        if (!$this->mode->allowsFileMutation()) {
            throw new GuardrailException('Server is in readonly mode; rewind is disabled.');
        }

        if (!$this->journal instanceof Journal) {
            throw new McpException('The scaffold journal is not enabled; nothing to rewind.');
        }

        $id = \is_string($input['id'] ?? null) && $input['id'] !== '' ? $input['id'] : null;
        $force = ($input['force'] ?? false) === true;

        $entry = $id !== null ? $this->journal->findById($id) : $this->latest($this->journal);
        $result = $this->journal->rewind($entry, $force);

        $this->events->record(EventKind::Rewind, EventStatus::Ok, 'mcp framework__rewind_spec ' . $entry->id);

        return [
            'entry' => $entry->id,
            'deleted' => $result['deleted'] ?? [],
            'restored' => $result['restored'] ?? [],
            'skipped' => $result['skipped'] ?? [],
        ];
    }

    private function latest(Journal $journal): JournalEntry
    {
        foreach ($journal->tail(1) as $entry) {
            return $entry;
        }

        throw new McpException('No scaffold operations recorded; nothing to rewind.');
    }
}
