<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Persistence\Contracts;

use Altair\Data\Contracts\DataObjectInterface;
use Altair\Persistence\Exception\PersistenceExceptionInterface;

/**
 * Projects a storage row (or any associative array) into a typed, immutable
 * {@see DataObjectInterface}, coercing each value to the target property's
 * declared type.
 *
 * This is the read-model bridge: Cycle entities (and the Data package) stay
 * coercion-free; type coercion and nested-object composition live here, in
 * the persistence layer.
 */
interface HydratorInterface
{
    /**
     * @template T of DataObjectInterface
     *
     * @param class-string<T>      $dataObjectClass
     * @param array<string, mixed> $data
     *
     * @throws PersistenceExceptionInterface when a value cannot satisfy the declared property type
     *
     * @return T
     */
    public function hydrate(string $dataObjectClass, array $data): DataObjectInterface;

    /**
     * Hydrate a collection of rows into a list of Data objects.
     *
     * @template T of DataObjectInterface
     *
     * @param class-string<T>                  $dataObjectClass
     * @param iterable<array<string, mixed>>   $rows
     *
     * @throws PersistenceExceptionInterface when a value cannot satisfy the declared property type
     *
     * @return list<T>
     */
    public function hydrateMany(string $dataObjectClass, iterable $rows): array;
}
