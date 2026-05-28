<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Contracts;

/**
 * @template TKey
 * @template TValue
 */
interface PairInterface
{
    /**
     * Check if keys are equal.
     *
     * @param TKey $key
     */
    public function equalsKey(mixed $key): bool;

    /**
     * Returns a copy of the Pair.
     *
     * @return PairInterface<TKey, TValue>
     */
    public function copy(): PairInterface;

    /**
     * Returns pair as array.
     *
     * @return array{key: TKey, value: TValue}
     */
    public function toArray(): array;
}
