<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Plan;

/**
 * Names alter-migrations the way Cycle's FileRepository expects: a
 * `<Ymd.His>_<chunk>_<name>.php` filename (dot between date and time) and a
 * matching `M<YmdHis><Name>` class. Two-phase plans share a timestamp and
 * disambiguate via the chunk index + a `Phase1`/`Phase2` class suffix.
 */
final readonly class PlanNaming
{
    public function __construct(private string $migrationsRelativeRoot = 'database/migrations') {}

    public function name(string $table, string $phase = ''): string
    {
        return 'alter_' . $this->snake($table) . ($phase === '' ? '' : '_' . $phase);
    }

    public function className(string $table, int $timestamp, string $phase = ''): string
    {
        return 'M' . gmdate('YmdHis', $timestamp) . 'Alter' . $this->camel($table) . ucfirst($phase);
    }

    public function path(string $table, int $timestamp, int $chunk, string $phase = ''): string
    {
        return \sprintf(
            '%s/%s_%d_%s.php',
            $this->migrationsRelativeRoot,
            gmdate('Ymd.His', $timestamp),
            $chunk,
            $this->name($table, $phase),
        );
    }

    private function snake(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9_]+/i', '_', $value));
    }

    private function camel(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', strtolower($value))));
    }
}
