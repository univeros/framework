# Webhooks

> First-class webhook primitive — both directions. **Inbound:** a spec block wires a PSR-15 middleware that verifies an HMAC / Ed25519 signature, enforces a timestamp replay window, and dedupes by event id in a pluggable store. **Outbound:** a `WebhookDispatcher` signs the payload, dispatches asynchronously over Symfony Messenger, retries failed deliveries with exponential / linear backoff, dead-letters after `max_attempts`, and exposes `bin/altair webhook:replay <id>` to re-send. Round-trips through OpenAPI 3.1 via `x-altair-webhook` so the policy survives `spec:emit-openapi` → `openapi:import` byte-for-byte.

**Composer:** `univeros/webhooks`
**Namespace:** `Altair\Webhooks`

## Introduction

PHP frameworks ship no native primitive for webhooks. Laravel has none. Symfony has none. Slim has none. Yet every API that talks to another system eventually needs them — Stripe, GitHub, Slack, Twilio, Shopify and Square all ship both inbound *and* outbound webhook contracts. Teams either roll their own (and re-invent HMAC verification, timestamp-window protection, idempotent delivery, retry curves and dead-letter semantics) or stitch together three uncoordinated community packages.

The agent-era cost is sharper. An agent asked to integrate Stripe's webhooks has to discover the signing scheme, find a verification library, wire the timestamp check, dedupe deliveries and write replay handling — all from spec-less prose. An agent asked to *emit* webhooks for a third-party integration has to invent retry curves and dead-letter behaviour from scratch.

This package ships the contract for both directions:

```yaml
# inbound — verify what arrives
webhook:
  direction: in
  signing: hmac-sha256
  secret_name: stripe
  dedupe_ttl: 24h
  timestamp_window: 5m
```

```yaml
# outbound — sign and dispatch what you emit
webhook:
  direction: out
  signing: hmac-sha256
  retry:
    max_attempts: 5
    backoff: exponential
  dead_letter: webhook.deadletter
```

That YAML is the source of truth. Run `bin/altair spec:scaffold` and the scaffolder wires the right artifacts for the declared direction. No hand-rolled signing middleware, no invented retry curve.

Four pieces make the design honest:

1. **Multi-scheme signing.** `SignerInterface` has three operations — `name`, `sign`, `verify` — and the package ships `HmacSha256Signer`, `HmacSha512Signer` and `Ed25519Signer`. HMAC signatures are hex-encoded to match Stripe / GitHub; `verify()` is constant-time (`hash_equals` / libsodium) and tolerantly parses the Stripe `t=<ts>,v1=<hex>` header format as well as a bare hex digest.
2. **Pluggable storage.** Inbound dedupe (`InboundDeduplicatorInterface`) and outbound delivery state (`DeliveryStoreInterface`) each ship an `InMemory` adapter for tests and a `Redis` adapter for production. The inbound dedupe primitive is `SET key value NX EX ttl` — concurrent identical deliveries see exactly one handler invocation.
3. **No hand-rolled dispatcher.** `WebhookDispatcher` records a `Delivery`, dispatches a `WebhookMessage` over Symfony Messenger, and `WebhookHandler` performs the signed POST with retry + dead-letter. Delivery state (id, attempts, last response, status) is persisted so `webhook:replay` works.
4. **Round-trips through OpenAPI.** The `x-altair-webhook` extension carries the policy through OpenAPI 3.1 (see [docs/openapi/extensions.md](../openapi/extensions.md)); the round-trip drift gate (`bin/altair openapi:roundtrip`) refuses to merge a regression that drops the block.

What this package deliberately does **not** do:

- **Webhook subscription management.** Listing / adding / removing subscribers is a host-application concern; the framework provides the dispatch primitive, not the admin surface.
- **Cross-region replication of the dedupe / delivery store.** Adapters target single-region clusters; multi-region is the host's call.
- **WebSocket / SSE signing.** Different transport, different semantics.
- **Signature scheme negotiation.** The spec block declares a single fixed scheme; mixed-scheme support waits until a real consumer asks for it.

## Installation

Standalone:

```bash
composer require univeros/webhooks
```

The package requires PHP 8.3+ and depends only on the PSR HTTP interfaces (`psr/http-message`, `psr/http-factory`, `psr/http-server-middleware`), `symfony/messenger` (outbound dispatch) and `univeros/configuration` + `univeros/container` for DI wiring. The Redis adapters need `ext-redis` plus a reachable Redis instance; Ed25519 signing needs `ext-sodium` (the `Ed25519Signer` throws at construction when it is absent, and `SignerRegistry::default()` simply omits it).

Secrets resolve from the environment by default:

```bash
# EnvSecretResolver maps secret_name 'stripe' → WEBHOOK_SECRET_STRIPE
WEBHOOK_SECRET_STRIPE=whsec_xxx
WEBHOOK_SECRET_PARTNER_X=...      # secret_name 'partner-x' folds non-alphanumerics to '_'
```

## Quick start — inbound

### 1. Add the block to a spec

```yaml
endpoint:
  method: POST
  path: /webhooks/stripe
  summary: Receive Stripe events
  tags: [webhooks]
domain:
  class: App\Webhook\ReceiveStripe
webhook:
  direction: in
  signing: hmac-sha256
  secret_name: stripe
  header: Stripe-Signature
  dedupe_ttl: 24h
  timestamp_window: 5m
```

### 2. Scaffold

```bash
bin/altair spec:scaffold api/webhooks/stripe.yaml
```

The generated Action exposes the policy via a static `webhook()` accessor, which the `ActionAwareWebhookVerifyMiddleware` reads per request.

### 3. Wire the middleware (host)

Register `WebhooksConfiguration` so the signer registry, secret resolver and dedupe store resolve, then add the auto-wiring middleware **after** `DispatcherMiddleware` (which publishes the resolved Action) and **before** `ActionMiddleware` (which invokes it):

```php
// config/configurations.php
return [
    // ...
    new \Altair\Webhooks\Configuration\WebhooksConfiguration(),
];
```

```php
$middleware->add(new \Altair\Webhooks\Middleware\ActionAwareWebhookVerifyMiddleware(
    signers: $container->get(\Altair\Webhooks\Signing\SignerRegistry::class),
    secrets: $container->get(\Altair\Webhooks\Contracts\SecretResolverInterface::class),
    deduplicator: $container->get(\Altair\Webhooks\Contracts\InboundDeduplicatorInterface::class),
    responseFactory: $container->get(\Psr\Http\Message\ResponseFactoryInterface::class),
    streamFactory: $container->get(\Psr\Http\Message\StreamFactoryInterface::class),
));
```

Endpoints without a `webhook: { direction: in }` block are passed straight through.

### 4. Use it

```bash
# Valid signature, fresh event id → handler runs (201).
curl -X POST http://localhost:8080/webhooks/stripe \
  -H 'Stripe-Signature: <hex hmac-sha256 of the raw body>' \
  -H 'X-Timestamp: 1700000000' \
  -H 'X-Event-Id: evt_123' \
  -d '{"type":"payment_intent.succeeded"}'
# HTTP/1.1 201 Created

# Same event id again → absorbed without re-processing.
# HTTP/1.1 200 OK
# Webhook-Replayed: true

# Tampered signature → rejected.
# HTTP/1.1 401 Unauthorized
# {"error":"webhook signature verification failed"}

# Timestamp outside the 5m window → rejected.
# HTTP/1.1 400 Bad Request
```

When the `X-Event-Id` header is absent the middleware synthesises a stable id from `sha256(body + timestamp)`, so dedupe still works for senders that don't supply one.

## Quick start — outbound

### 1. Declare it, or dispatch directly

A `webhook: { direction: out }` block on a creating endpoint wires a `WebhookDispatcher` binding; application code emits through it:

```php
$dispatcher = $container->get(\Altair\Webhooks\Dispatcher\WebhookDispatcher::class);

$dispatcher->dispatch(
    eventName: 'post.created',
    payload: ['id' => $post->id, 'title' => $post->title],
    subscriberUrl: 'https://subscriber.example/hooks',
    secretName: 'partner-x',
    signerName: 'hmac-sha256',          // optional; defaults to hmac-sha256
);
```

`dispatch()` records a `Pending` `Delivery` and puts a `WebhookMessage` on the bus. Outbound dispatch needs `Symfony\Component\Messenger\MessageBusInterface` bound (apply `MessengerConfiguration` alongside `WebhooksConfiguration`).

### 2. Consume

```bash
bin/altair worker                       # the WebhookHandler signs + POSTs each delivery
```

`WebhookHandler` adds these headers to the outbound POST:

| Header | Value |
|---|---|
| `Content-Type` | `application/json` |
| `X-Signature` | signature of the payload under the chosen scheme + secret |
| `X-Timestamp` | unix timestamp at send time |
| `X-Event-Id` | the delivery id (ULID) |
| `X-Delivery-Id` | the delivery id (ULID) |

### 3. Inspect + replay failures

```bash
bin/altair webhook:show-failed          # list dead-lettered deliveries, oldest first
bin/altair webhook:replay <delivery-id> # re-dispatch one (accepts an unambiguous id prefix)
```

`webhook:replay` resets the delivery to `Pending` (attempts 0) and puts a fresh `WebhookMessage` back on the bus.

## Signing primitives

| Scheme | Class | Output | Notes |
|---|---|---|---|
| `hmac-sha256` | `HmacSha256Signer` | hex HMAC-SHA256 | Default. Matches Stripe / GitHub. |
| `hmac-sha512` | `HmacSha512Signer` | hex HMAC-SHA512 | Stronger digest, same wire shape. |
| `ed25519` | `Ed25519Signer` | hex detached signature | Asymmetric: `sign()` takes the hex 64-byte secret key, `verify()` the hex 32-byte public key. Needs `ext-sodium`. |

All implement `SignerInterface`:

```php
interface SignerInterface
{
    public function name(): string;                                          // 'hmac-sha256', ...
    public function sign(string $payload, string $secret): string;           // hex
    public function verify(string $payload, string $signature, string $secret): bool; // constant-time
}
```

`verify()` returns `false` rather than throwing on mismatch, and the HMAC signers accept either a bare hex digest or a Stripe-style `t=<ts>,v1=<hex>` header (the `v1=` component is extracted). Resolve a scheme by name through the registry:

```php
$registry = \Altair\Webhooks\Signing\SignerRegistry::default(); // HMAC always; Ed25519 when ext-sodium is loaded
$signer = $registry->get('hmac-sha256');
```

### Secret resolution

`SecretResolverInterface::resolve(string $name): string` turns a `secret_name` into the actual secret. `EnvSecretResolver` reads `WEBHOOK_SECRET_<NAME>` (configurable prefix; non-alphanumerics in the name fold to `_`) and throws `WebhookException::missingSecret()` when unset. Bind your own implementation to back secrets with a KMS / secret manager — the secret value never travels through OpenAPI, only the `secret_name` lookup key does.

## Storage adapters

| Concern | InMemory | Redis |
|---|---|---|
| Inbound dedupe | `InMemoryDeduplicator` (tests, single-worker dev) | `RedisDeduplicator` — atomic `SET … NX EX ttl`, key prefix `webhook:dedupe:` |
| Outbound delivery state | `InMemoryDeliveryStore` (tests) | `RedisDeliveryStore` — serialized at `webhook:delivery:<id>`, dead-letter index as a sorted set scored by `createdAt` |

The Redis adapters take a pre-configured `\Redis` client so connection lifecycle stays the host's responsibility. `WebhooksConfiguration` binds the InMemory adapters by default; swap to Redis by re-binding in your own Configuration:

```php
$container->factory(
    \Altair\Webhooks\Contracts\DeliveryStoreInterface::class,
    static function (): \Altair\Webhooks\Storage\RedisDeliveryStore {
        $redis = new \Redis();
        $redis->connect((string) (getenv('REDIS_HOST') ?: '127.0.0.1'), (int) (getenv('REDIS_PORT') ?: 6379));
        return new \Altair\Webhooks\Storage\RedisDeliveryStore($redis);
    },
);
```

## Behaviour matrix — inbound

`WebhookVerifyMiddleware` handles every meaningful state in one place. Defaults: `dedupe_ttl` 1h, `timestamp_window` 5m.

| Situation | Response |
|---|---|
| Signature header absent | `401 Unauthorized` (opaque `{error}` envelope) |
| Signature mismatch / secret missing | `401 Unauthorized` (opaque — never leak which check failed) |
| Timestamp header absent (`requireTimestamp=true`) | `400 Bad Request` |
| Timestamp non-numeric | `400 Bad Request` |
| Timestamp outside the window (past or future) | `400 Bad Request` |
| Event id already claimed within TTL | `200 OK`, empty body, `Webhook-Replayed: true` |
| Fresh event, handler succeeds | Handler's response (e.g. `201`) |
| Fresh event, handler throws | Claim released; exception re-thrown so retry is re-processed |
| Fresh event, handler returns `5xx` | `5xx`; claim released so the sender's retry is re-processed |

The request body is read for verification and then re-streamed from position 0 so the downstream handler sees the full payload. Dedupe is claim-once: the first caller wins, later identical deliveries within the TTL are absorbed with `200 OK`.

## Behaviour matrix — outbound

`WebhookHandler` drives delivery state through the `RetryPolicy` (defaults: `max_attempts` 5, `exponential` backoff, `base_delay` 30s).

| Situation | Delivery status | Messenger action |
|---|---|---|
| `2xx` response | `Delivered` (`nextAttemptAt` cleared) | message acknowledged |
| `4xx` response | `DeadLettered` immediately | `UnrecoverableMessageHandlingException` → failure transport |
| `5xx` / network error, attempt < `max_attempts` | `Failed` (`nextAttemptAt` scheduled) | `RecoverableMessageHandlingException` → redelivered after the backoff delay |
| `5xx` / network error, attempt ≥ `max_attempts` | `DeadLettered` | `UnrecoverableMessageHandlingException` → failure transport |
| delivery row missing | — | `UnrecoverableMessageHandlingException` (not retried) |

Backoff delay before the *n*-th attempt: exponential = `base_delay × 2^(n-1)` (30s, 60s, 120s, 240s…), linear = `base_delay × n` (30s, 60s, 90s…). A `4xx` is treated as a permanent rejection and dead-letters without burning the retry budget; only `5xx` and transport-level failures are retried.

## Auto-wiring

`ActionAwareWebhookVerifyMiddleware` reads the resolved Action from the request attribute (`altair:http:action`). When that Action exposes a static `webhook()` accessor with `direction: in`, the middleware builds a per-request `WebhookVerifyMiddleware` from the policy (signer, secret name, dedupe TTL, timestamp window, header names — durations parsed by `DurationParser`). It passes through when there is no Action, no `webhook()` accessor, or the policy is outbound. This is the inbound equivalent of `ActionAwareIdempotencyMiddleware` (see [idempotency.md](./idempotency.md)).

## Round-trip via OpenAPI

When a spec carries `webhook:`, the forward emitter (`spec:emit-openapi`) writes an `x-altair-webhook` block on the operation; the reverse importer (`openapi:import`) reconstructs the `webhook:` block. `direction` and `signing` always travel; every other field is written only when it differs from its default, and the importer re-applies those defaults — so the block is byte-stable across the round-trip. The shared secret itself never appears in OpenAPI; only `secret_name` carries through.

The drift gate (`openapi:roundtrip`) compares `x-altair-webhook` on both sides; a regression that drops or changes the block produces a `kind: extension_drift` entry and fails CI in `--check` mode.

See [docs/openapi/extensions.md](../openapi/extensions.md) for the extension contract and [docs/openapi/roundtrip.md](../openapi/roundtrip.md) for the gate.

## What is not yet supported

- **Subscription management UI.** Listing / adding / removing subscribers is a host concern.
- **Cross-region replication** of the dedupe / delivery store. Adapters target single-region clusters.
- **WebSocket / SSE signing.** Different transport, different semantics.
- **Signature scheme negotiation.** The spec block declares one fixed scheme.

## See also

- [#184](https://github.com/univeros/framework/issues/184) — epic
- [#185](https://github.com/univeros/framework/issues/185) — storage contracts + signers + adapters
- [#186](https://github.com/univeros/framework/issues/186) — inbound verify middleware
- [#187](https://github.com/univeros/framework/issues/187) — outbound dispatcher + retry / dead-letter / replay
- [#188](https://github.com/univeros/framework/issues/188) — `webhook:` spec block + scaffolder
- [#189](https://github.com/univeros/framework/issues/189) — `x-altair-webhook` round-trip activation
- [docs/openapi/extensions.md](../openapi/extensions.md) — the OpenAPI extension family
- [docs/openapi/roundtrip.md](../openapi/roundtrip.md) — the drift gate
