<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety\Check;

use Altair\MigrationIntelligence\Intent\ChangeColumnIntent;
use Altair\MigrationIntelligence\Intent\IntentInterface;
use Altair\MigrationIntelligence\Safety\RowCounter;
use Altair\MigrationIntelligence\Safety\SafetyCheckInterface;
use Altair\MigrationIntelligence\Safety\SafetyFinding;
use Altair\MigrationIntelligence\Schema\ColumnType;
use Override;

/**
 * For an incompatible type change, samples existing values and checks whether
 * they would survive the cast. A PHP-side heuristic (not a DB-side trial cast)
 * — accurate enough to warn before a destructive change.
 */
final readonly class TypeCastSafetyCheck implements SafetyCheckInterface
{
    private const string NAME = 'type_cast';

    public function __construct(private int $sampleSize = 100) {}

    #[Override]
    public function check(IntentInterface $intent, RowCounter $rows): array
    {
        if (!$intent instanceof ChangeColumnIntent || !$intent->incompatible || !$intent->typeChanged()) {
            return [];
        }

        $values = $rows->sample($intent->table, $intent->after->name, $this->sampleSize);
        $failures = 0;
        foreach ($values as $value) {
            if (!$this->castsCleanly($value, $intent->after->type)) {
                ++$failures;
            }
        }

        if ($failures === 0) {
            return [SafetyFinding::warn(
                \sprintf(
                    "Type change on '%s' (%s -> %s) is incompatible; the sampled %d value(s) cast cleanly, "
                    . 'but verify the full table before applying.',
                    $intent->after->name,
                    $intent->before->type,
                    $intent->after->type,
                    \count($values),
                ),
                self::NAME,
            )];
        }

        return [SafetyFinding::error(
            \sprintf(
                "Type change on '%s' (%s -> %s) would lose data: %d of %d sampled value(s) do not cast to %s.",
                $intent->after->name,
                $intent->before->type,
                $intent->after->type,
                $failures,
                \count($values),
                $intent->after->type,
            ),
            self::NAME,
        )];
    }

    private function castsCleanly(mixed $value, string $type): bool
    {
        if ($value === null) {
            return true;
        }

        $string = (string) $value;

        return match ($type) {
            ColumnType::INTEGER, ColumnType::BIG_INTEGER => preg_match('/^-?\d+$/', $string) === 1,
            ColumnType::FLOAT, ColumnType::DECIMAL => is_numeric($string),
            ColumnType::BOOLEAN => \in_array(strtolower($string), ['0', '1', 'true', 'false', 't', 'f'], true),
            default => true,
        };
    }
}
