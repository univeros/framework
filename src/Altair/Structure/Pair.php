<?php

namespace Altair\Structure;

use Altair\Structure\Contracts\HashableInterface;
use Altair\Structure\Contracts\PairInterface;
use JsonSerializable;
use OutOfBoundsException;

/**
 * A pair which represents a key, and an associated value.
 *
 */
class Pair implements PairInterface, JsonSerializable
{
    /**
     * @param mixed $key The pair's key
     */
    public $key;
    /**
     * @param mixed $value The pair's value
     */
    public $value;

    /**
     * Constructor.
     *
     * @param mixed $key
     * @param mixed $value
     */
    public function __construct($key = null, $value = null)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * This allows unset($pair->key) to not completely remove the property,
     * but be set to null instead.
     *
     * @param mixed $name
     *
     * @return mixed|null
     */
    public function __get($name)
    {
        if ($name === 'key' || $name === 'value') {
            $this->$name = null;

            return;
        }
        throw new OutOfBoundsException();
    }

    /**
     * {@inheritdoc}
     */
    public function equalsKey($key): bool
    {
        if (is_object($this->key) && $this->key instanceof HashableInterface) {
            return get_class($this->key) === get_class($key) && $this->key->equals($key);
        }

        return $this->key === $key;
    }

    /**
     * Returns a copy of the Pair.
     *
     * @return PairInterface
     */
    public function copy(): PairInterface
    {
        return new static($this->key, $this->value);
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'value' => $this->value];
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Debug Info.
     *
     * @return array
     */
    public function __debugInfo()
    {
        return $this->toArray();
    }

    /**
     * To String.
     */
    public function __toString()
    {
        return 'object(' . get_class($this) . ')';
    }
}
