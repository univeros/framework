<?php

namespace Altair\Data\Traits;

use Altair\Data\Contracts\ArrayableInterface;
use Altair\Data\Exception\InvalidArgumentException;

trait AttributesAwareTrait
{

    /**
     * Checks whether a property exists in the instance.
     *
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key): bool
    {
        return property_exists($this, $key);
    }

    /**
     * Returns a property value.
     *
     * @param string $key
     *
     * @throws InvalidArgumentException if the property is not found
     * @return mixed
     */
    public function get(string $key)
    {
        if (!$this->has($key)) {
            throw new InvalidArgumentException(sprintf('"%s" attribute not found.', $key));
        }

        return $this->{$key};
    }

    /**
     * Returns a copy of the instance with the new data.
     *
     * @param array $data
     *
     * @return mixed
     */
    public function withData(array $data)
    {
        $cloned = clone $this;

        $data = array_intersect_key($data, get_object_vars($this));

        foreach ($data as $key => $value) {
            $cloned->{$key} = $value;
        }

        return $cloned;
    }

    /**
     * @inheritdoc
     */
    public function toArray(): array
    {
        return array_map(
            static function ($value) {
                if ($value instanceof ArrayableInterface) {
                    $value = $value->toArray();
                }

                return $value;
            },
            get_object_vars($this)
        );
    }
}
