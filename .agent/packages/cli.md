# univeros/cli  ·  Altair\Cli

**Purpose:** Attribute-driven CLI built on top of Symfony Console.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `CommandLocatorInterface` | `scan(array)` | `iterable` |  |

## Concrete classes

- `AltairCommand` — implements `SignalableCommandInterface`
- `Application` — implements `ResetInterface`
- `Argument` _(final)_
- `ArgumentBinder`
- `AttributeCommandDiscoverer` — implements `CommandLocatorInterface`
- `CliConfiguration` — implements `ConfigurationInterface`
- `Command` _(final)_
- `Option` _(final)_
- `OptionBinder`
- `ParameterTypeInspector`
- `ValueCoercer`

## Tests as documentation

- `tests/Cli/Binding/ArgumentBinderTest.php`
- `tests/Cli/Binding/OptionBinderTest.php`
- `tests/Cli/Binding/ValueCoercerTest.php`
- `tests/Cli/Discovery/AttributeCommandDiscovererTest.php`
- `tests/Cli/IntegrationTest.php`

## Related packages

- `symfony/console`
- `univeros/configuration`
- `univeros/container`
