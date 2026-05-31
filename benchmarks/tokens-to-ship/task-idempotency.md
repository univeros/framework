# Frozen task variant — Posts API with idempotency

> Companion to [`task.md`](./task.md) and [`task-import.md`](./task-import.md).
> Adds an `Idempotency-Key` requirement to the Posts API so the
> benchmark can measure what it costs each arm to deliver a *correctly
> idempotent* endpoint — the canonical realistic scenario for any
> mutating API in 2026.

## Prompt given to the agent (verbatim)

> Add a **Posts** REST resource to the application with the endpoints
> from [`task.md`](./task.md), **plus** the following requirement:
>
> - `POST /posts` and `PUT /posts/{id}` accept an `Idempotency-Key`
>   request header. A second call with the same key and the same
>   request body returns the originally-recorded response. A second
>   call with the same key and a *different* body returns `409
>   Conflict`. A call without the header is rejected with `400 Bad
>   Request` (the `mode: required` policy).
>
> The idempotency cache must survive for at least 24 hours per key.
> The default storage backend can be whatever the project ships; a
> production deployment would swap to Redis.
>
> Stop when the acceptance suite passes.

## What changes between arms

- **Arm A — Altair.** Agent adds `idempotency: { ttl: 24h, mode: required }`
  to the spec, re-runs `bin/altair spec:scaffold`. The generated
  Action exposes the policy via the static `idempotency()` accessor;
  the host's `IdempotencyKeyMiddleware` reads it. Storage adapter is
  picked via `IdempotencyConfiguration` (`InMemoryStore` for the
  test run; `RedisStore` in a production deploy).
- **Arm B — Baseline.** Agent picks (or hand-rolls) a middleware,
  wires the storage adapter, writes the request-body hash, threads
  the middleware into the route's pipeline, adds tests for the
  replay / 409 / 400 paths. Reasonable baselines: the
  [`league/idempotent-request`](https://github.com/thephpleague/idempotent-request)
  family of community packages — they exist, they're well-considered,
  and the cost being measured is the *integration cost* against the
  hand-build baseline.

## Fixture

[`fixtures/posts-idempotent.openapi.yaml`](./fixtures/posts-idempotent.openapi.yaml)
— same Posts shape as the import variant, with `x-altair-idempotency`
on the mutating operations:

```yaml
/posts:
  post:
    x-altair-idempotency:
      ttl: 24h
      scope: tenant
    # ...
/posts/{id}:
  put:
    x-altair-idempotency:
      ttl: 24h
      scope: tenant
    # ...
```

The frozen document captures the v1 contract: `ttl` + `scope` on the
wire, `mode` server-side (set to `required` by the host scaffold). A
future v2 of the fixture will add `mode` to the extension once it
becomes part of the wire contract.

## Acceptance criteria (delta from [task.md](./task.md))

Adds two checks to the suite. Everything else from `task.md` stays
identical:

- [ ] `POST /posts` with valid body **and** `Idempotency-Key: abc` →
      `201` with `Idempotency-Replayed: false` (or absent) on first
      call; same body returned on second call with the same key, this
      time with `Idempotency-Replayed: true`.
- [ ] `POST /posts` with the same `Idempotency-Key: abc` but a
      different body → `409`.
- [ ] `POST /posts` *without* `Idempotency-Key` → `400` with
      `{error}` envelope describing the missing header.
- [ ] `PUT /posts/{id}` enforces the same three checks.

## Reporting

Report this variant **separately**. The forward task answers "given a
prose spec, what does the framework cost to ship CRUD?"; the import
variant answers "given an OpenAPI contract, what does translation
cost?"; this variant answers **"what does correct idempotency cost?"**

Both numbers should be in the published table:

| Variant | Median tokens (Arm A / Arm B) | Median wallclock | pass@1 |
|---|---|---|---|
| [task.md](./task.md) | … / … | … / … | … |
| [task-import.md](./task-import.md) | … / … | … / … | … |
| **task-idempotency.md** *(this)* | … / … | … / … | … |

The honest framing: "ship correctly idempotent CRUD" is the canonical
realistic API task in 2026. Any framework that claims production-ready
status should be measurable here. The Univeros bet is that a spec
block + a built-in middleware + a round-trip-safe OpenAPI extension
beats hand-rolled-from-scratch and beats find-and-wire-a-third-party,
*and* keeps beating them on the next change.
