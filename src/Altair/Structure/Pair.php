<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure;

use Altair\Structure\Contracts\HashableInterface;
use Altair\Structure\Contracts\PairInterface;
use JsonSerializable;
use OutOfBoundsException;
use Override;
use ReturnTypeWillChange;
use Stringable;

/**
 * A pair which represents a key, and an associated value.
 *
 */
class Pair implements PairInterface, JsonSerializable, Stringable
{
    /**
     * Constructor.
     */
    public function __construct(public mixed $key = null, public mixed $value = null) {}

    /**
     * This allows unset($pair->key) to not completely remove the property,
     * but be set to null instead.
     *
     *
     * @return mixed|null
     */
    public function __get(mixed $name)
    {
        if ($name === 'key' || $name === 'value') {
            $this->$name = null;

            return;
        }

        throw new OutOfBoundsException('Out of bounds');
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
    #[Override]
    public function __toString(): string
    {
        return 'object(' . static::class . ')';
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function equalsKey($key): bool
    {
        if ($this->key instanceof HashableInterface) {
            return $this->key::class === $key::class && $this->key->equals($key);
        }

        return $this->key === $key;
    }

    /**
     * Returns a copy of the Pair.
     */
    #[Override]
    public function copy(): PairInterface
    {
        return new static($this->key, $this->value);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function toArray(): array
    {
        return ['key' => $this->key, 'value' => $this->value];
    }

    /**
     * {@inheritDoc}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
