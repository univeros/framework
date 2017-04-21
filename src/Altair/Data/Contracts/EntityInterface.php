<?php
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
