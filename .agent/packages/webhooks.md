# univeros/webhooks  ·  Altair\Webhooks

**Purpose:** First-class webhook framework for Univeros: signing primitives, inbound verify middleware, and an outbound dispatcher with retry / dead-letter / replay.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `DeliveryStoreInterface` | `findById(string)` | `Delivery\|null` |  |
|  | `findFailed(int)` | `array` |  |
|  | `record(Delivery)` | `void` |  |
|  | `update(Delivery)` | `void` |  |
| `InboundDeduplicatorInterface` | `claim(string, int)` | `bool` |  |
|  | `release(string)` | `void` |  |
| `SecretResolverInterface` | `resolve(string)` | `string` |  |
| `SignerInterface` | `name()` | `string` |  |
|  | `sign(string, string)` | `string` |  |
|  | `verify(string, string, string)` | `bool` |  |

## Concrete classes

- `AbstractHmacSigner` _(abstract)_ — implements `SignerInterface`
- `ActionAwareWebhookVerifyMiddleware` _(final)_ — implements `MiddlewareInterface`
- `Delivery` _(final)_
- `DeliveryStatus` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `DurationParser` _(final)_
- `Ed25519Signer` _(final)_ — implements `SignerInterface`
- `EnvSecretResolver` _(final)_ — implements `SecretResolverInterface`
- `HmacSha256Signer` _(final)_ — implements `SignerInterface`
- `HmacSha512Signer` _(final)_ — implements `SignerInterface`
- `InMemoryDeduplicator` _(final)_ — implements `InboundDeduplicatorInterface`
- `InMemoryDeliveryStore` _(final)_ — implements `DeliveryStoreInterface`
- `RedisDeduplicator` _(final)_ — implements `InboundDeduplicatorInterface`
- `RedisDeliveryStore` _(final)_ — implements `DeliveryStoreInterface`
- `RetryPolicy` _(final)_
- `SignerRegistry` _(final)_
- `WebhookDispatcher` _(final)_
- `WebhookHandler` _(final)_
- `WebhookMessage` _(final)_
- `WebhookReplayCommand` _(final)_ — implements `SignalableCommandInterface`
- `WebhookShowFailedCommand` _(final)_ — implements `SignalableCommandInterface`
- `WebhookVerifyMiddleware` _(final)_ — implements `MiddlewareInterface`

## Tests as documentation

- `tests/Webhooks/Cli/WebhookReplayCommandTest.php`
- `tests/Webhooks/Cli/WebhookShowFailedCommandTest.php`
- `tests/Webhooks/Dispatcher/RetryPolicyTest.php`
- `tests/Webhooks/Dispatcher/WebhookDispatcherTest.php`
- `tests/Webhooks/Dispatcher/WebhookHandlerTest.php`
- `tests/Webhooks/Middleware/ActionAwareWebhookVerifyMiddlewareTest.php`
- `tests/Webhooks/Middleware/WebhookVerifyMiddlewareTest.php`
- `tests/Webhooks/Signing/Ed25519SignerTest.php`
- `tests/Webhooks/Signing/EnvSecretResolverTest.php`
- `tests/Webhooks/Signing/HmacSha256SignerTest.php`
- `tests/Webhooks/Signing/HmacSha512SignerTest.php`
- `tests/Webhooks/Signing/SignerRegistryTest.php`
- `tests/Webhooks/Storage/DeliveryTest.php`
- `tests/Webhooks/Storage/InMemoryDeduplicatorTest.php`
- `tests/Webhooks/Storage/InMemoryDeliveryStoreTest.php`
- `tests/Webhooks/Storage/RedisDeduplicatorTest.php`
- `tests/Webhooks/Storage/RedisDeliveryStoreTest.php`

## Related packages

- `psr/http-client`
- `psr/http-factory`
- `psr/http-message`
- `psr/http-server-handler`
- `psr/http-server-middleware`
- `univeros/configuration`
- `univeros/container`
- `univeros/messaging`
