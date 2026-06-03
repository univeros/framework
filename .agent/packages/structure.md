# univeros/structure  ·  Altair\Structure

**Purpose:** Typed data structures — Map, Set, Vector, Deque, Queue, Stack, PriorityQueue — inspired by ext-ds, in pure PHP with no C extension.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `CapacityInterface` | `allocate(int)` | `mixed` | constants: `MIN_CAPACITY` |
|  | `capacity()` | `int` |  |
| `CollectionInterface` | `clear()` | `mixed` | extends `Countable`, `JsonSerializable`, `Traversable` |
|  | `copy()` | `mixed` |  |
|  | `count()` | `int` |  |
|  | `isEmpty()` | `bool` |  |
|  | `toArray()` | `array` |  |
|  | `toJson(int, mixed)` | `string` |  |
| `HashableInterface` | `equals(mixed)` | `bool` |  |
|  | `hash()` | `mixed` |  |
| `MapInterface` | `apply(callable)` | `MapInterface` | extends `CollectionInterface`, `Countable`, `JsonSerializable`, `Traversable` |
|  | `diff(MapInterface)` | `MapInterface` |  |
|  | `filter(callable)` | `MapInterface` |  |
|  | `first()` | `PairInterface` |  |
|  | `get(mixed, mixed)` | `mixed` |  |
|  | `hasKey(mixed)` | `bool` |  |
|  | `hasValue(mixed)` | `bool` |  |
|  | `intersect(MapInterface)` | `MapInterface` |  |
|  | `keys()` | `SetInterface` |  |
|  | `ksort(callable\|null)` | `MapInterface` |  |
|  | `last()` | `PairInterface` |  |
|  | `map(callable)` | `MapInterface` |  |
|  | `merge(mixed)` | `MapInterface` |  |
|  | `pairs()` | `VectorInterface` |  |
|  | `put(mixed, mixed)` | `MapInterface` |  |
|  | `putAll(mixed)` | `MapInterface` |  |
|  | `reduce(callable, mixed)` | `mixed` |  |
|  | `remove(mixed, mixed)` | `mixed` |  |
|  | `reverse()` | `MapInterface` |  |
|  | `skip(int)` | `PairInterface` |  |
|  | `slice(int, int\|null)` | `MapInterface` |  |
|  | `sort(callable\|null)` | `MapInterface` |  |
|  | `sum()` | `mixed` |  |
|  | `union(MapInterface)` | `MapInterface` |  |
|  | `values()` | `VectorInterface` |  |
|  | `xor(MapInterface)` | `MapInterface` |  |
| `PairInterface` | `copy()` | `PairInterface` |  |
|  | `equalsKey(mixed)` | `bool` |  |
|  | `toArray()` | `array` |  |
| `PriorityNodeInterface` | _(marker)_ |  |  |
| `QueueInterface` | `peek()` | `mixed` | extends `CollectionInterface`, `Countable`, `JsonSerializable`, `Traversable` |
|  | `pop()` | `mixed` |  |
|  | `push(mixed)` | `QueueInterface` |  |
| `SequenceInterface` | `__construct(mixed)` | `mixed` | extends `CollectionInterface`, `Countable`, `JsonSerializable`, `Traversable` |
|  | `apply(callable)` | `SequenceInterface` |  |
|  | `contains(mixed)` | `bool` |  |
|  | `filter(callable\|null)` | `SequenceInterface` |  |
|  | `find(mixed)` | `mixed` |  |
|  | `first()` | `mixed` |  |
|  | `get(int)` | `mixed` |  |
|  | `insert(int, mixed)` | `SequenceInterface` |  |
|  | `join(string\|null)` | `string` |  |
|  | `last()` | `mixed` |  |
|  | `map(callable)` | `SequenceInterface` |  |
|  | `merge(mixed)` | `SequenceInterface` |  |
|  | `pop()` | `mixed` |  |
|  | `push(mixed)` | `SequenceInterface` |  |
|  | `reduce(callable, mixed)` | `mixed` |  |
|  | `remove(int)` | `mixed` |  |
|  | `reverse()` | `SequenceInterface` |  |
|  | `rotate(int)` | `SequenceInterface` |  |
|  | `set(int, mixed)` | `SequenceInterface` |  |
|  | `shift()` | `mixed` |  |
|  | `slice(int, int\|null)` | `SequenceInterface` |  |
|  | `sort(callable\|null)` | `SequenceInterface` |  |
|  | `sum()` | `mixed` |  |
|  | `unshift(mixed)` | `SequenceInterface` |  |
| `SetInterface` | `add(mixed)` | `SetInterface` | extends `CollectionInterface`, `Countable`, `JsonSerializable`, `Traversable` |
|  | `allocate(int)` | `SetInterface` |  |
|  | `capacity()` | `int` |  |
|  | `contains(mixed)` | `bool` |  |
|  | `diff(SetInterface)` | `SetInterface` |  |
|  | `filter(callable\|null)` | `SetInterface` |  |
|  | `first()` | `mixed` |  |
|  | `get(int)` | `mixed` |  |
|  | `getMap()` | `MapInterface` |  |
|  | `intersect(SetInterface)` | `SetInterface` |  |
|  | `join(string\|null)` | `string` |  |
|  | `last()` | `mixed` |  |
|  | `merge(mixed)` | `SetInterface` |  |
|  | `reduce(callable, mixed)` | `mixed` |  |
|  | `remove(mixed)` | `void` |  |
|  | `reverse()` | `SetInterface` |  |
|  | `slice(int, int\|null)` | `SetInterface` |  |
|  | `sort(callable\|null)` | `SetInterface` |  |
|  | `sum()` | `mixed` |  |
|  | `union(SetInterface)` | `SetInterface` |  |
|  | `xor(SetInterface)` | `SetInterface` |  |
| `StackInterface` | `peek()` | `mixed` | extends `CollectionInterface`, `Countable`, `JsonSerializable`, `Traversable` |
|  | `pop()` | `mixed` |  |
|  | `push(mixed)` | `StackInterface` |  |
| `VectorInterface` | _(marker)_ |  | extends `CollectionInterface`, `Countable`, `JsonSerializable`, `SequenceInterface`, `Traversable`; constants: `MIN_VECTOR_CAPACITY` |

## Concrete classes

- `Deque` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `SequenceInterface`, `Stringable`, `Traversable`
- `Map` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `Pair` — implements `JsonSerializable`, `PairInterface`, `Stringable`
- `PriorityNode` — implements `PriorityNodeInterface`
- `PriorityQueue` — implements `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `Stringable`, `Traversable`
- `Queue` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `QueueInterface`, `Stringable`, `Traversable`
- `Set` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `SetInterface`, `Stringable`, `Traversable`
- `Stack` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `StackInterface`, `Stringable`, `Traversable`
- `Vector` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `SequenceInterface`, `Stringable`, `Traversable`, `VectorInterface`

## Tests as documentation

- `tests/Structure/AbstractCollectionTest.php`
- `tests/Structure/DequeTest.php`
- `tests/Structure/MapTest.php`
- `tests/Structure/PairTest.php`
- `tests/Structure/PriorityQueueTest.php`
- `tests/Structure/QueueTest.php`
- `tests/Structure/SetTest.php`
- `tests/Structure/StackTest.php`
- `tests/Structure/VectorTest.php`
