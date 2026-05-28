<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\MigrationIntelligence\Safety;

use Altair\MigrationIntelligence\Exception\MigrationIntelligenceException;

/**
 * Validates and quotes SQL identifiers for the raw COUNT queries the safety
 * checks build. Identifiers must match a strict pattern before they are ever
 * interpolated into SQL — anything else is rejected, so a hostile column name
 * can never become an injection vector.
 */
final readonly class IdentifierQuoter
{
    private const string PATTERN = '/^[A-Za-z_]\w*$/';

    public function __construct(private string $quoteChar = '"') {}

    public static function forDriver(string $driverType): self
    {
        return new self(strtolower($driverType) === 'mysql' ? '`' : '"');
    }

    public function quote(string $identifier): string
    {
        if (preg_match(self::PATTERN, $identifier) !== 1) {
            throw new MigrationIntelligenceException(\sprintf(
                "Refusing to build SQL for unsafe identifier '%s'.",
                $identifier,
            ));
        }

        return $this->quoteChar . $identifier . $this->quoteChar;
    }
}
