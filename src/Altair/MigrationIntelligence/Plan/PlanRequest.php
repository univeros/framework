<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Plan;

use Altair\MigrationIntelligence\Schema\TableShape;
use Cycle\Database\DatabaseInterface;

/**
 * Everything {@see PlanBuilder} needs to turn a current/desired pair of table
 * shapes into a migration plan.
 *
 * `database` is optional: when present the safety checks run against it; when
 * null (e.g. a spec-vs-spec diff) they are skipped. `timestamp` is injectable
 * so tests can pin deterministic filenames/class names.
 */
final readonly class PlanRequest
{
    /**
     * @param array<string, string> $renames old column name => new name
     */
    public function __construct(
        public TableShape $from,
        public TableShape $to,
        public string $driver = 'postgres',
        public array $renames = [],
        public ?DatabaseInterface $database = null,
        public ?int $timestamp = null,
        public bool $force = false,
    ) {}

    public function timestamp(): int
    {
        return $this->timestamp ?? time();
    }
}
