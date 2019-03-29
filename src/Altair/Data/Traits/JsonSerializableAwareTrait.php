<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Data\Traits;

use Altair\Data\Contracts\ArrayableInterface;

trait JsonSerializableAwareTrait
{
    /**
     * @see ArrayableInterface::toArray()
     *
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * @see ArrayableInterface::toArray()
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
