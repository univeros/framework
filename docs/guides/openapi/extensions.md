# `x-altair-*` OpenAPI extensions

> The set of OpenAPI 3.1 specification extensions Univeros uses to carry
> framework-specific concerns that the base spec cannot express. Forward
> emit (`spec:emit-openapi`) writes them; reverse import
> (`openapi:import`) reads them back. The round-trip preserves
> persistence, queue, and domain identity that would otherwise be lost.

**Schemas:** [`docs/openapi/extensions/`](./extensions/)
**Issue:** [#163](https://github.com/univeros/framework/issues/163) ·
epic [#160](https://github.com/univeros/framework/issues/160)

## Why an extension family

OpenAPI 3.1 describes the wire shape — what the request looks like, what
each response looks like, what each status means. It deliberately does
not describe how that shape is satisfied: which class handles the
request, which entity persists it, which message gets dispatched after.

For Univeros those are first-class spec fields. If a round trip through
OpenAPI silently dropped them, the only safe import workflow would be
"import then hand-rewrite," and the import path stops being useful for
adoption. `x-altair-*` is the OpenAPI-idiomatic way out: unknown `x-*`
keys are explicitly permitted by the spec and ignored by tooling that
doesn't recognise them, so adding ours doesn't break the document for
non-Univeros consumers.

## The v1 keys

All keys live at the **operation** level (under
`paths.<path>.<method>`).

| Key | Round-trips | Schema |
|---|---|---|
| `x-altair-domain` | Yes — `spec.domain.{class, invocation}` | [x-altair-domain.schema.json](./extensions/x-altair-domain.schema.json) |
| `x-altair-persistence` | Yes — `spec.persistence` | [x-altair-persistence.schema.json](./extensions/x-altair-persistence.schema.json) |
| `x-altair-queue` | Yes — `spec.queue` | [x-altair-queue.schema.json](./extensions/x-altair-queue.schema.json) |
| `x-altair-idempotency` | Yes — `spec.idempotency` (ttl, scope) | [x-altair-idempotency.schema.json](./extensions/x-altair-idempotency.schema.json) |
| `x-altair-webhook` | Yes — `spec.webhook` (direction + signing always; other fields when non-default) | [x-altair-webhook.schema.json](./extensions/x-altair-webhook.schema.json) |
| `x-altair-input-location` | Carried through; needs parameters-parser support | [x-altair-input-location.schema.json](./extensions/x-altair-input-location.schema.json) |

"Carried through" means the parser preserves the key on the
`OperationModel` so a downstream emitter can read it. The reverse
importer doesn't yet do anything with it because the corresponding
runtime piece hasn't landed; the schema is published now so authoring
tooling can lint a document that uses it.

## Round-trip example

A hand-authored Altair YAML spec:

```yaml
endpoint:
  method: POST
  path: /users
  summary: Create a user
  tags: [users]
input:
  email:
    type: string
    rules: [required]
domain:
  class: App\User\CreateUser
  invocation: __invoke
persistence:
  entity:
    class: App\User\User
    table: users
    fields:
      id: { type: uuid, primary: true }
      email: { type: string, unique: true }
  repository: App\User\UserRepository
queue:
  on_create:
    message: App\Messages\SendWelcomeEmail
    fields: { email: string }
    transport: redis
```

`spec:emit-openapi` produces the corresponding OpenAPI 3.1 fragment with
the `x-altair-*` blocks attached:

```yaml
paths:
  /users:
    post:
      summary: Create a user
      tags: [users]
      x-altair-domain:
        class: App\User\CreateUser
        invocation: __invoke
      x-altair-persistence:
        entity:
          class: App\User\User
          table: users
          fields:
            id: { type: uuid, primary: true }
            email: { type: string, unique: true }
        repository: App\User\UserRepository
      x-altair-queue:
        - name: on_create
          message: App\Messages\SendWelcomeEmail
          fields: { email: string }
          transport: redis
      requestBody:
        ...
      responses:
        ...
```

`openapi:import` reads those blocks and reconstructs the original spec:
`domain.class` matches (not the path-derived
`App\Users\CreateUsers`), the `persistence:` block is recovered
verbatim, and the `queue:` block comes back as a name-keyed map (the
extension is a list because OpenAPI's YAML serialisation reads more
naturally that way; the Altair spec uses a map because the dispatch
name is a stable identifier).

## Forward compatibility

Unknown `x-altair-*` keys are not an error. The parser captures any key
that starts with `x-altair-` onto `OperationModel::$extensions`; on the
reverse path, anything the runner does not know how to interpret
surfaces in `ImportReceipt::$warnings` so v1 imports never silently
drop a key a future Univeros release will rely on.

The warning is informational: the receipt's `ok` field stays `true` and
the import still succeeds. Agents can branch on the warning to refuse
imports that depend on yet-unsupported behaviour, or note the gap and
proceed.

## Validating the extensions

The schemas in [`docs/openapi/extensions/`](./extensions/) are Draft
2020-12 JSON Schemas. They can be used in two ways:

1. **At authoring time.** Editor tooling that supports
   `$schema`-referencing extensions can validate `x-altair-*` blocks
   inline as the document is edited.
2. **At CI time.** A linter step in the OpenAPI document's repository
   can validate each `x-altair-*` block against the matching schema and
   fail the build on drift — the same gate the round-trip test
   ([#164](https://github.com/univeros/framework/issues/164)) provides
   from the framework side.

## What does not round-trip yet

- **`x-altair-input-location`**. The Altair flat `input:` block can
  represent path / query / header / body inputs uniformly, but the
  `OpenApiParser` does not currently parse `parameters[]` schemas, so
  the location annotation has nowhere to land on the reverse path. The
  forward emitter does not yet write this key either — both halves
  land together when the parser gains `parameters[]` support.
`x-altair-idempotency` now round-trips end to end (see
[idempotency.md](../../packages/idempotency.md)) — the `ttl` and
`scope` carry through the OpenAPI extension; `mode` is a server-side
enforcement concern and defaults to `optional` on the reverse path.

`x-altair-webhook` now round-trips end to end (see
[webhooks.md](../../packages/webhooks.md)). `direction` and `signing`
always travel; every other field (`secret_name`, custom header names,
`dedupe_ttl` / `timestamp_window`, the outbound `retry` block,
`dead_letter`) is written only when it differs from its default. The
importer re-applies the same defaults and the re-emit drops them again,
which is what keeps the block byte-stable through the round-trip gate.
The shared secret itself never appears in OpenAPI — only `secret_name`,
the resolver lookup key, carries through.

## See also

- [docs/openapi/import.md](./import.md) — the importer that consumes these keys
- [#162](https://github.com/univeros/framework/issues/162) — the CLI itself
- [#161](https://github.com/univeros/framework/issues/161) — the spec emitter (library)
- [#164](https://github.com/univeros/framework/issues/164) — round-trip drift gate
