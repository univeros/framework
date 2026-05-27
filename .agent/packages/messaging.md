# univeros/messaging  ·  Altair\Messaging

**Purpose:** Thin MessageBus + worker bridge over Symfony Messenger, wired through Altair's Container.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `HandlerInterface` | _(marker)_ |  |  |
| `MessageBusInterface` | `dispatch(object, array)` | `Envelope` |  |

## Concrete classes

- `AsHandler` _(final)_
- `AttributeHandlerDiscoverer`
- `ContainerHandlerMiddleware` _(final)_ — implements `MiddlewareInterface`
- `FailedRetryCommand` _(final)_
- `FailedShowCommand` _(final)_
- `FailureSenderContainer` _(final)_ — implements `ContainerInterface`
- `HandlerEntry` _(final)_
- `HandlerLocator` _(final)_ — implements `HandlersLocatorInterface`
- `HandlerRegistry` _(final)_
- `LazyBus` _(final)_ — implements `MessageBusInterface`
- `LoggingMiddleware` _(final)_ — implements `MiddlewareInterface`
- `MessageBus` _(final)_ — implements `MessageBusInterface`
- `MessengerConfiguration` _(final)_ — implements `ConfigurationInterface`
- `TransportLocator` _(final)_ — implements `ContainerInterface`
- `TransportRegistry` _(final)_
- `TransportSettings` _(final)_
- `WorkerCommand` _(final)_
- `WorkerFactory` _(final)_

## Tests as documentation

- `tests/Messaging/Attribute/AsHandlerTest.php`
- `tests/Messaging/Configuration/MessengerConfigurationTest.php`
- `tests/Messaging/Configuration/TransportSettingsTest.php`
- `tests/Messaging/Discovery/AttributeHandlerDiscovererTest.php`
- `tests/Messaging/Discovery/HandlerRegistryTest.php`
- `tests/Messaging/HandlerLocatorTest.php`
- `tests/Messaging/Integration/DispatchAndConsumeTest.php`
- `tests/Messaging/MessageBusTest.php`
- `tests/Messaging/Middleware/LoggingMiddlewareTest.php`

## Related packages

- `psr/log`
- `symfony/messenger`
- `symfony/serializer`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
