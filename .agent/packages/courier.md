# univeros/courier  ·  Altair\Courier

**Purpose:** The Altair Courier package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `CommandBusInterface` | `handle(CommandMessageInterface)` | `mixed` |  |
| `CommandInterface` | `exec(CommandMessageInterface)` | `void` |  |
| `CommandLocatorServiceInterface` | `get(string)` | `CommandInterface` |  |
|  | `has(string)` | `bool` |  |
| `CommandMessageInterface` | `getLogMessage()` | `LogMessageInterface\|null` |  |
|  | `getName()` | `string` |  |
|  | `setLogMessage(LogMessageInterface)` | `void` |  |
| `CommandMessageNameResolverInterface` | `resolve(CommandMessageInterface)` | `string` |  |
| `CommandMiddlewareInterface` | `handle(CommandMessageInterface, callable)` | `void` |  |
| `CommandRunnerStrategyInterface` | `run(CommandMessageInterface)` | `void` |  |
| `InMemoryCommandLocatorServiceInterface` | `add(string, string)` | `InMemoryCommandLocatorServiceInterface` | extends `CommandLocatorServiceInterface` |
|  | `withMap(MessageCommandMap)` | `InMemoryCommandLocatorServiceInterface` |  |
| `LogMessageInterface` | `__toString()` | `string` | extends `Stringable` |
|  | `getLevel()` | `string` |  |
|  | `getMessage()` | `string` |  |
| `MiddlewareResolverInterface` | `__invoke(mixed)` | `mixed` |  |

## Concrete classes

- `CallableCommandLocatorService` — implements `CommandLocatorServiceInterface`
- `ClassCommandMessageNameResolver` — implements `CommandMessageNameResolverInterface`
- `CommandBus` — implements `CommandBusInterface`
- `CommandHandlerMiddleware` — implements `CommandMiddlewareInterface`
- `CommandLockerMiddleware` — implements `CommandMiddlewareInterface`
- `CommandLoggerMiddleware` — implements `CommandMiddlewareInterface`
- `CommandMessageNameResolver` — implements `CommandMessageNameResolverInterface`
- `CommandRunnerExecStrategy` — implements `CommandRunnerStrategyInterface`
- `CommandRunnerMiddlewareStrategy` — implements `CommandRunnerStrategyInterface`
- `ExecCommandBusConfiguration` — implements `ConfigurationInterface`
- `InMemoryCommandLocatorService` — implements `CommandLocatorServiceInterface`, `InMemoryCommandLocatorServiceInterface`
- `LogMessage` — implements `LogMessageInterface`, `Stringable`
- `MessageCommandMap` — implements `ArrayAccess`, `CapacityInterface`, `CollectionInterface`, `Countable`, `IteratorAggregate`, `JsonSerializable`, `MapInterface`, `Stringable`, `Traversable`
- `MiddlewareCommandBusConfiguration` — implements `ConfigurationInterface`
- `MiddlewareResolver` — implements `MiddlewareResolverInterface`

## Tests as documentation

- `tests/Courier/CallableCommandLocatorServiceTest.php`
- `tests/Courier/CommandBusTest.php`
- `tests/Courier/InMemoryCommandLocatorServiceTest.php`
- `tests/Courier/LogMessageTraitTest.php`

## Related packages

- `psr/log`
- `univeros/configuration`
- `univeros/container`
- `univeros/filesystem`
- `univeros/structure`
