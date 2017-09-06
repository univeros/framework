<?php

namespace Altair\Data\Traits;

use Altair\Data\Exception\RuntimeException;

trait ImmutableAttributesAwareTrait
{
    use AttributesAwareTrait;

    /**
     * AttributesAwareTrait constructor. Allow usage of array to configure an object.
     *
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        if (!empty($data)) {
            $data = array_intersect_key($data, get_object_vars($this));
            foreach ($data as $key => $value) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Checks if a property is defined in the object
     *
     * This will return `false` if the value is `null`! To check if a value
     * exists in the object, use `has()`.
     *
     * @param string $key
     *
     * @return boolean
     */
    public function __isset($key)
    {
        return isset($this->{$key});
    }

    /**
     * Allow read access to immutable object properties
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Protects against the object being modified
     *
     * @param string $key
     * @param mixed $value
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public function __set($key, $value)
    {
        throw new RuntimeException(
            sprintf(
                'Modification of immutable object `%s` is not allowed',
                get_class($this)
            )
        );
    }

    /**
     * Protects against the object being modified
     *
     * @param string $key
     *
     * @return void
     *
     * @throws RuntimeException
     */
    public function __unset($key)
    {
        throw new RuntimeException(
            sprintf(
                'Modification of immutable object `%s` is not allowed',
                get_class($this)
            )
        );
    }
}
