# univeros/data  ·  Altair\Data

**Purpose:** The Altair Data package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `ArrayableInterface` | `toArray()` | `array` |  |
| `CreateRepositoryInterface` | `create(array)` | `EntityInterface` |  |
| `DateAttributeMutatorInterface` | `asCarbonDate(string)` | `Carbon` |  |
|  | `asDateString(string, mixed)` | `string` |  |
| `DeleteRepositoryInterface` | `delete(mixed)` | `bool` |  |
|  | `deleteAll()` | `bool` |  |
|  | `deleteAllBy(array)` | `mixed` |  |
|  | `deleteOneBy(array)` | `bool` |  |
| `EntityInterface` | `get(string)` | `mixed` | extends `ArrayableInterface`, `JsonSerializable`, `Serializable` |
|  | `has(string)` | `bool` |  |
|  | `withData(array)` | `mixed` |  |
| `PartialRepositoryInterface` | `findPartial(mixed, array)` | `EntityInterface\|null` |  |
|  | `findPartialBy(array, array)` | `EntityInterface\|null` |  |
|  | `findPartialsBy(array, array)` | `array\|null` |  |
| `QueryRepositoryInterface` | `find(mixed)` | `EntityInterface\|null` |  |
|  | `findAll()` | `array\|null` |  |
|  | `findAllBy(array)` | `array\|null` |  |
|  | `findOneBy(array)` | `EntityInterface\|null` |  |
| `ScalarRepositoryInterface` | `findScalar(mixed, string)` | `mixed` |  |
|  | `findScalarBy(array, string)` | `mixed` |  |
|  | `findScalars(string)` | `array\|null` |  |
| `UpdateRepositoryInterface` | `update(mixed, array)` | `mixed` |  |

## Tests as documentation

- `tests/Data/EntityTest.php`
