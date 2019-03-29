<?php declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Data\Contracts;

use Altair\Data\Exception\InvalidArgumentException;
use JsonSerializable;
use Serializable;

interface EntityInterface extends ArrayableInterface, JsonSerializable, Serializable
{
    /**
     * Checks whether a property exists in the instance.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Returns a property value.
     *
     * @param string $key
     *
     * @throws InvalidArgumentException if the property is not found
     * @return mixed
     */
    public function get(string $key);

    /**
     * Returns a copy of the instance with the new data.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function withData(array $data);
}
