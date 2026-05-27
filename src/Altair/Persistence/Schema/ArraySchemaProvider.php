<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Schema;

use Override;

/**
 * Trivial provider that hands back the schema array it was constructed with.
 *
 * Useful for tests and for apps that pre-compile their schema during build.
 *
 * @phpstan-import-type CycleSchema from SchemaProviderInterface
 */
final readonly class ArraySchemaProvider implements SchemaProviderInterface
{
    /**
     * @param CycleSchema $schema
     */
    public function __construct(private array $schema) {}

    #[Override]
    public function schema(): array
    {
        return $this->schema;
    }
}
