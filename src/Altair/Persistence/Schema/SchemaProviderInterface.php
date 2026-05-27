<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Schema;

/**
 * Supplies a compiled Cycle ORM schema array.
 *
 * Implementations may load a cached pre-compiled schema from disk, build
 * one from attribute discovery on the fly, or hand back a hard-coded
 * schema for tests.
 *
 * @phpstan-type CycleSchema array<string, array<int|string, mixed>>
 */
interface SchemaProviderInterface
{
    /**
     * @return CycleSchema
     */
    public function schema(): array;
}
