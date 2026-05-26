# univeros/sample-package  ·  Altair\Tests\AgentSpec\Fixtures\SamplePackage

**Purpose:** Fixture package used by AgentSpec test cases.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `FarewellInterface` | `bye()` | `string` | extends `GreeterInterface` |
| `GreeterInterface` | `greet(string)` | `string` | constants: `DEFAULT_GREETING` |

## Concrete classes

- `AttributeMiddleware` _(abstract)_
- `SampleGreeter` _(final)_ — implements `GreeterInterface`

## Request attribute conventions

| Constant | Value | Declared on |
|---|---|---|
| `ATTRIBUTE_CLIENT_ID` | `sample:client-id` | `AttributeMiddleware` |
| `ATTRIBUTE_LOCALE` | `sample:locale` | `AttributeMiddleware` |

## Common patterns

### Greet someone

```php
$greeter = new SampleGreeter();
echo $greeter->greet('world');
```

### Bring your own greeting

```php
final class Shouter implements GreeterInterface { /* ... */ }
```

## Tests as documentation

- `tests/AgentSpec/Fixtures/TestsRoot/SamplePackage/SampleGreeterTest.php`

## Related packages

- `psr/log`
- `univeros/structure`

## Stability

Fixture package: never published. Used only in `tests/AgentSpec` snapshots.
