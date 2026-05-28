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
 * @template TKey
 * @template TValue
 *
 * @implements PairInterface<TKey, TValue>
 *
 * @phpstan-consistent-constructor
 */
class Pair implements PairInterface, JsonSerializable, Stringable
{
    /**
     * Constructor.
     *
     * @param TKey $key
     * @param TValue $value
     */
    public function __construct(
        /** @var TKey */
        public mixed $key = null,
        /** @var TValue */
        public mixed $value = null,
    ) {}

    /**
     * Resolves reads of $key/$value after they have been unset, returning null
     * rather than triggering an "undefined property" error. The property is not
     * re-initialised, so its declared TKey/TValue type is never violated; every
     * subsequent read routes back through this accessor and yields null.
     */
    public function __get(mixed $name): mixed
    {
        if ($name === 'key' || $name === 'value') {
            return null;
        }

        throw new OutOfBoundsException('Out of bounds');
    }

    /**
     * Debug Info.
     *
     * @return array{key: TKey, value: TValue}
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
     *
     * @param TKey $key
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
     *
     * @return static
     */
    #[Override]
    public function copy(): PairInterface
    {
        return new static($this->key, $this->value);
    }

    /**
     * {@inheritDoc}
     *
     * @return array{key: TKey, value: TValue}
     */
    #[Override]
    public function toArray(): array
    {
        return ['key' => $this->key, 'value' => $this->value];
    }

    /**
     * {@inheritDoc}
     *
     * @return array{key: TKey, value: TValue}
     */
    #[ReturnTypeWillChange]
    #[Override]
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
