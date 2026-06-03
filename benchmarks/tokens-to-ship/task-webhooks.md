# Frozen task variant — webhooks (inbound + outbound)

> Companion to [`task.md`](./task.md), [`task-import.md`](./task-import.md)
> and [`task-idempotency.md`](./task-idempotency.md). Measures what it
> costs each arm to deliver a *correctly signed and idempotent inbound
> webhook receiver* and a *retried and dead-letterable outbound
> dispatcher* — the canonical realistic third-party-integration task in
> 2026.

Inbound and outbound are **two separate fixtures and two separate
acceptance sections**. They measure different things; lumping them into
one number would average a misleading aggregate. Two rows in the
published table is the honest framing.

## Prompt given to the agent (verbatim)

### Inbound

> Add a **Stripe webhook receiver** at `POST /webhooks/stripe` that:
>
> - Verifies an HMAC-SHA256 signature carried in the `Stripe-Signature`
>   header against a shared secret. A tampered or absent signature is
>   rejected with `401`.
> - Reads a signed timestamp and rejects requests outside a 5-minute
>   window with `400` (replay protection).
> - Dedupes by event id for 24 hours: a redelivery of an already-seen
>   event returns `200` without re-processing.
>
> Stop when the acceptance suite passes.

### Outbound

> After a successful `POST /posts`, emit a signed `post.created`
> webhook to a configured subscriber. Sign with HMAC-SHA256, retry on
> `5xx` / network failure with backoff, and dead-letter after the
> configured maximum attempts. Provide a way to re-send a
> dead-lettered delivery.
>
> Stop when the acceptance suite passes.

## What changes between arms

- **Arm A — Altair.** Agent adds a `webhook:` block to the relevant
  spec (`direction: in` on the receiver, `direction: out` on
  `POST /posts`), re-runs `bin/altair spec:scaffold`. Inbound wires
  `ActionAwareWebhookVerifyMiddleware` from the generated Action's
  `webhook()` accessor; outbound wires a `WebhookDispatcher` binding.
  Storage adapters come from `WebhooksConfiguration` (`InMemory*` for
  the test run; Redis in a production deploy);
  `bin/altair webhook:show-failed` / `webhook:replay` cover the
  dead-letter loop. Done.
- **Arm B — Baseline.** Agent picks (or hand-rolls) a signing
  middleware, wires the timestamp window, writes the dedupe storage,
  builds the outbound dispatcher with retry + DLQ, wires replay
  tooling, and writes the tests. Reasonable baselines exist for the
  signing part (`paragonie/halite`, hand-rolled HMAC); the
  *integration cost* — wiring verification, dedupe, retry curves,
  dead-letter and replay into one coherent surface — is what is being
  measured.

## Fixtures

- [`fixtures/posts-webhook-in.openapi.yaml`](./fixtures/posts-webhook-in.openapi.yaml)
  — inbound: `POST /webhooks/stripe` carrying
  `x-altair-webhook: { direction: in, signing: hmac-sha256,
  secret_name: stripe, header: Stripe-Signature, dedupe_ttl: 24h }`.
- [`fixtures/posts-webhook-out.openapi.yaml`](./fixtures/posts-webhook-out.openapi.yaml)
  — outbound: `POST /posts` carrying
  `x-altair-webhook: { direction: out, signing: hmac-sha256,
  retry: { max_attempts: 8, backoff: linear, base_delay: 60s },
  dead_letter: webhook.deadletter }`.

Both fixtures round-trip clean through `bin/altair openapi:roundtrip`
and scaffold a working project through
`bin/altair openapi:import --scaffold`. The frozen documents carry only
non-default field values, matching the v1 wire contract: `direction`
and `signing` always travel; other fields round-trip only when they
differ from their default (see
[docs/guides/openapi/extensions.md](../../docs/guides/openapi/extensions.md)).

## Acceptance criteria

### Inbound (delta on the receiver)

- [ ] `POST /webhooks/stripe` with a valid HMAC + fresh event id →
      `200`.
- [ ] Replay with the same event id → `200` with
      `Webhook-Replayed: true`.
- [ ] Tampered signature → `401`.
- [ ] Timestamp outside the 5-minute window → `400`.

### Outbound (delta on `POST /posts`)

- [ ] Creating a post dispatches a signed POST to the configured
      subscriber (verified by a test fixture endpoint), carrying
      `X-Signature` / `X-Timestamp` / `X-Event-Id` / `X-Delivery-Id`.
- [ ] A failing subscriber (`5xx`) is retried with the configured
      backoff and eventually dead-lettered after `max_attempts`.
- [ ] `bin/altair webhook:replay <delivery-id>` re-dispatches a
      dead-lettered delivery.

## Reporting

Report this variant **separately** — and as **two rows**, inbound and
outbound. The forward task answers "given a prose spec, what does CRUD
cost?"; the import variant answers "what does translating an OpenAPI
contract cost?"; the idempotency variant answers "what does correct
idempotency cost?"; this variant answers **"what does a correct,
production-grade webhook integration cost — in each direction?"**

| Variant | Median tokens (Arm A / Arm B) | Median wallclock | pass@1 |
|---|---|---|---|
| [task.md](./task.md) | … / … | … / … | … |
| [task-import.md](./task-import.md) | … / … | … / … | … |
| [task-idempotency.md](./task-idempotency.md) | … / … | … / … | … |
| **task-webhooks.md — inbound** *(this)* | … / … | … / … | … |
| **task-webhooks.md — outbound** *(this)* | … / … | … / … | … |

The honest framing: "integrate with a third party's webhooks" is the
canonical realistic API task in 2026 — every payments / SaaS / commerce
platform ships both directions, and no PHP framework ships a native
primitive for either. The Univeros bet is that a spec block + built-in
signing / dedupe / dispatch + a round-trip-safe OpenAPI extension beats
hand-rolled-from-scratch and beats find-and-wire-three-packages, *and*
keeps beating them on the next change.
