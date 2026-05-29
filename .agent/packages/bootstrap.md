# univeros/bootstrap  ·  Altair\Bootstrap

**Purpose:** Zero-to-running project bootstrap: the bin/altair new command that materialises a runnable Altair API from the skeleton template.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `PresetInterface` | `name()` | `string` |  |
|  | `orm()` | `string` |  |
|  | `queue()` | `string` |  |

## Concrete classes

- `FullPreset` _(final)_ — implements `PresetInterface`
- `GenerateEnvStep` _(final)_
- `MinimalPreset` _(final)_ — implements `PresetInterface`
- `NewCommand` _(final)_
- `PresetRegistry` _(final)_
- `SkeletonGenerator` _(final)_
- `StandardPreset` _(final)_ — implements `PresetInterface`

## Tests as documentation

- `tests/Bootstrap/GenerateEnvStepTest.php`
- `tests/Bootstrap/GeneratedPingTest.php`
- `tests/Bootstrap/NewCommandTest.php`
- `tests/Bootstrap/PresetRegistryTest.php`
- `tests/Bootstrap/SkeletonGeneratorTest.php`

## Related packages

- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/doctor`
