---
title: Use Map and Vector for typed in-memory collections
scenario: You need keyed lookup with insertion order or a contiguous sequence — without the gotchas of bare PHP arrays.
packages: [structure]
since: 2.0.0
tested_by: tests/Examples/StructureTypedCollectionsTest.php
---

# Use Map and Vector for typed in-memory collections

`Altair\Structure\Map` preserves insertion order, accepts any key type, and never falls back to integer-keys-when-you-meant-strings. `Altair\Structure\Vector` is a contiguous sequence with O(1) push/pop and `map`/`filter`/`reduce` that return new collections.

```php
use Altair\Structure\Map;
use Altair\Structure\Vector;

// Map: keyed access in insertion order
$active = new Map();
$active->put('alice', ['status' => 'online']);
$active->put('bob',   ['status' => 'away']);

$active->get('alice');           // ['status' => 'online']
$active->hasKey('charlie');      // false
$active->count();                // 2

// Vector: a typed list you can transform
$totals = new Vector([10, 20, 30, 40]);

$totals->push(50);               // [10, 20, 30, 40, 50]
$totals->count();                // 5
$totals->toArray();              // [10, 20, 30, 40, 50]
```

## Gotchas

- **Both implement `Countable`, `IteratorAggregate`, and `JsonSerializable`.** They round-trip cleanly through `count()`, `foreach`, and `json_encode()` — but their JSON shape is a list (for `Vector`) and an object (for `Map`).
- **`Map::put` returns the map itself for chaining**, but the operation IS mutating. If you need an immutable copy, call `->copy()` first.
- **Reach for `Map` when key insertion order matters.** A plain PHP array with string keys works similarly but loses ordering on certain operations (especially via JSON).
