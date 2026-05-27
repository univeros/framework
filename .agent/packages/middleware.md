# univeros/middleware  ·  Altair\Middleware

**Purpose:** The Altair Middleware package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `MiddlewareInterface` | `__invoke(PayloadInterface, callable)` | `mixed` |  |
| `MiddlewareManagerInterface` | `__invoke(PayloadInterface)` | `mixed` |  |
| `MiddlewareResolverInterface` | `__invoke(mixed)` | `MiddlewareInterface` |  |
| `MiddlewareRunnerInterface` | `__invoke(PayloadInterface)` | `PayloadInterface` |  |
| `PayloadInterface` | `getAttribute(mixed, mixed)` | `mixed` |  |
|  | `getAttributes()` | `array` |  |
|  | `withAttribute(mixed, mixed)` | `PayloadInterface` |  |
|  | `withAttributes(array)` | `PayloadInterface` |  |
|  | `withoutAttribute(mixed)` | `PayloadInterface` |  |

## Concrete classes

- `MiddlewareConfiguration` — implements `ConfigurationInterface`
- `MiddlewareManager` — implements `MiddlewareManagerInterface`
- `MiddlewareResolver` — implements `MiddlewareResolverInterface`
- `Payload` — implements `JsonSerializable`, `PayloadInterface`
- `Runner` — implements `MiddlewareRunnerInterface`

## Tests as documentation

- `tests/Middleware/MiddlewareManagerTest.php`
- `tests/Middleware/PayloadTest.php`
- `tests/Middleware/RunnerTest.php`

## Related packages

- `univeros/structure`
