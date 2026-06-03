# univeros/common  ·  Altair\Common

**Purpose:** Pure PHP utilities — string helpers, array manipulation, and a key-value registry — shared across every Altair package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `RegistryInterface` | `get(string, mixed)` | `mixed` |  |
|  | `set(string, mixed)` | `mixed` |  |

## Concrete classes

- `Arr`
- `ArrayRegistry` — implements `RegistryInterface`
- `Inflector`
- `Pluralizer`
- `Str`
- `Transliterator`

## Tests as documentation

- `tests/Common/Registry/ArrayRegistryTest.php`
- `tests/Common/Support/ArrTest.php`
- `tests/Common/Support/StrTest.php`
