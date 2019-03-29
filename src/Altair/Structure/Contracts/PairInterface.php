<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Contracts;

interface PairInterface
{
    /**
     * Check if keys are equal.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function equalsKey($key): bool;

    /**
     * Returns a copy of the Pair.
     *
     * @return PairInterface
     */
    public function copy(): PairInterface;

    /**
     * Returns pair as array.
     *
     * @return array
     */
    public function toArray(): array;
}
