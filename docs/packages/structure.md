# Structure

Typed data structures (Map, Set, Vector, Deque, Queue, Stack, and PriorityQueue) inspired by the `ext-ds` PHP extension, implemented in pure PHP with no C extension required.

**Package:** `univeros/structure`
**Namespace:** `Altair\Structure`
**Minimum PHP:** 8.3

---

## Introduction

PHP arrays are flexible, but that flexibility comes at a cost. An array can hold any mix of types, shift between indexed and associative layouts at any point, and have its iteration order change silently when keys are added or removed. For application code that needs to reason about collections precisely ("this is a sequence of integers in insertion order" or "this set contains no duplicates by construction"), a plain array provides no enforcement and no semantic signal to the reader.

The Structure package fills that gap. It provides eight concrete collection types and the contract layer above them. Each type has a well-defined semantic: a `Map` is a key-value store that preserves insertion order; a `Set` guarantees uniqueness; a `Vector` is a dense, contiguous, integer-indexed sequence; a `Deque` is a double-ended sequence that supports efficient push and unshift; a `Queue` is strictly FIFO; a `Stack` is strictly LIFO; a `PriorityQueue` dequeues by priority rather than arrival order; a `Pair` is an immutable key-value tuple used internally by `Map` and surfaced by `Map::pairs()` and `Map::skip()`.

The design mirrors the `ext-ds` PHP extension closely. `ext-ds` is a compiled C extension that ships with PHP 8.0+ as an optional pecl package and provides the same conceptual types (`Ds\Map`, `Ds\Set`, `Ds\Vector`, etc.). The Structure package reproduces that API in pure PHP so you can use the same collection semantics without depending on the extension being installed in your environment. The trade-off is performance: pure PHP iteration is slower than the C implementation, and this matters in tight loops over large collections. For most web application workloads, where collections are small and operations are infrequent, the difference is negligible.

Every collection implements `CollectionInterface`, which extends PHP's built-in `Countable`, `JsonSerializable`, and `Traversable`. This means every collection works with `count()`, can be passed to `json_encode()` directly, and can be iterated in a `foreach` loop. The `CapacityInterface` is implemented by the structures that manage an internal buffer (`Map`, `Vector`, `Deque`, `Queue`, `Stack`, and `PriorityQueue`) and exposes `allocate(int $capacity)` so you can pre-size the internal array and avoid repeated reallocations when you know the expected size in advance.

All collection methods return new instances where the contract specifies a new collection as a result (`filter`, `map`, `sort`, `reverse`, `slice`, `merge`, and similar). Methods that modify in place (`push`, `pop`, `put`, `remove`, and similar) return `$this` for chaining and update the receiver directly. This is intentional: sequence and map mutation is O(1) and returning a full copy on every insertion would be wasteful. If you need snapshot semantics, call `copy()` before the operation.

---

## Installation

Install via Composer:

```bash
composer require univeros/structure
```

The package has no runtime dependencies beyond PHP 8.3. No PHP extension is required. If you are consuming the full `univeros/framework` monorepo, `univeros/structure` is already satisfied through the root `replace` map.

---

## Quick start

The following example uses `Map` to build a type-safe key-value store, exercises the most common operations, and shows how to iterate and serialize it.

```php
<?php declare(strict_types=1);

use Altair\Structure\Map;

// Construct from an associative array.
$config = new Map([
    'host' => 'localhost',
    'port' => 5432,
    'name' => 'myapp',
]);

// Add or replace a key.
$config->put('ssl', true);

// Safe read with a default when the key may be absent.
$timeout = $config->get('timeout', 30);

// Membership test.
if ($config->hasKey('ssl')) {
    echo 'SSL enabled';
}

// Functional transformation — returns a new Map; original is unchanged.
$upper = $config->map(fn($key, $value) => is_string($value) ? strtoupper($value) : $value);

// Iterate in insertion order.
foreach ($config as $key => $value) {
    echo "{$key}: " . json_encode($value) . PHP_EOL;
}

// Serialize to JSON directly.
$json = json_encode($config);  // {"host":"localhost","port":5432,...}

// Export to a plain PHP array.
$array = $config->toArray();
```

---

## Concepts

### Contract hierarchy

Every collection type is defined by a contract in `Altair\Structure\Contracts\`. The hierarchy is:

```
CollectionInterface   (Countable, JsonSerializable, Traversable)
├── SequenceInterface          (Vector, Deque — integer-indexed sequences)
│   └── VectorInterface        (adds MIN_VECTOR_CAPACITY constant)
├── MapInterface               (Map)
├── SetInterface               (Set)
├── QueueInterface             (Queue)
└── StackInterface             (Stack)

CapacityInterface              (Map, Vector, Deque, Queue, Stack, PriorityQueue)
HashableInterface              (objects used as Map keys; implement hash() + equals())
PairInterface                  (Pair)
PriorityNodeInterface          (PriorityNode — internal to PriorityQueue)
```

`CollectionInterface` provides the stable API surface that all consumers should code against: `clear()`, `count()`, `copy()`, `isEmpty()`, `toArray()`, `toJson()`, and JSON serialization.

`SequenceInterface` adds the full sequence API: `apply`, `contains`, `filter`, `find`, `first`, `last`, `get`, `insert`, `join`, `map`, `merge`, `pop`, `push`, `reduce`, `remove`, `reverse`, `rotate`, `set`, `shift`, `slice`, `sort`, `sum`, and `unshift`.

`HashableInterface` is important when you use objects as `Map` keys. If the key object implements `HashableInterface`, `Map` calls `equals()` for key comparison rather than PHP's default `===` identity check. Implement `hash()` to return any scalar that identifies the object and `equals()` to define value equality.

### Trait composition

Concrete classes share behaviour through four traits in `Altair\Structure\Traits\`:

| Trait | Purpose | Used by |
|---|---|---|
| `CollectionTrait` | `clear`, `copy`, `count`, `isEmpty`, `toJson`, `jsonSerialize`, `__construct`, and `normalizeItems` | All concrete classes |
| `SequenceTrait` | Full `SequenceInterface` implementation over `$this->internal[]` | `Vector`, `Deque` |
| `CapacityTrait` | `capacity`, `allocate`, and `adjustCapacity` (halves buffer when size < capacity/4) | `Vector` |
| `SquaredCapacityTrait` | Capacity management where capacity is rounded up to the next power of two | `Map`, `Deque`, `PriorityQueue` |

`SquaredCapacityTrait` uses `CapacityTrait` internally and overrides `allocate` and `increaseCapacity` so the internal array is always sized to a power of two. This keeps the bucket-modulo arithmetic predictable and reduces reallocation frequency.

`Vector` uses `CapacityTrait` directly (with a 1.5x growth factor) rather than `SquaredCapacityTrait`, matching the ext-ds `Vector` growth policy.

### Collection types at a glance

| Type | Semantic | Order | Accepts duplicates |
|---|---|---|---|
| `Map` | Key-value pairs, any key type | Insertion order | Keys unique, values unrestricted |
| `Set` | Unique values | Insertion order | No |
| `Vector` | Dense integer-indexed sequence | Index order | Yes |
| `Deque` | Double-ended queue (full sequence API) | Index order | Yes |
| `Queue` | FIFO access only | Arrival order | Yes |
| `Stack` | LIFO access only | Arrival order | Yes |
| `PriorityQueue` | Highest-priority value first | Priority order | Yes |
| `Pair` | Key-value tuple | N/A | N/A |

---

## Usage

### Map

A `Map` is a sequential collection of key-value pairs where iteration order equals insertion order. Keys can be any PHP value (scalar, object, array), not just strings and integers as in a plain PHP array. When you call `put($key, $value)` and a pair with an equal key already exists, the existing pair's value is replaced; its insertion position does not change.

Internally, `Map` stores an array of `Pair` objects (`$this->internal`) and a squared-capacity buffer. Key lookup is linear: `hasKey`, `get`, and `remove` all iterate `$this->internal` and call `Pair::equalsKey()` on each pair. This means Map key lookup is O(n). For large maps where lookup performance matters, consider a plain associative PHP array with string keys.

**Typical operations:**

```php
use Altair\Structure\Map;

$map = new Map(['a' => 1, 'b' => 2]);

// Add or replace.
$map->put('c', 3);

// Read with optional default.
$value = $map->get('x', 0);   // returns 0 when key absent

// Remove and return the value; provide a default to suppress the exception.
$removed = $map->remove('b', null);

// Set-theoretic operations — all return new Map instances.
$keys    = $map->keys();       // Set of keys
$values  = $map->values();     // Vector of values in insertion order
$pairs   = $map->pairs();      // Vector of Pair copies

$diff    = $map->diff($other);      // pairs whose keys are not in $other
$inter   = $map->intersect($other); // pairs whose keys appear in both
$union   = $map->union($other);     // equivalent to merge
$xor     = $map->xor($other);       // pairs in exactly one of the two maps

// Sort by value; sort by key with ksort.
$sorted  = $map->sort();        // new Map, ascending by value
$ksorted = $map->ksort();       // new Map, ascending by key
```

**Gotchas:**

- `get($key)` with no default throws `\OutOfBoundsException` when the key is absent. Always pass a default or guard with `hasKey`.
- `offsetExists($offset)` returns `false` when the value is `null` even if the key exists. Use `hasKey` for existence checks.
- `remove($key)` without a default also throws `\OutOfBoundsException` when the key is absent. Pass `null` as a second argument to make it idempotent.
- When an object is used as a key and it does not implement `HashableInterface`, equality is tested with strict PHP `===` (identity). Two different instances that represent the same logical key will produce two separate entries.

---

### Set

A `Set` is a collection of unique values in insertion order. Internally, a `Set` wraps a `Map` where the values are keys and all stored map-values are `null`. This means you get the same insertion-order preservation, the same O(n) membership test, and the same capacity management as `Map`, without writing it again.

**Typical operations:**

```php
use Altair\Structure\Set;

$tags = new Set(['php', 'oop', 'collections']);

// Adding a duplicate is silent; the set does not grow.
$tags->add('php');  // still 3 elements

// Membership.
$tags->contains('oop');        // true
$tags->contains('java');       // false

// Set algebra — all return new Set instances.
$union = $tags->union($other);
$inter = $tags->intersect($other);
$diff  = $tags->diff($other);
$sym   = $tags->xor($other);  // symmetric difference

// Retrieve a value by position (not by value).
$first = $tags->get(0);   // 'php'
```

**Gotchas:**

- `offsetSet($offset, $value)` requires `$offset === null` (i.e., `$set[] = $value`). Passing a non-null offset throws `\OutOfBoundsException`.
- `offsetExists` and `offsetUnset` always throw `\Error('Not supported')`. Do not use `isset($set[$i])` or `unset($set[$i])`; use `contains()` and `remove()` instead.
- `sort()` sorts by the key positions inside the internal `Map` (using `ksort`), which sorts the values themselves.
- `getMap()` exposes the internal `Map` for delegation purposes. Reading from it directly is safe; mutating it bypasses uniqueness logic and is not supported.

---

### Vector

A `Vector` is the most efficient sequence type when random access by index is the primary operation. It stores values in a contiguous PHP array. Index access is O(1). Insertion or removal in the middle (using `insert` or `remove`) is O(n) because subsequent elements must shift.

`Vector` uses `SequenceTrait` and `CapacityTrait` with a minimum capacity of `VectorInterface::MIN_VECTOR_CAPACITY` (10). Its growth factor is 1.5× when the buffer fills; it shrinks when size drops below capacity/4, down to the minimum of 10.

**Typical operations:**

```php
use Altair\Structure\Vector;

$v = new Vector([10, 20, 30]);

$v->push(40, 50);          // append one or more values
$v->unshift(5);            // prepend; returns new Vector
$first = $v->first();      // 5
$last  = $v->pop();        // 50, removes it

// Transform — returns new Vector.
$doubled = $v->map(fn($x) => $x * 2);
$even    = $v->filter(fn($x) => $x % 2 === 0);
$sorted  = $v->sort();
$sliced  = $v->slice(1, 3);
```

**Gotchas:**

- `get(int $index)` and `set(int $index, $value)` throw `\OutOfRangeException` for out-of-bounds indices. Check `count()` first or use `find()` to locate a value.
- `push()` and `pop()` operate at the tail. `shift()` removes from the head. For frequent head-and-tail operations, prefer `Deque`.
- `insert(int $index, ...$values)` splices values in at the given index. Internally this calls `array_splice`, which shifts all elements to the right of the insertion point.

---

### Deque

A `Deque` (pronounced "deck", from "double-ended queue") is a sequence that supports efficient push and unshift operations at both ends. It uses `SequenceTrait` and `SquaredCapacityTrait`, giving it the full sequence API alongside power-of-two capacity management. The internal description in the source explains that two pointers track a head and a tail, allowing "wrap-around" access that avoids moving elements when adding to either end.

`Queue` wraps a `Deque` internally. Prefer `Deque` directly when you need the full sequence API (sort, slice, map, filter, etc.) and also need efficient prepend operations.

**Typical operations:**

```php
use Altair\Structure\Deque;

$d = new Deque(['b', 'c', 'd']);

$d->unshift('a');          // prepend; returns new Deque
$d->push('e');             // append
$head = $d->shift();       // 'a', removes from front
$tail = $d->pop();         // 'e', removes from back

// Rotate: move elements from one end to the other.
// Positive n shifts n elements from front to back.
$rotated = $d->rotate(1);  // ['c', 'd', 'b'] if ['b', 'c', 'd']
```

**Gotchas:**

- `Deque` shares the full `SequenceTrait` implementation with `Vector`. The only structural difference is the capacity strategy: `Deque` uses `SquaredCapacityTrait` (powers of two) while `Vector` uses the linear 1.5× growth `CapacityTrait`.
- When sorting a `Deque` whose internal buffer has wrapped (head pointer is not at index 0), the implementation must realign the buffer before sorting. The test suite covers three cases: enough free space to realign without allocation, exactly enough space, and insufficient space requiring a new allocation.

---

### Queue

A `Queue` exposes a strictly FIFO interface: `push` to enqueue, `pop` to dequeue from the front, `peek` to inspect the front without removing it. No random access is provided; `offsetGet`, `offsetExists`, and `offsetUnset` throw `\Error('Not supported')`.

`Queue` wraps a `Deque` internally. The `Deque`'s efficient `shift()` becomes the `Queue::pop()` operation, and `Deque::push()` becomes the enqueue path. Capacity management is delegated to the wrapped `Deque`.

Iterating a `Queue` with `foreach` is destructive: the iterator calls `pop()` until `isEmpty()`.

**Typical operations:**

```php
use Altair\Structure\Queue;

$queue = new Queue();
$queue->push('first');
$queue->push('second', 'third');   // variadic — enqueue multiple

$front = $queue->peek();           // 'first', does not remove
$item  = $queue->pop();            // 'first', removes it

// Pre-size the internal Deque to avoid reallocations.
$queue->allocate(100);
```

**Gotchas:**

- `foreach ($queue as $item)` empties the queue. Call `copy()` first if you need to iterate non-destructively.
- Only `$queue[] = $value` is supported via `ArrayAccess`. Passing a key to `offsetSet` throws `\OutOfBoundsException`.

---

### Stack

A `Stack` exposes a strictly LIFO interface: `push` to add to the top, `pop` to remove from the top, `peek` to read the top without removing it. No random access is provided.

`Stack` wraps a `Vector` internally. `Stack::push()` calls `Vector::push()` (append to tail), and `Stack::pop()` calls `Vector::pop()` (remove from tail). Both are O(1) amortized. `Stack::toArray()` returns elements in LIFO order (top-first) by calling `array_reverse` on the internal vector.

Iterating a `Stack` with `foreach` is destructive: the iterator calls `pop()` until `isEmpty()`.

**Typical operations:**

```php
use Altair\Structure\Stack;

$stack = new Stack();
$stack->push('a');
$stack->push('b', 'c');     // 'c' is now on top

$top   = $stack->peek();    // 'c', does not remove
$value = $stack->pop();     // 'c', removes it

// Stack::toArray() returns in LIFO order.
$array = $stack->toArray(); // ['b', 'a']
```

**Gotchas:**

- `toArray()` reverses the internal order so that the first element in the returned array is the top of the stack. This is the opposite of the internal `Vector`'s `toArray()`.
- Like `Queue`, `foreach` is destructive and `offsetGet`/`offsetExists`/`offsetUnset` throw `\Error('Not supported')`.

---

### PriorityQueue

A `PriorityQueue` is a max-heap. Values are pushed with an integer priority and are always dequeued in descending priority order. When two values have the same priority, they are dequeued in FIFO insertion order; the stamp field on each `PriorityNode` records arrival sequence and serves as the tiebreaker.

`PriorityQueue::push(mixed $value, int $priority)` has a different signature from `Queue::push`. The second argument is required and is the priority integer.

`PriorityQueue::toArray()` returns values in priority order without permanently removing them: it saves and restores `$this->heap` around the drain operation.

Iterating a `PriorityQueue` with `foreach` is destructive: the iterator calls `pop()` until `isEmpty()`.

**Typical operations:**

```php
use Altair\Structure\PriorityQueue;

$pq = new PriorityQueue();
$pq->push('low',    1);
$pq->push('high',   10);
$pq->push('medium', 5);

$top  = $pq->peek();    // 'high', does not remove
$item = $pq->pop();     // 'high', removes it
// next pop would return 'medium', then 'low'

// When priorities are equal, FIFO order is preserved.
$pq2 = new PriorityQueue();
$pq2->push('first',  0);
$pq2->push('second', 0);
$pq2->pop();  // 'first'
$pq2->pop();  // 'second'
```

**Gotchas:**

- Priority is `int` only. Passing a float will cause a `TypeError` under `strict_types=1`.
- `PriorityQueue` does not implement `CapacityInterface` as a declared interface, but it does use `SquaredCapacityTrait`, so `allocate()` and `capacity()` are available as public methods at the class level.
- `PriorityQueue` does not expose a `push(mixed ...$values)` without priorities. The method signature is `push(mixed $value, int $priority): void`; it does not return `$this`.

---

### Pair

A `Pair` is a simple key-value tuple. It is not a standalone collection; it is the internal node type that `Map` stores and returns. You encounter `Pair` instances through:

- `Map::first()` and `Map::last()`: return the first and last `Pair`.
- `Map::skip(int $position)`: returns a copy of the `Pair` at the given insertion position.
- `Map::pairs()`: returns a `Vector` of `Pair` copies.

`Pair` has two public properties, `$key` and `$value`, set by the constructor. It implements `JsonSerializable` and serializes as `['key' => ..., 'value' => ...]`. It also implements `\Stringable` and returns `'object(Altair\Structure\Pair)'`.

When a `Pair`'s key implements `HashableInterface`, `equalsKey()` delegates to `HashableInterface::equals()` and also confirms the class names match. This allows objects of the same class and same logical identity to be treated as equivalent map keys.

```php
use Altair\Structure\Map;

$map = new Map(['x' => 10, 'y' => 20]);

// Get the pair at insertion position 0 (a copy — safe to read, not live).
$pair = $map->skip(0);
echo $pair->key;    // 'x'
echo $pair->value;  // 10

// Iterate all pairs as Pair objects.
foreach ($map->pairs() as $pair) {
    echo "{$pair->key} => {$pair->value}" . PHP_EOL;
}
```

**Gotchas:**

- Pairs returned by `skip()` and `pairs()` are copies. Modifying `$pair->value` does not update the map.
- Pairs returned by `first()` and `last()` are the live internal objects. Mutating `$pair->value` on these will modify the map in place. This is an edge case to be aware of; prefer `get($key)` for reads and `put($key, $value)` for updates.
- `unset($pair->key)` triggers `__get` via a PHP quirk and sets the property to `null` rather than removing it.

---

## Configuration

This package has no configuration. It provides a library of data structure classes. No service provider, bootstrap file, or environment variable is required. Instantiate the classes directly.

---

## Testing

All collections implement `toArray()` and serialize to JSON. The two most useful assertion patterns are comparing `toArray()` output (checking both values and key order) and round-tripping through `serialize()`/`unserialize()`.

The `AbstractCollectionTest` in the test suite uses a custom `assertToArray` that checks array values and key order separately, because PHPUnit's `assertEquals` on associative arrays considers `[a => 1, b => 2]` equal to `[b => 2, a => 1]`:

```php
use Altair\Structure\Map;
use Altair\Structure\Set;
use Altair\Structure\Vector;
use PHPUnit\Framework\TestCase;

final class StructureExampleTest extends TestCase
{
    public function testMapPreservesInsertionOrder(): void
    {
        $map = new Map(['b' => 2, 'a' => 1]);
        $array = $map->toArray();

        // Assert order, not just equality.
        $this->assertSame(['b', 'a'], array_keys($array));
        $this->assertSame([2, 1], array_values($array));
    }

    public function testSetDeduplicates(): void
    {
        $set = new Set(['x', 'y', 'x', 'z', 'y']);
        $this->assertSame(3, count($set));
        $this->assertSame(['x', 'y', 'z'], $set->toArray());
    }

    public function testSerializeRoundTrip(): void
    {
        $v = new Vector([1, 2, 3]);
        $restored = unserialize(serialize($v));

        $this->assertInstanceOf(Vector::class, $restored);
        $this->assertSame([1, 2, 3], $restored->toArray());
    }

    public function testMapJsonSerialize(): void
    {
        $map = new Map(['host' => 'localhost', 'port' => 5432]);
        $this->assertSame('{"host":"localhost","port":5432}', json_encode($map));
    }
}
```

When testing `PriorityQueue`, remember that `toArray()` is non-destructive (it restores the heap), but `foreach` is destructive. Use `toArray()` for assertions that should not drain the queue.

---

## Extending

The idiomatic way to create a domain-specific collection is to extend a concrete class and override the methods that need type-specific behaviour. The framework's own `Altair\Cookie\Collection\CookieCollection` extending `Map` is the reference pattern.

`CookieCollection` overrides `put($key, $value)` to wrap each value in a `Cookie` object, `putAll($values)` to handle both raw values and existing `Cookie` instances, `sort()` to compare by cookie value strings, `values()` to return the cookie value strings rather than the `Cookie` objects, `sum()` to throw `InvalidCallException` (not meaningful for cookies), and the protected helpers `lookupValue` and `pairsToArray` to accommodate the `Cookie` wrapper.

You can follow the same pattern for any domain type. Extend `Map` for keyed domain collections, `Set` for unique-value collections, or `Vector` for ordered lists:

```php
<?php declare(strict_types=1);

use Altair\Structure\Map;
use Altair\Structure\Contracts\MapInterface;

/**
 * A typed map from string names to positive integer scores.
 * Keys are strings; values are always positive ints.
 */
final class ScoreBoard extends Map
{
    public function record(string $player, int $score): self
    {
        if ($score < 0) {
            throw new \InvalidArgumentException('Score must be non-negative.');
        }

        $this->put($player, $score);

        return $this;
    }

    public function leader(): string
    {
        return $this->ksort()->last()->key;
    }

    // Override put() to enforce int values.
    #[\Override]
    public function put($key, $value): MapInterface
    {
        if (!is_string($key) || !is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('Keys must be strings; values must be non-negative ints.');
        }

        return parent::put($key, $value);
    }
}
```

When extending, note:

- `Map::put()` and `Map::putAll()` are the two entry points for all insertions, including those triggered by `ArrayAccess` (`$map[$key] = $value`). Override both to guarantee your type constraints are applied regardless of how callers add values.
- Protected methods `lookupKey`, `lookupValue`, `delete`, and `pairsToArray` are available to subclasses and used by `CookieCollection`. They are not part of the public interface and may change in future versions.
- `clear()` (from `CollectionTrait`) returns `new static()`, so it will return an instance of your subclass. The default constructor accepts no arguments (`Map::__construct($values = [])`), so make sure your subclass constructor is compatible.

---

## Recipes

### Type-safe DTO bag with Map

Use a `Map` as a typed parameter bag when constructing domain commands or query objects, avoiding the verbosity of named arguments while keeping keys self-documenting.

```php
use Altair\Structure\Map;

$params = new Map([
    'user_id'    => 42,
    'email'      => 'alice@example.com',
    'roles'      => ['admin', 'editor'],
]);

function createUser(Map $params): void
{
    $id    = $params->get('user_id');
    $email = $params->get('email');
    $roles = $params->get('roles', []);

    // ... persist
}

createUser($params);
```

The `Map` rejects keys after construction only if you treat it as read-only by convention. For strict immutability, call `$params->copy()` before passing it to code that may call `put()`.

---

### Deduplication pipeline with Set

Collect items from multiple sources and deduplicate them in one pass without sorting or secondary comparisons.

```php
use Altair\Structure\Set;

function uniqueTags(array ...$sources): array
{
    $all = new Set();

    foreach ($sources as $source) {
        foreach ($source as $tag) {
            $all->add($tag);
        }
    }

    return $all->toArray();
}

$tags = uniqueTags(
    ['php', 'oop', 'patterns'],
    ['oop', 'design', 'php'],
    ['php', 'testing'],
);
// ['php', 'oop', 'patterns', 'design', 'testing']
```

Insertion order is preserved. The first occurrence of each value keeps its position; subsequent identical values are silently dropped.

---

### Breadth-first search with Queue

`Queue`'s FIFO guarantee maps directly onto the BFS frontier. Each node's neighbours are pushed to the back of the queue and processed in arrival order, ensuring that shallower nodes are always visited before deeper ones.

```php
use Altair\Structure\Queue;
use Altair\Structure\Set;

/**
 * Returns nodes reachable from $start in BFS order.
 *
 * @param array<string, string[]> $graph  adjacency list
 */
function bfs(array $graph, string $start): array
{
    $visited = new Set([$start]);
    $queue   = new Queue([$start]);
    $order   = [];

    while (!$queue->isEmpty()) {
        $node    = $queue->pop();
        $order[] = $node;

        foreach ($graph[$node] ?? [] as $neighbour) {
            if (!$visited->contains($neighbour)) {
                $visited->add($neighbour);
                $queue->push($neighbour);
            }
        }
    }

    return $order;
}
```

The `Set` tracks visited nodes in O(n) lookup (the same cost as `Map`). For large graphs, consider a plain PHP `array` keyed by node name for O(1) visited-check.

---

### Undo stack

`Stack`'s LIFO interface maps directly onto command history. Each executed command is pushed; undo pops the most recent one.

```php
use Altair\Structure\Stack;

final class TextEditor
{
    private Stack $history;
    private string $content = '';

    public function __construct()
    {
        $this->history = new Stack();
    }

    public function type(string $text): void
    {
        $this->history->push($this->content); // save state
        $this->content .= $text;
    }

    public function undo(): void
    {
        if ($this->history->isEmpty()) {
            return;
        }
        $this->content = $this->history->pop();
    }

    public function content(): string
    {
        return $this->content;
    }
}

$editor = new TextEditor();
$editor->type('Hello');
$editor->type(', world');
$editor->undo();            // reverts ', world'
echo $editor->content();    // 'Hello'
```

---

### Task scheduling with PriorityQueue

`PriorityQueue` is the natural data structure for a task runner that must always execute the highest-priority pending task next, without sorting the full list on each step.

```php
use Altair\Structure\PriorityQueue;

final class TaskRunner
{
    private PriorityQueue $queue;

    public function __construct()
    {
        $this->queue = new PriorityQueue();
    }

    public function schedule(callable $task, int $priority): void
    {
        $this->queue->push($task, $priority);
    }

    public function runNext(): void
    {
        if ($this->queue->isEmpty()) {
            return;
        }

        $task = $this->queue->pop(); // always the highest-priority callable
        $task();
    }

    public function runAll(): void
    {
        while (!$this->queue->isEmpty()) {
            $this->runNext();
        }
    }
}

$runner = new TaskRunner();
$runner->schedule(fn() => print "low\n",      1);
$runner->schedule(fn() => print "critical\n", 100);
$runner->schedule(fn() => print "normal\n",   10);

$runner->runAll();
// Prints: critical, normal, low
```

When two tasks share the same priority, they run in FIFO order thanks to the insertion-order tiebreaker in `PriorityNode`.

---

## Related packages

- [`./cookie.md`](./cookie.md): The Cookie package's `CookieCollection` and `SetCookieCollection` both extend `Map`. Reading this document first makes the `CookieCollection` API immediately legible: every method that is not overridden in `CookieCollection` is inherited from `Map` and documented here.
- [`./cache.md`](./cache.md): The Cache package does not currently use `Altair\Structure` types in its public API, but domain layers that bridge the two packages (for example, storing `Map` instances as cache values) will serialize correctly because all collection types implement `JsonSerializable` and `\Serializable`.
- [`./common.md`](./common.md): The Common package provides `Arr` helpers that complement `toArray()` for post-processing collection output (dot-path access, column extraction, recursive merging).
- [`./data.md`](./data.md): The Data package's entity `toArray()` and `withData()` pattern works well alongside `Map` when you need both a typed entity shape and a flexible key-value store.

---

## Limitations

- **Pure PHP means slower than `ext-ds`** for the same operations. Key lookup in `Map` and membership testing in `Set` are both O(n) linear scans over an internal array of `Pair` objects. The `ext-ds` C implementation uses hash tables for O(1) average-case lookup. If you are handling collections in the tens of thousands of items per request, or in tight loops, install `ext-ds` and use its native types instead.
- **`Map` key lookup is O(n).** There is no hash table or bucket structure. For maps with more than a few hundred entries where lookup dominates, a plain PHP associative array with string keys will outperform `Map`.
- **Iterator protocol is destructive for Queue, Stack, and PriorityQueue.** `foreach` calls `pop()` until the collection is empty. Call `copy()` before iterating if you need the collection to remain intact.
- **`Set::offsetExists` and `Set::offsetUnset` throw `\Error`.** Array-access syntax beyond `$set[] = $value` is not supported on `Set`. This is a deliberate restriction.
- **`PriorityQueue` accepts only `int` priorities.** Floating-point priorities are not supported; PHP's strict type enforcement will reject them at the call site under `declare(strict_types=1)`.
- **No concurrent modification protection.** If you modify a collection inside a `foreach` loop, behaviour depends on which collection you are using and what PHP's iterator protocol does with the modified `$this->internal`. Mutation during iteration is not tested and is not safe.
- **`clear()` returns a new empty instance.** `CollectionTrait::clear()` is `return new static()`. It does not reset `$this` in place; it returns a new object. Code that calls `$collection->clear()` and discards the return value will not see the collection emptied.
