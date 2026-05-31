# univeros/idempotency  ·  Altair\Idempotency

**Purpose:** Stripe-style Idempotency-Key primitive for Univeros: storage contract, adapters, and (in companion packages) PSR-15 middleware + spec block.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `IdempotencyStoreInterface` | `claim(string, string, int)` | `StoredResponse\|null` |  |
|  | `complete(string, StoredResponse, int)` | `void` |  |
|  | `get(string)` | `StoredResponse\|null` |  |
|  | `release(string)` | `void` |  |

## Concrete classes

- `ApcuStore` _(final)_ — implements `IdempotencyStoreInterface`
- `IdempotencyConfiguration` _(final)_ — implements `ConfigurationInterface`
- `IdempotencyKeyMiddleware` _(final)_ — implements `MiddlewareInterface`
- `InMemoryStore` _(final)_ — implements `IdempotencyStoreInterface`
- `RedisStore` _(final)_ — implements `IdempotencyStoreInterface`
- `RequestBodyHasher`
- `StoredResponse` _(final)_

## Tests as documentation

- `tests/Idempotency/Hash/RequestBodyHasherTest.php`
- `tests/Idempotency/Middleware/IdempotencyKeyMiddlewareTest.php`
- `tests/Idempotency/Storage/ApcuStoreTest.php`
- `tests/Idempotency/Storage/InMemoryStoreTest.php`
- `tests/Idempotency/Storage/RedisStoreTest.php`
- `tests/Idempotency/Storage/StoredResponseTest.php`

## Related packages

- `psr/http-factory`
- `psr/http-message`
- `psr/http-server-handler`
- `psr/http-server-middleware`
- `univeros/configuration`
- `univeros/container`
