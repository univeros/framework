<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Schema;

/**
 * The canonical column-type vocabulary every reader normalizes to, plus the
 * mappings from spec types and Cycle abstract types.
 *
 * Keeping one vocabulary means the differ never reports a spurious type change
 * just because the spec said `int` and the DB reported `integer`.
 */
final class ColumnType
{
    public const string PRIMARY = 'primary';

    public const string BIG_PRIMARY = 'bigPrimary';

    public const string INTEGER = 'integer';

    public const string BIG_INTEGER = 'bigInteger';

    public const string STRING = 'string';

    public const string TEXT = 'text';

    public const string BOOLEAN = 'boolean';

    public const string FLOAT = 'float';

    public const string DECIMAL = 'decimal';

    public const string DATETIME = 'datetime';

    public const string DATE = 'date';

    public const string TIME = 'time';

    public const string TIMESTAMP = 'timestamp';

    public const string JSON = 'json';

    public const string UUID = 'uuid';

    public const string ENUM = 'enum';

    /**
     * Normalize a spec field type (e.g. "int", "bigint", "uuid") to canonical.
     */
    public static function fromSpec(string $type): string
    {
        return match (strtolower($type)) {
            'int', 'integer' => self::INTEGER,
            'bigint', 'biginteger' => self::BIG_INTEGER,
            'bool', 'boolean' => self::BOOLEAN,
            'double' => self::FLOAT,
            default => $type,
        };
    }

    /**
     * Normalize a Cycle abstract type (from {@see \Cycle\Database\ColumnInterface::getAbstractType()})
     * to canonical. Cycle has finer-grained integer variants; we collapse the
     * small/tiny ones onto `integer` so the diff stays readable.
     */
    public static function fromCycleAbstract(string $abstract): string
    {
        return match ($abstract) {
            'tinyInteger', 'smallInteger' => self::INTEGER,
            'longText', 'tinyText' => self::TEXT,
            'double' => self::FLOAT,
            'binary', 'tinyBinary', 'longBinary' => self::TEXT,
            // SQLite reports an unrecognized declared type (e.g. VARCHAR) as
            // `unknown`; it is almost always a string column.
            'unknown' => self::STRING,
            default => $abstract,
        };
    }

    /**
     * The type family for diffing. `string` and `text` collapse into one family
     * because SQLite's type affinity reports string columns as `text` on
     * introspection — keeping them separate would produce spurious type-change
     * diffs between a spec and a live SQLite schema.
     */
    public static function family(string $type): string
    {
        return match ($type) {
            self::STRING, self::TEXT => self::STRING,
            default => $type,
        };
    }

    /**
     * The Cycle migration column type to emit for a canonical type. UUIDs and
     * enums are stored as strings at the schema level.
     */
    public static function toCycle(string $canonical): string
    {
        return match ($canonical) {
            self::UUID, self::ENUM => self::STRING,
            default => $canonical,
        };
    }
}
