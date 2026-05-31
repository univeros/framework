# Idempotency

> Stripe-style `Idempotency-Key` primitive. Spec-driven: a tiny block in the YAML spec wires a PSR-15 middleware that hashes the request body, claims the key in a pluggable store, replays the captured response on retry, and refuses 409 when the same key is reused with a different payload. Storage adapters cover InMemory (tests), APCu (single-host production), and Redis (multi-host production). Round-trips through OpenAPI 3.1 via `x-altair-idempotency` so the policy survives `openapi:emit` → `openapi:import` byte-for-byte.

**Composer:** `univeros/idempotency`
**Namespace:** `Altair\Idempotency`

## Introduction

PHP frameworks ship no native primitive for idempotency. Laravel has none. Symfony has none. Slim has none. Every team that needs the behaviour — and every team running a real payments / billing / ops surface eventually does — bolts on a middleware, picks a Redis schema, writes the storage code, wires the routes, and then hopes the next refactor doesn't quietly break replay semantics.

The agent-era problem is sharper. Agents retry mutating requests by reflex. Without an idempotency contract, the second attempt at `POST /payments` is a duplicate charge; the third attempt at `POST /jobs` is a re-dispatched message; the fourth attempt at `POST /users` is a duplicate row. With one, the agent can retry safely — the framework deduplicates by header, byte-comparing the request body to refuse drift, replaying the original response so the consumer sees the same outcome it would have seen on the first call.

This package ships the contract:

```yaml
idempotency:
  ttl: 24h
  scope: tenant
  mode: required
```

That YAML is the source of truth. Run `bin/altair spec:scaffold` and the generated Action carries a static `idempotency()` accessor exposing the policy. The host's `IdempotencyKeyMiddleware` reads it and builds the runtime behaviour per endpoint. No hand-wiring per route.

Three pieces make the design honest:

1. **Pluggable storage.** The `IdempotencyStoreInterface` has three contract operations — `claim`, `complete`, `release` — and the package ships three adapters: `InMemoryStore` for tests, `ApcuStore` for single-host production, `RedisStore` for multi-host. The atomic primitives are `apcu_add` and `SET key NX EX ttl` respectively; concurrent identical requests for the same key see exactly one execute and the others replay.
2. **No hand-rolled middleware.** The PSR-15 middleware (`IdempotencyKeyMiddleware`) handles the entire behaviour matrix — header presence + validation, body hash, claim coordination, in-progress wait, replay, conflict, error rollback, streaming skip — in one place. The spec-driven scaffolder reaches it via the static accessor on the generated Action.
3. **Round-trips through OpenAPI.** The `x-altair-idempotency` extension carries `ttl` and `scope` through OpenAPI 3.1 (see [docs/openapi/extensions.md](../openapi/extensions.md)); the round-trip drift gate (`bin/altair openapi:roundtrip`) refuses to merge a regression that drops the block.

What this package deliberately does **not** do:

- Webhook-specific idempotency. Webhooks have their own dedupe model (event id + delivery TTL) that belongs alongside the webhook framework, not here.
- Saga / multi-step idempotency. Single-request scope only in v1.
- Cross-region / multi-write replication of the store. Adapters target single-region clusters; multi-region is the host application's call.
- Response replay for streaming endpoints. The middleware skips the cache when the response advertises `transfer-encoding: chunked` or content-type `text/event-stream`.

## Installation

Standalone:

```bash
composer require univeros/idempotency
```

The package requires PHP 8.3+ and depends only on PSR HTTP interfaces (`psr/http-message`, `psr/http-factory`, `psr/http-server-middleware`) plus `univeros/configuration` + `univeros/container` for the DI wiring. The storage adapters' system requirements are declared as `suggest` rather than hard `require`:

- **`ApcuStore`** needs `ext-apcu` (CLI-enabled when used outside FPM).
- **`RedisStore`** needs `ext-redis` plus a reachable Redis instance.

## Quick start

### 1. Add the block to a spec

```yaml
endpoint:
  method: POST
  path: /payments
  summary: Create a payment
  tags: [payments]
input:
  amount:
    type: int
    rules: [required]
  currency:
    type: string
    rules: [required]
domain:
  class: App\Payment\CreatePayment
idempotency:
  ttl: 24h
  scope: tenant
  mode: required
```

### 2. Scaffold

```bash
bin/altair spec:scaffold api/payments/create.yaml
```

The generated `App\Http\Actions\CreatePaymentAction` exposes the policy:

```php
public static function idempotency(): array
{
    return ['ttl' => '24h', 'scope' => 'tenant', 'mode' => 'required'];
}
```

### 3. Wire the middleware (host)

Two lines. First, register `IdempotencyConfiguration` in the container chain so `IdempotencyStoreInterface` resolves:

```php
// config/configurations.php
return [
    // ...
    new \Altair\Idempotency\Configuration\IdempotencyConfiguration(),
];
```

That binds `IdempotencyStoreInterface` → `InMemoryStore` by default. Swap to Redis in production:

```php
$container->bind(\Altair\Idempotency\Contracts\IdempotencyStoreInterface::class)
    ->withFactory(static function (): \Altair\Idempotency\Storage\RedisStore {
        $redis = new \Redis();
        $redis->connect((string) getenv('REDIS_HOST') ?: '127.0.0.1', (int) (getenv('REDIS_PORT') ?: 6379));
        return new \Altair\Idempotency\Storage\RedisStore($redis);
    });
```

Second, add `ActionAwareIdempotencyMiddleware` to the middleware pipeline **after** `DispatcherMiddleware` (which publishes the resolved Action on the request) and **before** `ActionMiddleware` (which invokes it):

```php
$middleware->add(new \Altair\Idempotency\Middleware\ActionAwareIdempotencyMiddleware(
    store: $container->get(\Altair\Idempotency\Contracts\IdempotencyStoreInterface::class),
    responseFactory: $container->get(\Psr\Http\Message\ResponseFactoryInterface::class),
    streamFactory: $container->get(\Psr\Http\Message\StreamFactoryInterface::class),
));
```

That's the entire host wiring. The middleware reads each request's resolved Action via the `altair:http:action` attribute, looks for the static `idempotency()` accessor the scaffolder emits when a spec carries the `idempotency:` block, and configures a per-request `IdempotencyKeyMiddleware` with the spec's TTL and mode. Endpoints without the block see no behaviour change — the middleware passes them through.

#### Manual wiring (escape hatch)

For endpoints that need a different policy than the spec declares — say, forcing `mode: required` globally even when individual specs say `optional` — use `IdempotencyKeyMiddleware` directly:

```php
// Manual per-route wiring — only when you need to override the spec-driven policy.
$middleware->add(new \Altair\Idempotency\Middleware\IdempotencyKeyMiddleware(
    store: $container->get(\Altair\Idempotency\Contracts\IdempotencyStoreInterface::class),
    responseFactory: $container->get(\Psr\Http\Message\ResponseFactoryInterface::class),
    streamFactory: $container->get(\Psr\Http\Message\StreamFactoryInterface::class),
    ttlSeconds: 86_400,
    mode: \Altair\Idempotency\Middleware\IdempotencyKeyMiddleware::MODE_REQUIRED,
));
```

### 4. Use it

```bash
# First call — executes, caches, returns 201.
curl -X POST http://localhost:8080/payments \
  -H 'Idempotency-Key: pay_abc123' \
  -H 'Content-Type: application/json' \
  -d '{"amount":1000,"currency":"USD"}'

# HTTP/1.1 201 Created
# Content-Type: application/json
# {"id":"py_1"}

# Second call — same key, same body — replays.
curl -X POST http://localhost:8080/payments \
  -H 'Idempotency-Key: pay_abc123' \
  -H 'Content-Type: application/json' \
  -d '{"amount":1000,"currency":"USD"}'

# HTTP/1.1 201 Created
# Content-Type: application/json
# Idempotency-Replayed: true
# {"id":"py_1"}

# Third call — same key, different body — refuses.
curl -X POST http://localhost:8080/payments \
  -H 'Idempotency-Key: pay_abc123' \
  -H 'Content-Type: application/json' \
  -d '{"amount":2000,"currency":"USD"}'

# HTTP/1.1 409 Conflict
# Content-Type: application/json
# {"error":"Idempotency-Key reused with a different payload."}
```

The `Idempotency-Replayed: true` response header marks the second call as a cache hit so observability + agent loops can tell a fresh execution from a replay without inspecting state.

## Storage adapters

| Adapter | Atomic claim primitive | Use case | TTL handling |
|---|---|---|---|
| `InMemoryStore` | Process-local array + injectable clock | Tests, single-worker dev scripts | Soft expiry via injected clock |
| `ApcuStore` | `apcu_add` (insert-only) | Single-host production behind a single FPM pool | Native `apcu_store` TTL |
| `RedisStore` | `SET key value NX EX ttl` | Multi-host production / multi-region within a single Redis cluster | Native Redis TTL |

All three share the same contract:

```php
interface IdempotencyStoreInterface
{
    public function claim(string $key, string $requestHash, int $ttlSeconds): ?StoredResponse;
    public function complete(string $key, StoredResponse $response, int $ttlSeconds): void;
    public function release(string $key): void;
    public function get(string $key): ?StoredResponse;
}
```

`claim()` is the atomic primitive: `null` means the caller now owns the key and must execute; a `StoredResponse` means the key was already claimed (in-progress or completed, distinguished by `inProgress`). All three adapters guarantee atomicity on conforming backends; `ApcuStore` throws at construction time when `ext-apcu` is unavailable rather than silently degrading.

The constructor for `RedisStore` accepts a pre-configured `\Redis` client so connection lifecycle (pooling, reconnection, authentication) stays the host's responsibility.

Both `ApcuStore` and `RedisStore` expose a configurable `keyPrefix` (default `altair.idem.`) so multiple applications sharing one backend don't collide.

## Behaviour matrix

The middleware handles every meaningful state in one place:

| Situation | Response |
|---|---|
| `GET` / `HEAD` / `OPTIONS` | Pass through; no caching. |
| Header absent, `mode=optional` | Pass through; no caching. |
| Header absent, `mode=required` | `400 Bad Request` with `{error}` envelope. |
| Header malformed (`>255` chars, control chars, whitespace) | `400 Bad Request`. |
| Key unseen | Claim; execute handler; cache response; return. |
| Key seen, same hash, completed | Replay cached response + `Idempotency-Replayed: true` header. |
| Key seen, same hash, in-progress, ≤ maxWait | Wait + retry; replay when ready. |
| Key seen, same hash, in-progress, > maxWait | `409 Conflict`. |
| Key seen, different hash | `409 Conflict`. |
| Handler throws | Release claim; re-throw. Next attempt starts fresh. |
| Streaming response (`chunked` or `text/event-stream`) | Pass through without caching. |

Response headers are stored on an **allow-list** basis (default `Content-Type`, `Location`, `Link`) so that sensitive headers — `Set-Cookie`, `Authorization`, anything not on the list — never end up in shared storage. This is verified by test and is the package's strictest invariant.

## Round-trip via OpenAPI

When a spec carries `idempotency:`, the forward emitter (`spec:emit-openapi`) writes:

```yaml
x-altair-idempotency:
  ttl: 24h
  scope: tenant
```

on the corresponding operation. The reverse importer (`openapi:import`) reads it back and emits an equivalent `idempotency:` block. `ttl` and `scope` round-trip byte-for-byte; `mode` is a server-side enforcement concern and defaults to `optional` on the reverse path.

The drift gate (`openapi:roundtrip`) compares the extension on both sides; a regression that drops the block produces a `kind: extension_drift` entry and fails CI in `--check` mode.

See [docs/openapi/extensions.md](../openapi/extensions.md) for the full extension contract and [docs/openapi/roundtrip.md](../openapi/roundtrip.md) for the gate.

## What does not round-trip yet

- **Webhook-specific idempotency**. Webhooks dedupe by event id + delivery TTL, which is a different model. Lands alongside the webhook framework epic.
- **Saga / multi-step idempotency**. The current contract is one-request, one-key. Multi-request workflows (split a payment authorisation from its capture, for example) need an explicit transaction-id concept that exceeds the scope of this primitive.
- **Cross-region replication**. The storage adapters target single-region clusters. Multi-region read-your-writes consistency is a host-application concern.
- **Streaming responses**. The middleware skips caching for chunked and SSE responses; replay semantics for partially-delivered streams are deliberately undefined.

## Testing your idempotent endpoints

Use `InMemoryStore` in tests so each test starts with a clean cache:

```php
$store = new \Altair\Idempotency\Storage\InMemoryStore();
$middleware = new \Altair\Idempotency\Middleware\IdempotencyKeyMiddleware(
    store: $store,
    responseFactory: new \Laminas\Diactoros\ResponseFactory(),
    streamFactory: new \Laminas\Diactoros\StreamFactory(),
    ttlSeconds: 60,
);
```

For tests that exercise the TTL boundary, inject a fake clock:

```php
$now = 1_700_000_000;
$store = new \Altair\Idempotency\Storage\InMemoryStore(static fn(): int => $now);
// ... claim, then advance $now beyond TTL, assert the next claim succeeds.
```

The framework's own test suite (e.g. `tests/Idempotency/Middleware/IdempotencyKeyMiddlewareTest.php`) is the canonical reference for the behaviour matrix and a copy-paste source for application tests.

## See also

- [#171](https://github.com/univeros/framework/issues/171) — epic
- [#172](https://github.com/univeros/framework/issues/172) — storage contract + adapters
- [#173](https://github.com/univeros/framework/issues/173) — middleware
- [#174](https://github.com/univeros/framework/issues/174) — spec block + scaffolder
- [#175](https://github.com/univeros/framework/issues/175) — `x-altair-idempotency` round-trip activation
- [docs/openapi/extensions.md](../openapi/extensions.md) — the OpenAPI extension family
- [docs/openapi/roundtrip.md](../openapi/roundtrip.md) — the drift gate
