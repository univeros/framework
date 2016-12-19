<?php

namespace Altair\Structure\Traits;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\CollectionInterface;
use JsonSerializable;
use Traversable;

/**
 * Common to structures that implement the base collection interface.
 */
trait CollectionTrait
{
    protected $internal = [];

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array|\Traversable|CollectionInterface|null $values
     */
    public function __construct($values = [])
    {
        if (func_num_args()) {
            $this->pushAll($this->normalizeItems($values));
        }
    }

    /**
     * Invoked when calling var_dump.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return $this->toArray();
    }

    /**
     * Returns a string representation of the collection, which is invoked when
     * the collection is converted to a string.
     */
    public function __toString()
    {
        return 'object(' . get_class($this) . ')';
    }

    /**
     * @return static
     */
    public function clear()
    {
        return new static();
    }

    /**
     * Returns whether the collection is empty.
     *
     * This should be equivalent to a count of zero, but is not required.
     * Implementations should define what empty means in their own context.
     *
     * @return bool whether the collection is empty.
     */
    public function isEmpty(): bool
    {
        return count($this) === 0;
    }

    /**
     * Returns a representation that can be natively converted to JSON, which is
     * called when invoking json_encode.
     *
     * @return mixed
     *
     * @see JsonSerializable
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Creates a shallow copy of the collection.
     *
     * @return static a shallow copy of the collection.
     */
    public function copy()
    {
        return new static($this);
    }

    /**
     * Returns the size of the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->internal);
    }

    /**
     * Returns the collection of items as JSON.
     *
     * @param int $options Bitmask of the different options of the json_encode function.
     * @param int $depth Sets the maximum depth. Must be greater than 0.
     *
     * @return string a JSON encoded string of the items or false on failure
     */
    public function toJson(int $options = 0, $depth = 512): string
    {
        return json_encode($this->toArray(), $options, $depth);
    }

    /**
     * Returns an array representation of the collection.
     *
     * The format of the returned array is implementation-dependent. Some implementations may throw an exception if an
     * array representation could not be created (for example when object are used as keys).
     *
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * Pushes all values of either an array or traversable object.
     * @param mixed $values
     */
    protected function pushAll($values)
    {
        foreach ($values as $value) {
            $this->internal[] = $value;
        }
        if ($this instanceof CapacityInterface) {
            $this->adjustCapacity();
        }
    }

    /**
     * Results array of items from Collections or array.
     *
     * @param  mixed $items
     *
     * @return array
     */
    protected function normalizeItems($items)
    {
        if (is_array($items)) {
            return $items;
        } elseif ($items instanceof CollectionInterface) {
            return $items->toArray();
        } elseif ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        } elseif ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }
}
