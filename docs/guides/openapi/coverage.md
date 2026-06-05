# OpenAPI 3.1 coverage

What `bin/altair openapi:import` (OpenAPI → Altair) and `spec:emit-openapi`
(Altair → OpenAPI) actually do with each OpenAPI feature. This is the honest,
evidence-based map — kept current as [#214](https://github.com/univeros/framework/issues/214)
closes the gaps.

> **Standard.** Map everything representable; **surface (warn/error) everything
> else — never silently drop**; emit spec-compliant OpenAPI; keep the round-trip
> stable. Literal 100% fidelity into generated code isn't a goal — some features
> (`callbacks`, `links`, `oneOf` polymorphism, free-form `additionalProperties`)
> have no clean Altair representation.

## Import (OpenAPI → Altair spec)

### Mapped

- Paths + operations; `operationId` (or synthesised), `summary`.
- **Parameters** — path / query / header / cookie become inputs tagged with their `in:` location (with type + `required`; enums → `in:` rule). *Phase 2.*
- Request + response bodies in **`application/json`**. When a request body has **no `application/json`**, its schema is read from the first content type carrying an object/array schema (multipart/form-data, x-www-form-urlencoded) — the body is *normalized*, not dropped (Phase 4a). Responses fall back to the first content type with any schema. On export the body always re-emits as `application/json`, so the wire content type is normalized while the body structure round-trips.
- Schema types: `object` (incl. **nested objects**), `array`, **arrays of objects**, **top-level array bodies**, scalars (`integer`→`int`, `number`→`float`, `boolean`→`bool`, `string`), `enum`→`in:` rule.
- **Validation constraints** ↔ rules (Phase 3): `format` (`email`/`uri`/`ip`/`date-time`)→`email`/`url`/`ip`/`datetime`; `minLength`/`maxLength`→`min`/`max`; `minimum`/`maximum`→`min`/`max`; `pattern`→`regex:`. Round-trips (the forward emitter writes the inverse).
- Internal `$ref` (`#/components/schemas/<Name>`), `properties` + `required`.
- `x-altair-*` extensions (domain/persistence/queue/idempotency/webhook round-trip).

### Surfaced as warnings (imported, but reported — not silent) — *Phase 1*

`openapi:import` warns about each of these in its receipt (`warnings[]`) and human output:

- parameter `$ref` (not resolved).
- **non-`application/json` request bodies whose schema is read** — when JSON is present the other representations are ignored; when it is absent an object/array body is normalized (Phase 4a). Either way the importer says which content type(s) it read or ignored.
- **binary/scalar-only request bodies** (`application/octet-stream`, `text/plain`) — no named-field representation, so still dropped (warned).
- **non-`application/json` responses whose schema is read** — when a response has no `application/json`, its schema is normalized from the first content type carrying one, and the importer reports the normalization (Phase 4a). When `application/json` is present the other representations are alternative views, not a loss, so they are not warned.
- requestBody **`$ref`** (body dropped).
- operation + global **`security`**, `components.securitySchemes`.
- `servers`, `webhooks` (3.1), `callbacks`, path-item `$ref`.

### Surfaced as errors / skips (fail, or `--skip-unmappable`)

- **External / file / URL `$ref`** (only internal component refs resolve).
- `oneOf` / `anyOf` / `allOf` in a request body; recursive `$ref`; a bare scalar body (incl. a normalized non-JSON body that turns out to be a scalar).

### Not yet mapped or warned (later phases)

- Remaining constraints: `multipleOf`, `min/maxItems`, `uniqueItems` (no Altair rule yet).
- requestBody `required` flag; `additionalProperties`, `const`, `discriminator`, `not`, `prefixItems`, `nullable`; `deprecated`, `default`, `example(s)`, `title`/`description`; response `headers`/`links`.

## Export (Altair spec → OpenAPI)

`spec:emit-openapi` emits operations, responses from `output:`, an
`application/json` request body from body inputs, **OpenAPI `parameters`** for
inputs tagged `in: path|query|header|cookie` (Phase 2), and **schema
constraints** from validation rules — `email`→`format`, `min:3`→`minLength`,
`in:`→`enum`, `regex`→`pattern` (Phase 3). Not yet emitted (tracked in #214):
`security`, `servers`.

## Roadmap

See [#214](https://github.com/univeros/framework/issues/214) for the phased plan
(stop silent loss → parameters → validation fidelity → content & composition →
security & misc). This page is updated as each phase lands. **Phase 4a** (non-JSON
object bodies, normalized) has landed; `allOf` merge, `oneOf`/`anyOf`,
`additionalProperties`, and external-`$ref` bundling remain.
