<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use JsonException;
use RuntimeException;

/**
 * Structured outcome of an `openapi:import` run.
 *
 * Shape is the agent-facing contract for `--format=json`. Fields are kept
 * flat (no nested objects) so agents can parse them with `jq` one-liners
 * without juggling structure.
 *
 * `journalId` and `eventId` are populated only when a `Journal` /
 * `RecorderInterface` is bound; under a NullRecorder (and in tests) they
 * stay null, which keeps the JSON receipt byte-stable for the same input.
 */
final readonly class ImportReceipt
{
    /**
     * @param list<string>                              $specsWritten Relative paths of emitted Altair specs.
     * @param list<string>                              $scaffolded   Relative paths produced by chained spec:scaffold.
     * @param list<string>                              $rolledBack   Specs deleted after a scaffold failure.
     * @param list<string>                              $warnings     Non-fatal messages for the agent.
     * @param list<array{pointer: string, message: string}> $unmapped  Schemas the emitter could not express.
     */
    public function __construct(
        public bool $ok,
        public string $input,
        public array $specsWritten,
        public bool $scaffoldRequested,
        public array $scaffolded,
        public array $rolledBack,
        public array $unmapped,
        public array $warnings,
        public ?string $journalId,
        public ?string $eventId,
        public ?string $error,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'input' => $this->input,
            'specs_written' => $this->specsWritten,
            'scaffolded' => $this->scaffoldRequested,
            'scaffold_files' => $this->scaffolded,
            'rolled_back' => $this->rolledBack,
            'unmapped' => $this->unmapped,
            'warnings' => $this->warnings,
            'journal_id' => $this->journalId,
            'event_id' => $this->eventId,
            'error' => $this->error,
        ];
    }

    public function toJson(): string
    {
        try {
            return json_encode(
                $this->toArray(),
                JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $jsonException) {
            // ImportReceipt's fields are all primitives or arrays of strings —
            // failure here means a programmer error upstream, not user input.
            throw new RuntimeException('ImportReceipt is not JSON-encodable: ' . $jsonException->getMessage(), 0, $jsonException);
        }
    }
}
