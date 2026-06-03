# univeros/data  ·  Altair\Data

**Purpose:** Trait-composed, immutable-by-default data objects with JSON serialization, Serializable support, and Carbon-powered date mutators.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `ArrayableInterface` | `toArray()` | `array` |  |
| `DataObjectInterface` | `get(string)` | `mixed` | extends `ArrayableInterface`, `JsonSerializable`, `Serializable` |
|  | `has(string)` | `bool` |  |
|  | `withData(array)` | `mixed` |  |
| `DateAttributeMutatorInterface` | `asCarbonDate(string)` | `Carbon` |  |
|  | `asDateString(string, mixed)` | `string` |  |

## Tests as documentation

- `tests/Data/EntityTest.php`
