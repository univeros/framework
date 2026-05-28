<?php

declare(strict_types=1);

/*
 * This file is part of the univeros/framework
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Altair\Structure\Traits;

use Altair\Structure\Contracts\CapacityInterface;
use Altair\Structure\Contracts\CollectionInterface;
use JsonSerializable;
use ReturnTypeWillChange;
use Traversable;

/**
 * Common to structures that implement the base collection interface.
 *
 * @template TKey
 * @template TValue
 */
trait CollectionTrait
{
    /**
     * Internal storage for sequence-backed collections, keyed by position.
     *
     * Key-backed (Map) and adapter (Set, Stack, Queue) collections redeclare
     * this property with a narrower @var in their own class because they store
     * PairInterface instances or a delegate collection object instead. For the
     * adapters the empty-array default cannot match their narrowed delegate
     * type, but it is always overwritten in the constructor before use; PHPStan
     * evaluates the default per using-class, so the check is suppressed here.
     *
     * @var array<int, TValue>
     *
     * @phpstan-ignore property.defaultValue
     */
    protected $internal = [];

    /**
     * Creates an instance using the values of an array or Traversable object.
     *
     * @param array<array-key, TValue>|Traversable<TKey, TValue>|CollectionInterface<TKey, TValue>|null $values
     */
    public function __construct($values = [])
    {
        if (\func_num_args() !== 0) {
            $this->pushAll($this->normalizeItems($values));
        }
    }

    /**
     * Invoked when calling var_dump.
     *
     * @return array<array-key, TValue>
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
        return 'object(' . $this::class . ')';
    }

    public function clear(): static
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
        return \count($this) === 0;
    }

    /**
     * Returns a representation that can be natively converted to JSON, which is
     * called when invoking json_encode.
     *
     * @return mixed
     *
     * @see JsonSerializable
     */
    #[ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Creates a shallow copy of the collection.
     *
     * @return static a shallow copy of the collection.
     */
    public function copy(): static
    {
        return new static($this);
    }

    /**
     * Returns the size of the collection.
     */
    #[ReturnTypeWillChange]
    public function count(): int
    {
        return \count($this->internal);
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
     * @return array<array-key, TValue>
     */
    abstract public function toArray(): array;

    /**
     * Pushes all values of either an array or traversable object.
     *
     * @param iterable<array-key, TValue> $values
     */
    protected function pushAll(mixed $values): void
    {
        foreach ($values as $value) {
            // Adapter collections (Set, Stack, Queue) narrow $internal to a
            // delegate object and never call pushAll, so the array-append and
            // adjustCapacity() call below are unreachable in those contexts.
            // @phpstan-ignore offsetAssign.dimType
            $this->internal[] = $value;
        }

        if ($this instanceof CapacityInterface) {
            // @phpstan-ignore method.notFound
            $this->adjustCapacity();
        }
    }

    /**
     * Results array of items from Collections or array.
     *
     * @return array<array-key, TValue>
     */
    protected function normalizeItems(mixed $items): array
    {
        if (\is_array($items)) {
            return $items;
        }

        if ($items instanceof CollectionInterface) {
            return $items->toArray();
        }

        if ($items instanceof JsonSerializable) {
            return $items->jsonSerialize();
        }

        if ($items instanceof Traversable) {
            return iterator_to_array($items);
        }

        return (array) $items;
    }
}
