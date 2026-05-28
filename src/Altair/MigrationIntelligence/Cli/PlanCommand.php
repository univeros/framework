<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Cli;

use Altair\Cli\Attribute\Argument;
use Altair\Cli\Attribute\Command;
use Altair\Cli\Attribute\Option;
use Altair\Events\Actor;
use Altair\Events\Contracts\RecorderInterface;
use Altair\Events\Event;
use Altair\Events\EventKind;
use Altair\Events\EventStatus;
use Altair\MigrationIntelligence\Db\DatabaseProbe;
use Altair\MigrationIntelligence\Emitter\CycleMigrationEmitter;
use Altair\MigrationIntelligence\Exception\MigrationIntelligenceException;
use Altair\MigrationIntelligence\Exception\TableMissing;
use Altair\MigrationIntelligence\Output\RendererRegistry;
use Altair\MigrationIntelligence\Plan\PlanBuilder;
use Altair\MigrationIntelligence\Plan\PlanRequest;
use Altair\MigrationIntelligence\Plan\PlanSet;
use Altair\MigrationIntelligence\Reader\DbSchemaReader;
use Altair\MigrationIntelligence\Reader\EntitySchemaReader;
use Altair\MigrationIntelligence\Reader\SpecSchemaReader;
use Altair\MigrationIntelligence\Schema\TableShape;
use Altair\Scaffold\Spec\Ast\PersistenceSpec;
use Altair\Scaffold\Spec\Ast\Spec;
use Altair\Scaffold\Spec\SpecLoader;
use Cycle\Database\DatabaseInterface;
use Throwable;

/**
 * `bin/altair db:migration-plan` — propose a safe Cycle migration from a spec
 * or entity diff.
 *
 * Three diff sources:
 *   - a spec's persistence block vs. the live database (default);
 *   - `--from-entity=FQCN` vs. the live database;
 *   - `--from-spec=a.yaml --to-spec=b.yaml` for a spec-vs-spec diff (no DB).
 *
 * Prints the plan by default; `--output=DIR` writes the migration file(s).
 * Exit code is 1 when a safety check raises an error (CI gate), 2 on a usage
 * error, otherwise 0.
 */
#[Command(
    name: 'db:migration-plan',
    description: 'Propose a safe migration from a spec/entity diff with read-only safety checks.',
)]
final readonly class PlanCommand
{
    public function __construct(
        private SpecLoader $specs = new SpecLoader(),
        private SpecSchemaReader $specReader = new SpecSchemaReader(),
        private EntitySchemaReader $entityReader = new EntitySchemaReader(),
        private DbSchemaReader $dbReader = new DbSchemaReader(),
        private PlanBuilder $builder = new PlanBuilder(),
        private CycleMigrationEmitter $emitter = new CycleMigrationEmitter(),
        private ?RendererRegistry $renderers = null,
        private ?DatabaseProbe $probe = null,
        private ?RecorderInterface $recorder = null,
    ) {}

    public function __invoke(
        #[Argument(description: 'Spec YAML whose persistence block is the desired shape (diffed against the live DB).')]
        ?string $spec = null,
        #[Option(description: 'FQCN of a Cycle entity to diff against the live DB.', name: 'from-entity')]
        ?string $fromEntity = null,
        #[Option(description: 'Older spec for a spec-vs-spec diff (no DB).', name: 'from-spec')]
        ?string $fromSpec = null,
        #[Option(description: 'Newer spec for a spec-vs-spec diff (no DB).', name: 'to-spec')]
        ?string $toSpec = null,
        #[Option(description: 'Preview SQL dialect: postgres, mysql, sqlite. Defaults to the connected DB, else postgres.')]
        ?string $driver = null,
        #[Option(description: 'Declared column renames, e.g. "old:new,old2:new2".')]
        ?string $rename = null,
        #[Option(description: 'Write the migration file(s) into this directory instead of printing only.')]
        ?string $output = null,
        #[Option(description: 'Output format: human or json.')]
        string $format = 'human',
        #[Option(description: 'Proceed with destructive drops that still hold data.')]
        bool $force = false,
        #[Option(description: 'Skip the read-only database safety checks.', name: 'skip-safety')]
        bool $skipSafety = false,
    ): int {
        $renderers = $this->renderers ?? RendererRegistry::default();

        try {
            $renderer = $renderers->get($format);
            $request = $this->resolveRequest($spec, $fromEntity, $fromSpec, $toSpec, $driver, $rename, $force, $skipSafety);
        } catch (TableMissing $tableMissing) {
            echo $tableMissing->getMessage(), "\n";

            return 0;
        } catch (MigrationIntelligenceException $exception) {
            echo $exception->getMessage(), "\n";

            return 2;
        }

        $plan = $this->builder->build($request);
        echo $renderer->render($plan);

        if ($output !== null && !$plan->isEmpty()) {
            $this->write($plan, $output);
        }

        return $plan->exitCode();
    }

    private function resolveRequest(
        ?string $spec,
        ?string $fromEntity,
        ?string $fromSpec,
        ?string $toSpec,
        ?string $driver,
        ?string $rename,
        bool $force,
        bool $skipSafety,
    ): PlanRequest {
        $renames = $this->parseRenames($rename);

        if ($fromSpec !== null && $toSpec !== null) {
            return new PlanRequest(
                from: $this->specReader->fromSpec($this->loadPersistence($fromSpec)),
                to: $this->specReader->fromSpec($this->loadPersistence($toSpec)),
                driver: $driver ?? 'postgres',
                renames: $renames,
            );
        }

        $to = $this->resolveDesired($spec, $fromEntity);
        $database = ($this->probe ?? DatabaseProbe::fromEnvironment())->connect();
        if (!$database instanceof DatabaseInterface) {
            throw new MigrationIntelligenceException(
                'No database reachable. Set DB_* env, or use --from-spec/--to-spec for a spec-vs-spec diff.',
            );
        }

        $current = $this->dbReader->read($database, $to->name);
        if (!$current instanceof TableShape) {
            throw new TableMissing(\sprintf(
                "Table '%s' does not exist; use `bin/altair spec:scaffold` to create it.",
                $to->name,
            ));
        }

        return new PlanRequest(
            from: $current,
            to: $to,
            driver: $driver ?? strtolower($database->getDriver()->getType()),
            renames: $renames,
            database: $skipSafety ? null : $database,
            force: $force,
        );
    }

    private function resolveDesired(?string $spec, ?string $fromEntity): TableShape
    {
        if ($fromEntity !== null) {
            return $this->entityReader->read($fromEntity)
                ?? throw new MigrationIntelligenceException(
                    \sprintf("'%s' is not a Cycle-annotated entity class.", $fromEntity),
                );
        }

        if ($spec !== null) {
            return $this->specReader->fromSpec($this->loadPersistence($spec));
        }

        throw new MigrationIntelligenceException(
            'Provide a spec path, --from-entity=FQCN, or --from-spec + --to-spec.',
        );
    }

    private function loadPersistence(string $path): PersistenceSpec
    {
        $specs = $this->specs->load($path, validate: false);
        $first = $specs[0] ?? null;
        if (!$first instanceof Spec || !$first->persistence instanceof PersistenceSpec) {
            throw new MigrationIntelligenceException(\sprintf("Spec '%s' has no persistence block.", $path));
        }

        return $first->persistence;
    }

    /**
     * @return array<string, string>
     */
    private function parseRenames(?string $rename): array
    {
        if ($rename === null || trim($rename) === '') {
            return [];
        }

        $renames = [];
        foreach (explode(',', $rename) as $pair) {
            $parts = explode(':', trim($pair), 2);
            if (\count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
                $renames[trim($parts[0])] = trim($parts[1]);
            }
        }

        return $renames;
    }

    private function write(PlanSet $plan, string $directory): void
    {
        $target = rtrim($directory, '/\\');
        if (!is_dir($target)) {
            mkdir($target, 0o755, true);
        }

        $written = [];
        foreach ($plan->migrations as $migration) {
            if ($migration->isEmpty()) {
                continue;
            }

            $path = $target . DIRECTORY_SEPARATOR . basename($migration->filename);
            file_put_contents($path, $this->emitter->emit($migration));
            $written[] = $path;
            echo 'wrote ', $path, "\n";
        }

        $this->record($plan, $written);
    }

    /**
     * @param list<string> $written
     */
    private function record(PlanSet $plan, array $written): void
    {
        if (!$this->recorder instanceof RecorderInterface || $written === []) {
            return;
        }

        try {
            $this->recorder->record(Event::create(
                actor: Actor::Cli,
                command: 'db:migration-plan',
                kind: EventKind::Migration,
                status: $plan->safety->hasErrors() ? EventStatus::Partial : EventStatus::Ok,
                durationMs: 0,
                extra: ['table' => $plan->table, 'files' => $written, 'two_phase' => $plan->twoPhase],
            ));
        } catch (Throwable) {
            // Event recording is best-effort.
        }
    }
}
