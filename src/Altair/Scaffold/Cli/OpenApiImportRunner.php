<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Scaffold\Cli;

use Altair\Events\Actor;
use Altair\Events\Changes;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event as RecorderEvent;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\Scaffold\Emitter\EmissionPlan;
use Altair\Scaffold\Emitter\EmittedFile;
use Altair\Scaffold\Emitter\EmittedFileKind;
use Altair\Scaffold\Journal\Journal;
use Altair\Scaffold\Journal\JournalEntry;
use Altair\Scaffold\Journal\SnapshotCollector;
use Altair\Scaffold\Sdk\Exception\SdkException;
use Altair\Scaffold\Sdk\Model\CoverageScanner;
use Altair\Scaffold\Sdk\Model\OpenApiDocument;
use Altair\Scaffold\Sdk\Model\OpenApiParser;
use Altair\Scaffold\Sdk\Model\RefBundler;
use Altair\Scaffold\Spec\Emitter\Exception\UnmappableSchemaException;
use Altair\Scaffold\Spec\Emitter\OperationMapper;
use Altair\Scaffold\Spec\Emitter\PathDeriver;
use Altair\Scaffold\Spec\Parser;
use Altair\Scaffold\Spec\Validator;
use Altair\Scaffold\Writer\FileWriter;
use Altair\Scaffold\Writer\WriteStatus;

use function array_values;
use function microtime;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Core logic of `bin/altair openapi:import`. Lives outside the Command
 * class so it can be unit-tested without booting the CLI runner.
 *
 * The runner is responsible for:
 *
 * - parsing the OpenAPI document via {@see OpenApiParser},
 * - walking operations through {@see OperationMapper} to build YAML
 *   structures (applying {@see PersistenceInferrer} when requested),
 * - dumping deterministic YAML and routing it through {@see FileWriter}
 *   so collisions respect `--force`,
 * - optionally chaining `spec:scaffold` (`EmissionPlan` + `FileWriter`)
 *   over each just-written spec,
 * - rolling back imported specs when the scaffold phase fails,
 * - recording one combined journal entry and one mutation event.
 */
final readonly class OpenApiImportRunner
{
    private const int YAML_INLINE_LEVEL = 6;

    private const int YAML_INDENT = 2;

    /**
     * Operation-level `x-altair-*` keys this release understands. Anything
     * outside this list still rides along verbatim — the parser captures
     * any `x-altair-*` key — but surfaces in the receipt's `warnings[]`
     * so v1 imports do not silently drop a key a future release relies on.
     */
    private const array KNOWN_OPERATION_EXTENSIONS = [
        'x-altair-domain',
        'x-altair-persistence',
        'x-altair-queue',
        'x-altair-idempotency',
        'x-altair-webhook',
        'x-altair-input-location',
    ];

    public function __construct(
        private OpenApiParser $parser = new OpenApiParser(),
        private OperationMapper $operationMapper = new OperationMapper(),
        private PathDeriver $pathDeriver = new PathDeriver(),
        private EmissionPlan $emissionPlan = new EmissionPlan(),
        private Parser $specParser = new Parser(),
        private Validator $specValidator = new Validator(),
        private PersistenceInferrer $persistenceInferrer = new PersistenceInferrer(),
        private CoverageScanner $coverageScanner = new CoverageScanner(),
        private ?Journal $journal = null,
        private ?RecorderInterface $events = null,
    ) {}

    public function run(OpenApiImportOptions $options): ImportReceipt
    {
        $startedAt = \microtime(true);
        $sourceContents = $this->readDocument($options->documentPath);
        if ($sourceContents === null) {
            return $this->failure(
                $options,
                \sprintf("OpenAPI document '%s' is not readable.", $options->documentPath),
                $startedAt,
            );
        }

        try {
            $bundle = (new RefBundler($this->baseDir($options->documentPath)))->bundle($this->decodeDocument($sourceContents));
            $document = $this->parser->parse($bundle->document);
            $plan = $this->planSpecs($document, $options);
        } catch (UnmappableSchemaException $unmappableSchemaException) {
            return $this->failure(
                $options,
                $unmappableSchemaException->getMessage(),
                $startedAt,
                [['pointer' => $unmappableSchemaException->jsonPointer, 'message' => $unmappableSchemaException->getMessage()]],
            );
        } catch (Throwable $throwable) {
            return $this->failure($options, $throwable->getMessage(), $startedAt);
        }

        $coverageWarnings = [...$bundle->warnings, ...$this->coverageScanner->scan($bundle->document)];

        if ($options->dryRun) {
            return $this->dryRunReceipt($options, $plan, $document, $coverageWarnings);
        }

        $collector = new SnapshotCollector($options->projectRoot);
        $writer = new FileWriter($options->projectRoot);

        $writtenSpecs = $this->writeSpecs($plan->files, $writer, $collector, $options->force);
        $scaffoldFiles = [];
        $rolledBack = [];
        $warnings = [...$this->initialWarnings($options), ...$this->unknownExtensionWarnings($document), ...$coverageWarnings, ...$plan->warnings];

        if ($options->scaffold && $writtenSpecs !== []) {
            try {
                $scaffoldFiles = $this->runScaffold($writtenSpecs, $options, $writer, $collector);
            } catch (Throwable $throwable) {
                $rolledBack = $this->rollback($writtenSpecs, $options->projectRoot);

                return $this->failure(
                    $options,
                    'Scaffold phase failed after import: ' . $throwable->getMessage(),
                    $startedAt,
                    [],
                    $writtenSpecs,
                    $rolledBack,
                    $warnings,
                );
            }
        }

        $journalId = $this->recordJournal($options, $sourceContents, $collector);
        $eventId = $this->recordEvent(
            options: $options,
            status: EventStatus::Ok,
            durationMs: (int) ((\microtime(true) - $startedAt) * 1000),
            createdCount: \count($writtenSpecs) + \count($scaffoldFiles),
            error: null,
        );

        return new ImportReceipt(
            ok: true,
            input: $options->documentPath,
            specsWritten: $writtenSpecs,
            scaffoldRequested: $options->scaffold,
            scaffolded: $scaffoldFiles,
            rolledBack: $rolledBack,
            unmapped: $plan->unmapped,
            warnings: $warnings,
            journalId: $journalId,
            eventId: $eventId,
            error: null,
        );
    }

    /**
     * Map every operation to a spec file. An operation whose schema cannot be
     * expressed in Altair's spec is skipped (recorded in the returned plan)
     * when `--skip-unmappable` is set; otherwise its {@see UnmappableSchemaException}
     * propagates and aborts the whole import.
     *
     * Mapping happens before the filename-collision check so a skipped
     * operation never reserves a filename — only operations that actually
     * emit a spec can collide.
     */
    private function planSpecs(OpenApiDocument $document, OpenApiImportOptions $options): ImportPlan
    {
        $deriver = $options->outDir !== null
            ? new PathDeriver(specRoot: $options->outDir)
            : $this->pathDeriver;

        // Resolve every filename up front so distinct operations that derive the
        // same short name (e.g. Petstore's two "update pet" operations) get
        // disambiguated instead of colliding. Throws only on a duplicate
        // operationId, which is an invalid spec.
        $filenames = $deriver->resolveFilenames($document->operations);

        $files = [];
        $unmapped = [];
        $warnings = [];
        foreach ($document->operations as $operation) {
            try {
                $structure = $this->operationMapper->map($document, $operation);
            } catch (UnmappableSchemaException $unmappableSchemaException) {
                if (!$options->skipUnmappable) {
                    throw $unmappableSchemaException;
                }

                $label = strtoupper($operation->method) . ' ' . $operation->path;
                $unmapped[] = [
                    'pointer' => $unmappableSchemaException->jsonPointer,
                    'message' => $unmappableSchemaException->getMessage(),
                ];
                $warnings[] = \sprintf(
                    'skipped %s: %s (at %s)',
                    $label,
                    $unmappableSchemaException->reason,
                    $unmappableSchemaException->jsonPointer,
                );

                continue;
            }

            if ($options->persistence === 'cycle') {
                $structure = $this->persistenceInferrer->apply($operation, $structure);
            }

            $contents = Yaml::dump($structure, self::YAML_INLINE_LEVEL, self::YAML_INDENT, Yaml::DUMP_OBJECT_AS_MAP);
            $files[] = new EmittedFile(relativePath: $filenames[$deriver->operationKey($operation)], contents: $contents, kind: EmittedFileKind::Spec);
        }

        // Every operation was skipped: a bare exit 0 would otherwise read as
        // "imported successfully", so make the empty outcome explicit.
        if ($files === [] && $unmapped !== []) {
            $warnings[] = 'every operation was unmappable — no specs were emitted.';
        }

        return new ImportPlan($files, $unmapped, $warnings);
    }

    /**
     * @param  list<EmittedFile>   $planned
     * @return list<string>        Relative paths actually written (skipped files excluded).
     */
    private function writeSpecs(array $planned, FileWriter $writer, SnapshotCollector $collector, bool $force): array
    {
        $written = [];
        foreach ($planned as $file) {
            $before = $collector->captureBefore($file);
            $outcome = $writer->write($file, $force);
            $collector->record($file, $outcome, $before);

            if ($outcome->status !== WriteStatus::Skipped) {
                $written[] = $outcome->relativePath;
            }
        }

        return $written;
    }

    /**
     * @param  list<string>                       $writtenSpecs
     * @return list<string>                       Relative paths of scaffold-emitted files.
     */
    private function runScaffold(array $writtenSpecs, OpenApiImportOptions $options, FileWriter $writer, SnapshotCollector $collector): array
    {
        $scaffolded = [];

        foreach ($writtenSpecs as $relative) {
            $absolute = $options->projectRoot . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
            $spec = $this->specParser->parseFile($absolute);
            $this->specValidator->assertValid($spec);

            foreach ($this->emissionPlan->build($spec) as $file) {
                $before = $collector->captureBefore($file);
                $outcome = $writer->write($file, $options->force);
                $collector->record($file, $outcome, $before);

                if ($outcome->status !== WriteStatus::Skipped) {
                    $scaffolded[] = $outcome->relativePath;
                }
            }
        }

        return $scaffolded;
    }

    /**
     * Best-effort rollback: delete the specs the import just wrote so the
     * tree is left in (close to) its pre-import state. Files that no longer
     * match the just-written content are reported in `warnings` upstream.
     *
     * @param  list<string>  $writtenSpecs
     * @return list<string>
     */
    private function rollback(array $writtenSpecs, string $projectRoot): array
    {
        $deleted = [];
        foreach ($writtenSpecs as $relative) {
            $absolute = $projectRoot . DIRECTORY_SEPARATOR . ltrim($relative, '/\\');
            if (is_file($absolute) && @unlink($absolute)) {
                $deleted[] = $relative;
            }
        }

        return $deleted;
    }

    /**
     * @return list<string>
     */
    private function initialWarnings(OpenApiImportOptions $options): array
    {
        $warnings = [];
        if ($options->queue !== null) {
            $warnings[] = \sprintf(
                "queue=%s flag recorded; x-altair-queue blocks in the OpenAPI source still round-trip into spec.queue regardless of the flag.",
                $options->queue,
            );
        }

        return $warnings;
    }

    /**
     * @return list<string>
     */
    private function unknownExtensionWarnings(OpenApiDocument $document): array
    {
        $warnings = [];
        $seen = [];
        foreach ($document->operations as $operation) {
            foreach ($operation->extensions as $key => $_value) {
                if (!\is_string($key)) {
                    continue;
                }

                if (isset($seen[$key])) {
                    continue;
                }

                if (\in_array($key, self::KNOWN_OPERATION_EXTENSIONS, true)) {
                    continue;
                }

                $seen[$key] = true;
                $warnings[] = \sprintf(
                    "unknown extension '%s' carried through unchanged; not interpreted by this release.",
                    $key,
                );
            }
        }

        return $warnings;
    }

    private function recordJournal(OpenApiImportOptions $options, string $sourceContents, SnapshotCollector $collector): ?string
    {
        if (!$this->journal instanceof Journal) {
            return null;
        }

        try {
            $entry = JournalEntry::openApiImport(
                command: $this->commandString($options),
                documentPath: $options->documentPath,
                documentContent: $sourceContents,
                scaffoldVersion: JournalEntry::VERSION,
                filesCreated: $collector->created(),
                filesModified: $collector->modified(),
                filesSkipped: $collector->skipped(),
            );

            return $this->journal->record($entry);
        } catch (Throwable) {
            // Journaling is best-effort.
            return null;
        }
    }

    private function recordEvent(
        OpenApiImportOptions $options,
        EventStatus $status,
        int $durationMs,
        int $createdCount,
        ?string $error,
    ): ?string {
        if (!$this->events instanceof RecorderInterface) {
            return null;
        }

        try {
            $event = RecorderEvent::create(
                actor: Actor::Cli,
                command: $this->commandString($options),
                kind: EventKind::OpenapiImport,
                status: $status,
                durationMs: $durationMs,
                changes: $createdCount > 0 ? new Changes(['created' => array_fill(0, $createdCount, '*')]) : null,
                error: $error,
            );
            $this->events->record($event);

            return $event->id;
        } catch (Throwable) {
            // Event recording is best-effort.
            return null;
        }
    }

    private function commandString(OpenApiImportOptions $options): string
    {
        $parts = ['bin/altair openapi:import', $options->documentPath];
        if ($options->scaffold) {
            $parts[] = '--scaffold';
        }

        if ($options->persistence !== null) {
            $parts[] = '--persistence=' . $options->persistence;
        }

        if ($options->queue !== null) {
            $parts[] = '--queue=' . $options->queue;
        }

        if ($options->force) {
            $parts[] = '--force';
        }

        if ($options->skipUnmappable) {
            $parts[] = '--skip-unmappable';
        }

        return implode(' ', $parts);
    }

    private function readDocument(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return $contents === false ? null : $contents;
    }

    /**
     * @param list<string> $coverageWarnings
     */
    private function dryRunReceipt(OpenApiImportOptions $options, ImportPlan $plan, OpenApiDocument $document, array $coverageWarnings): ImportReceipt
    {
        return new ImportReceipt(
            ok: true,
            input: $options->documentPath,
            specsWritten: \array_values(array_map(static fn(EmittedFile $f): string => $f->relativePath, $plan->files)),
            scaffoldRequested: $options->scaffold,
            scaffolded: [],
            rolledBack: [],
            unmapped: $plan->unmapped,
            warnings: [...$this->initialWarnings($options), ...$this->unknownExtensionWarnings($document), ...$coverageWarnings, ...$plan->warnings],
            journalId: null,
            eventId: null,
            error: null,
        );
    }

    /**
     * Decodes the raw document, mirroring {@see OpenApiParser::parseYaml}'s
     * error handling so an invalid document fails the same way whether or not
     * it carries external refs.
     *
     * @return array<string, mixed>
     */
    private function decodeDocument(string $sourceContents): array
    {
        try {
            $decoded = Yaml::parse($sourceContents);
        } catch (ParseException $parseException) {
            throw new SdkException('Invalid OpenAPI YAML: ' . $parseException->getMessage(), 0, $parseException);
        }

        if (!\is_array($decoded)) {
            throw new SdkException('OpenAPI document must be a YAML map at the top level.');
        }

        return $decoded;
    }

    /**
     * Directory the document's external `$ref`s resolve against, with the
     * document path canonicalized first so a symlinked spec still resolves its
     * siblings against their real directory.
     */
    private function baseDir(string $documentPath): string
    {
        $real = realpath($documentPath);

        return \dirname($real !== false ? $real : $documentPath);
    }

    /**
     * @param list<array{pointer: string, message: string}> $unmapped
     * @param list<string>                                  $writtenSpecs
     * @param list<string>                                  $rolledBack
     * @param list<string>                                  $warnings
     */
    private function failure(
        OpenApiImportOptions $options,
        string $message,
        float $startedAt,
        array $unmapped = [],
        array $writtenSpecs = [],
        array $rolledBack = [],
        array $warnings = [],
    ): ImportReceipt {
        $eventId = $this->recordEvent(
            options: $options,
            status: EventStatus::Fail,
            durationMs: (int) ((\microtime(true) - $startedAt) * 1000),
            createdCount: 0,
            error: $message,
        );

        return new ImportReceipt(
            ok: false,
            input: $options->documentPath,
            specsWritten: $writtenSpecs,
            scaffoldRequested: $options->scaffold,
            scaffolded: [],
            rolledBack: $rolledBack,
            unmapped: $unmapped,
            warnings: $warnings,
            journalId: null,
            eventId: $eventId,
            error: $message,
        );
    }
}
