<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Index\Storage;

use Altair\Index\Model\Symbol;
use Altair\Index\Model\SymbolKind;
use Altair\Index\Model\Usage;
use Altair\Index\Model\UsageKind;

/**
 * Rehydrates {@see Symbol} and {@see Usage} value objects from raw database
 * rows. PDO SQLite returns every column as a string, so integers and booleans
 * are coerced here.
 */
final class RowMapper
{
    /**
     * @param array<string, mixed> $row
     */
    public static function symbol(array $row): Symbol
    {
        return new Symbol(
            (string) $row['fqn'],
            SymbolKind::from((string) $row['kind']),
            (string) $row['file'],
            (int) $row['line'],
            $row['visibility'] === null ? null : (string) $row['visibility'],
            (bool) $row['is_readonly'],
            (bool) $row['is_static'],
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function usage(array $row): Usage
    {
        return new Usage(
            (string) $row['target_fqn'],
            (string) $row['used_in_file'],
            (int) $row['used_in_line'],
            UsageKind::from((string) $row['usage_kind']),
            $row['context'] === null ? null : (string) $row['context'],
        );
    }
}
