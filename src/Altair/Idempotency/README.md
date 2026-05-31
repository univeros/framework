# univeros/idempotency

Stripe-style `Idempotency-Key` primitive for Univeros: storage contract, adapters (InMemory / APCu / Redis), PSR-15 middleware, spec block, and an `x-altair-idempotency` OpenAPI 3.1 extension that round-trips the policy.

```yaml
idempotency:
  ttl: 24h
  scope: tenant
  mode: required
```

Add the block to a spec → `bin/altair spec:scaffold` → the generated Action carries the policy → the host's `IdempotencyKeyMiddleware` reads it and enforces it per request.

See **[docs/packages/idempotency.md](../../../docs/packages/idempotency.md)** for the full reference: behaviour matrix, storage adapter trade-offs, round-trip semantics, host wiring.

## Composer

```bash
composer require univeros/idempotency
```

PHP 8.3+; depends only on PSR HTTP interfaces plus `univeros/configuration` + `univeros/container`. Adapter-specific extensions (`ext-apcu`, `ext-redis`) are declared as `suggest` rather than `require`.
