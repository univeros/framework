<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Diff;

use Altair\MigrationIntelligence\Schema\ColumnShape;
use Altair\MigrationIntelligence\Schema\ColumnType;

/**
 * Decides whether changing a column from one type to another is a *safe
 * widening* (existing data always casts) or *incompatible* (data may be lost
 * or rejected). Incompatible changes trigger a two-phase migration plan.
 */
final class TypeCompatibility
{
    /**
     * Pairs that always cast cleanly in every supported driver.
     *
     * @var array<string, list<string>>
     */
    private const array SAFE_WIDENINGS = [
        ColumnType::INTEGER => [ColumnType::BIG_INTEGER, ColumnType::STRING, ColumnType::TEXT, ColumnType::DECIMAL, ColumnType::FLOAT],
        ColumnType::BIG_INTEGER => [ColumnType::STRING, ColumnType::TEXT, ColumnType::DECIMAL],
        ColumnType::FLOAT => [ColumnType::STRING, ColumnType::TEXT, ColumnType::DECIMAL],
        ColumnType::DECIMAL => [ColumnType::STRING, ColumnType::TEXT],
        ColumnType::BOOLEAN => [ColumnType::INTEGER, ColumnType::STRING, ColumnType::TEXT],
        ColumnType::STRING => [ColumnType::TEXT],
        ColumnType::DATE => [ColumnType::DATETIME, ColumnType::TIMESTAMP, ColumnType::STRING, ColumnType::TEXT],
        ColumnType::TIME => [ColumnType::STRING, ColumnType::TEXT],
        ColumnType::DATETIME => [ColumnType::TIMESTAMP, ColumnType::STRING, ColumnType::TEXT],
        ColumnType::TIMESTAMP => [ColumnType::DATETIME, ColumnType::STRING, ColumnType::TEXT],
        ColumnType::UUID => [ColumnType::STRING, ColumnType::TEXT],
        ColumnType::ENUM => [ColumnType::STRING, ColumnType::TEXT],
        ColumnType::JSON => [ColumnType::TEXT, ColumnType::STRING],
    ];

    public static function isSafe(ColumnShape $before, ColumnShape $after): bool
    {
        if ($before->type === $after->type) {
            // Same type: shrinking a string's size can truncate; growing is safe.
            return self::sizeIsSafe($before, $after);
        }

        return \in_array($after->type, self::SAFE_WIDENINGS[$before->type] ?? [], true);
    }

    public static function isIncompatible(ColumnShape $before, ColumnShape $after): bool
    {
        return !self::isSafe($before, $after);
    }

    private static function sizeIsSafe(ColumnShape $before, ColumnShape $after): bool
    {
        if ($before->size === null || $after->size === null) {
            return true;
        }

        return $after->size >= $before->size;
    }
}
