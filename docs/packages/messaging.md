# Messaging

> A thin `MessageBus` + worker bridge over **Symfony Messenger**, wired through the framework's container, with attribute-driven handler discovery and a scaffold extension for message + handler emission.

**Composer:** `univeros/messaging`
**Namespace:** `Altair\Messaging`

## Introduction

The framework deliberately does not ship its own queue or transport adapters. Symfony Messenger has six years of head start on AMQP, Redis Streams, SQS, Doctrine, Beanstalkd, Sync and InMemory transports, plus a mature middleware story. Re-implementing any of that would be wasted effort.

This package wraps Messenger. It does *not* replace it. The wrap exists for three reasons:

1. **A vendor-neutral contract.** `Altair\Messaging\Contracts\MessageBusInterface` is framework-owned. Application code typehints that, not `Symfony\Component\Messenger\MessageBusInterface`. If Messenger is ever replaced (with `enqueue/enqueue`, a custom transport, or a fake for tests), the call sites do not move.
2. **A consistent DI story.** `MessengerConfiguration` reads `MESSENGER_*` env variables and binds the entire stack — bus, middleware, transports, handler locator, failure listener — into `Altair\Container` in one call. Handlers themselves are resolved through the framework's container so they get the same dependency injection as any other service.
3. **Spec-driven generation.** The `univeros/scaffold` sub-package already turns a YAML endpoint spec into Action / Input / Responder / test / OpenAPI. With a `queue:` block on the same spec, you also get a readonly message DTO, an `#[AsHandler]`-decorated handler stub, and a PHPUnit test — emitted alongside the HTTP artifacts, so the wire format and the async contract stay in sync without manual coordination.

What this package deliberately does *not* do: it does not invent a new message envelope (Symfony's `Envelope` + `StampInterface` are the source of truth), it does not ship its own retry strategy (Messenger's `MultiplierRetryStrategy` is fine), and it does not embed transport adapters — each transport bridge (`symfony/redis-messenger`, `symfony/doctrine-messenger`, `symfony/amqp-messenger`) is installed per-application.

## Installation

Standalone:

```bash
composer require univeros/messaging
```

This pulls in `symfony/messenger` and `symfony/serializer`. To use Redis as a transport, also install `symfony/redis-messenger`; for Doctrine, `symfony/doctrine-messenger`; etc. Sync and InMemory transports are always available with no extra dependencies.

If you are installing the full framework, `composer require univeros/framework` already includes this package.

## Quick start

```env
MESSENGER_TRANSPORT_DEFAULT=in-memory://
MESSENGER_ROUTING=App\Messages\SendWelcomeEmail:default
```

```php
use Altair\Configuration\Support\Env;
use Altair\Container\Container;
use Altair\Messaging\Configuration\MessengerConfiguration;
use Altair\Messaging\Contracts\MessageBusInterface;

$container = new Container();
$container->share(new Env());

(new MessengerConfiguration(
    handlerPaths: [__DIR__ . '/app/Messages'],
))->apply($container);

$bus = $container->make(MessageBusInterface::class);
$bus->dispatch(new \App\Messages\SendWelcomeEmail('u1', 'alice@example.com'));
```

That is everything an application needs to start dispatching messages. The `handlerPaths` argument tells the discoverer where to scan for `#[AsHandler]`-decorated classes.

## Defining a handler

```php
namespace App\Messages;

use Altair\Messaging\Attribute\AsHandler;
use Psr\Log\LoggerInterface;

#[AsHandler(SendWelcomeEmail::class)]
final readonly class SendWelcomeEmailHandler
{
    public function __construct(private LoggerInterface $logger) {}

    public function __invoke(SendWelcomeEmail $message): void
    {
        // ... send the email ...
        $this->logger->info('Welcome email sent to {email}', ['email' => $message->email]);
    }
}
```

No `getHandledMessages()` boilerplate, no marker interface to implement (though `HandlerInterface` exists if you want one for static analysis). The attribute + `__invoke` is the contract.

`#[AsHandler]` supports three optional parameters:

| Argument | Purpose |
|---|---|
| `fromTransport` | Restrict this handler to messages received from a specific transport name. |
| `priority` | Higher priority handlers run first when multiple handlers match. |
| `method` | Override the method invoked (defaults to `__invoke`). |

## Configuring transports

Transport DSNs are parsed from environment variables prefixed `MESSENGER_TRANSPORT_*`. The suffix (lowercased) becomes the transport name.

```env
MESSENGER_TRANSPORT_DEFAULT=redis://localhost:6379/messages
MESSENGER_TRANSPORT_HIGH=doctrine://default?queue_name=high
MESSENGER_TRANSPORT_FAILED=doctrine://default?queue_name=failed
MESSENGER_FAILURE_TRANSPORT=failed
MESSENGER_ROUTING="App\Messages\SendWelcomeEmail:default,App\Messages\GenerateReport:high"
```

Routing is `MessageFqcn:transport[|transport]`, comma-separated. A message with no routing entry is handled inline by the bus (synchronous).

The transport bridges are loaded reflectively: `MessengerConfiguration` adds `SyncTransportFactory` and `InMemoryTransportFactory` unconditionally, and detects `RedisTransportFactory`, `DoctrineTransportFactory`, `AmqpTransportFactory`, and `BeanstalkdTransportFactory` at boot if their respective bridge packages are installed.

## Running workers

> **Host-application boot is required.** The framework's `bin/altair` only wires CLI discovery (`CliConfiguration`); it does **not** apply `MessengerConfiguration` on its behalf. Invoking `bin/altair worker` directly from a fresh framework checkout fails with `TransportSettings is not instantiable`, because nothing has registered the messenger delegates with the container. The same caveat applies to `bin/altair db:migrate` (which needs `CycleOrmConfiguration` applied first). The host application is expected to ship its own entry point that constructs the container, applies the configurations it uses, then hands off to `Altair\Cli\Application::run()`. A typical host entry looks like:
>
> ```php
> #!/usr/bin/env php
> <?php
> require __DIR__ . '/../vendor/autoload.php';
>
> use Altair\Cli\Application;
> use Altair\Cli\Configuration\CliConfiguration;
> use Altair\Configuration\Support\Env;
> use Altair\Container\Container;
> use Altair\Messaging\Configuration\MessengerConfiguration;
>
> $container = new Container();
> $container->share(new Env());
>
> (new CliConfiguration([__DIR__ . '/../app/Cli']))->apply($container);
> (new MessengerConfiguration([__DIR__ . '/../app/Messages']))->apply($container);
>
> exit($container->make(Application::class)->run());
> ```

```bash
bin/altair worker                          # consume every configured transport
bin/altair worker --transports=default,high
bin/altair worker --time-limit=3600        # exit after N seconds (systemd / k8s)
bin/altair worker --memory-limit=128M
bin/altair worker --limit=100              # exit after N messages
```

The worker installs PCNTL handlers for `SIGTERM` and `SIGINT` so a running worker drains its in-flight message before exiting. On platforms without PCNTL the worker still works but only stops on the configured `--limit` / `--time-limit` / `--memory-limit` thresholds.

Failed messages route to whatever transport is named by `MESSENGER_FAILURE_TRANSPORT`. Two helpers operate on that transport:

```bash
bin/altair worker:show-failed              # list envelopes currently held in the failure transport
bin/altair worker:retry-failed --limit=N   # drain and re-dispatch through the bus
```

`worker:retry-failed` strips the `SentToFailureTransportStamp` before re-dispatching so the message goes back through its original routing.

## Scaffolding messages from a spec

When `univeros/scaffold` parses a YAML spec, an optional `queue:` block adds message + handler + test emission alongside the HTTP artifacts:

```yaml
endpoint:
  method: POST
  path: /users
  tags: [users]
domain:
  class: App\User\CreateUser
queue:
  on_create:
    message: App\Messages\SendWelcomeEmail
    fields:
      user_id: string
      email: string
    transport: default
```

`bin/altair spec:scaffold api/users/create.yaml` now produces, for each `queue:` entry:

```
app/Messages/SendWelcomeEmail.php          # readonly message DTO
app/Messages/SendWelcomeEmailHandler.php   # #[AsHandler] handler stub with TODO body
tests/Messages/SendWelcomeEmailHandlerTest.php  # golden test
```

The HTTP-side artifacts (`CreateUserAction`, `CreateUserInput`, etc.) are emitted in the same pass. Re-runs are idempotent — existing files are skipped unless `--force` is passed.

## Architecture (one level deeper)

The bus is a `Symfony\Component\Messenger\MessageBus` with two middlewares:

1. **`SendMessageMiddleware`** consults `SendersLocator` (built from `MESSENGER_ROUTING`) to enqueue the message to its mapped transport(s). If no transport is mapped, the message passes through unchanged.
2. **`ContainerHandlerMiddleware`** (a tagged subtype of `HandleMessageMiddleware`) hands the envelope to `HandlerLocator`, which resolves handlers from `HandlerRegistry` and instantiates them through the framework's `Container`.

```
dispatch(message)
   ↓
SendMessageMiddleware  →  (if routed)  →  transport.send(envelope) → enqueued; return
   ↓
ContainerHandlerMiddleware
   ↓
HandlerLocator.getHandlers(envelope)
   ↓                                       ↳ filters by transport (#[AsHandler(fromTransport:'high')])
HandlerRegistry.handlersFor(MessageClass)  ↳ sorts by priority (descending)
   ↓
Container.make(HandlerClass)  →  $handler->__invoke($message)
```

Workers run via `WorkerFactory`, which builds a Symfony `Worker` with the configured receivers, bus, and event dispatcher. The `WorkerCommand` adds `StopWorkerOn*Listener`s and PCNTL signal handlers from CLI options.

## Test as documentation

- [tests/Messaging/Attribute/AsHandlerTest.php](../../tests/Messaging/Attribute/AsHandlerTest.php) — reading the `#[AsHandler]` attribute.
- [tests/Messaging/Discovery/AttributeHandlerDiscovererTest.php](../../tests/Messaging/Discovery/AttributeHandlerDiscovererTest.php) — filesystem scan + registry build.
- [tests/Messaging/Discovery/HandlerRegistryTest.php](../../tests/Messaging/Discovery/HandlerRegistryTest.php) — priority ordering, transport filtering.
- [tests/Messaging/HandlerLocatorTest.php](../../tests/Messaging/HandlerLocatorTest.php) — descriptor resolution through `Container`.
- [tests/Messaging/MessageBusTest.php](../../tests/Messaging/MessageBusTest.php) — wrapping the Symfony bus.
- [tests/Messaging/Configuration/TransportSettingsTest.php](../../tests/Messaging/Configuration/TransportSettingsTest.php) — env → settings parsing.
- [tests/Messaging/Configuration/MessengerConfigurationTest.php](../../tests/Messaging/Configuration/MessengerConfigurationTest.php) — end-to-end container wiring.
- [tests/Messaging/Integration/DispatchAndConsumeTest.php](../../tests/Messaging/Integration/DispatchAndConsumeTest.php) — sync dispatch + async InMemory transport + worker consume.
- [tests/Messaging/Middleware/LoggingMiddlewareTest.php](../../tests/Messaging/Middleware/LoggingMiddlewareTest.php) — optional logging middleware.

## Related packages

- [`univeros/cli`](cli.md) — provides the `#[Command]` attribute used by `WorkerCommand` and the failed-message commands.
- [`univeros/configuration`](configuration.md) — `ConfigurationInterface` + `Env`.
- [`univeros/container`](container.md) — handler resolution.
- [`univeros/scaffold`](scaffold.md) — `queue:` block parsing + emitters.
