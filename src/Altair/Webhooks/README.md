# univeros/webhooks

First-class webhook primitive for Univeros — both directions. **Inbound:** signature verification (HMAC-SHA256 / HMAC-SHA512 / Ed25519) + timestamp replay window + event-id dedupe, as a PSR-15 middleware. **Outbound:** a signed dispatcher over Symfony Messenger with retry, dead-letter and replay. Driven by a `webhook:` spec block and an `x-altair-webhook` OpenAPI 3.1 extension that round-trips the policy.

```yaml
# inbound
webhook:
  direction: in
  signing: hmac-sha256
  secret_name: stripe
  dedupe_ttl: 24h
  timestamp_window: 5m
```

```yaml
# outbound
webhook:
  direction: out
  signing: hmac-sha256
  retry: { max_attempts: 5, backoff: exponential }
  dead_letter: webhook.deadletter
```

Add the block to a spec → `bin/altair spec:scaffold` → the scaffolder wires the inbound `ActionAwareWebhookVerifyMiddleware` or the outbound `WebhookDispatcher` binding for the declared direction.

```bash
bin/altair webhook:show-failed          # list dead-lettered deliveries
bin/altair webhook:replay <delivery-id> # re-dispatch one
```

See **[docs/packages/webhooks.md](../../../docs/packages/webhooks.md)** for the full reference: signing primitives, storage adapters, both behaviour matrices, round-trip semantics, and host wiring.

## Composer

```bash
composer require univeros/webhooks
```

PHP 8.3+; depends on the PSR HTTP interfaces, `symfony/messenger` (outbound), and `univeros/configuration` + `univeros/container`. The Redis adapters need `ext-redis`; Ed25519 signing needs `ext-sodium` (omitted from `SignerRegistry::default()` when absent).
