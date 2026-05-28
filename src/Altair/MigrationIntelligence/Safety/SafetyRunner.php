<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety;

use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Safety\Check\DropColumnSafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\ForeignKeySafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\LargeTableSafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\NotNullSafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\TypeCastSafetyCheck;
use Altair\MigrationIntelligence\Safety\Check\UniqueSafetyCheck;
use Cycle\Database\DatabaseInterface;
use Throwable;

/**
 * Runs every registered safety check over a plan's intents against the live
 * database, read-only. Never throws: no database degrades to a skipped report;
 * an unreachable database is reported as skipped; a per-check query failure
 * becomes an informational finding rather than aborting the run.
 */
final readonly class SafetyRunner
{
    /**
     * @var list<SafetyCheckInterface>
     */
    private array $checks;

    public function __construct(SafetyCheckInterface ...$checks)
    {
        $this->checks = $checks === [] ? $this->defaultChecks() : array_values($checks);
    }

    public static function withDefaults(bool $force = false, int $largeTableThreshold = LargeTableSafetyCheck::DEFAULT_THRESHOLD): self
    {
        return new self(
            new NotNullSafetyCheck(),
            new UniqueSafetyCheck(),
            new ForeignKeySafetyCheck(),
            new TypeCastSafetyCheck(),
            new LargeTableSafetyCheck($largeTableThreshold),
            new DropColumnSafetyCheck($force),
        );
    }

    /**
     * @param list<IntentInterface> $intents
     */
    public function run(array $intents, ?DatabaseInterface $database): SafetyReport
    {
        if (!$database instanceof DatabaseInterface) {
            return SafetyReport::skipped(
                'No database configured; safety checks apply to spec-vs-database diffs (set DB_* env to enable).',
            );
        }

        try {
            $database->query('SELECT 1');
        } catch (Throwable $throwable) {
            return SafetyReport::skipped('Database unreachable: ' . $throwable->getMessage());
        }

        $rows = new RowCounter($database);
        $findings = [];
        foreach ($intents as $intent) {
            foreach ($this->checks as $check) {
                try {
                    foreach ($check->check($intent, $rows) as $finding) {
                        $findings[] = $finding;
                    }
                } catch (Throwable $throwable) {
                    $findings[] = SafetyFinding::info(
                        \sprintf("Could not evaluate a safety check on '%s': %s", $intent->table(), $throwable->getMessage()),
                        'runner',
                    );
                }
            }
        }

        return new SafetyReport($this->dedupe($findings));
    }

    /**
     * @return list<SafetyCheckInterface>
     */
    private function defaultChecks(): array
    {
        return [
            new NotNullSafetyCheck(),
            new UniqueSafetyCheck(),
            new ForeignKeySafetyCheck(),
            new TypeCastSafetyCheck(),
            new LargeTableSafetyCheck(),
            new DropColumnSafetyCheck(),
        ];
    }

    /**
     * @param list<SafetyFinding> $findings
     *
     * @return list<SafetyFinding>
     */
    private function dedupe(array $findings): array
    {
        $seen = [];
        $unique = [];
        foreach ($findings as $finding) {
            $key = $finding->severity->value . '|' . $finding->check . '|' . $finding->message;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $finding;
        }

        return $unique;
    }
}
