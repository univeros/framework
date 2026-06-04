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
- **Path parameters** (currently always typed `string` — declared schema ignored).
- Request + response bodies in **`application/json`**.
- Schema types: `object` (incl. **nested objects**), `array`, **arrays of objects**, **top-level array bodies**, scalars (`integer`→`int`, `number`→`float`, `boolean`→`bool`, `string`), `enum`→`in:` rule.
- Internal `$ref` (`#/components/schemas/<Name>`), `properties` + `required`.
- `x-altair-*` extensions (domain/persistence/queue/idempotency/webhook round-trip).

### Surfaced as warnings (imported, but reported — not silent) — *Phase 1*

`openapi:import` warns about each of these in its receipt (`warnings[]`) and human output:

- **query / header / cookie parameters** and parameter `$ref`.
- **non-`application/json` request bodies** (multipart, form-urlencoded, xml, octet-stream).
- requestBody **`$ref`** (body dropped).
- operation + global **`security`**, `components.securitySchemes`.
- `servers`, `webhooks` (3.1), `callbacks`, path-item `$ref`.

### Surfaced as errors / skips (fail, or `--skip-unmappable`)

- **External / file / URL `$ref`** (only internal component refs resolve).
- `oneOf` / `anyOf` / `allOf` in a request body; recursive `$ref`; a bare scalar body.

### Not yet mapped or warned (later phases)

- Validation constraints: `format`, `min/maxLength`, `pattern`, `minimum/maximum`, `multipleOf`, `min/maxItems` (Phase 3 → `Altair\Validation` rules).
- requestBody `required` flag; `additionalProperties`, `const`, `discriminator`, `not`, `prefixItems`, `nullable`; `deprecated`, `default`, `example(s)`, `title`/`description`; response `headers`/`links`; non-JSON responses.

## Export (Altair spec → OpenAPI)

`spec:emit-openapi` emits operations, an `application/json` request body from the
`input:` block, and responses from `output:`. Not yet emitted (mirror of the
import gaps, tracked in #214): OpenAPI `parameters` (all inputs become a JSON
body — path/query/header distinction is lost), validation rules → schema
constraints (`email`→`format`, `min:3`→`minLength`, `in:`→`enum`, `regex`→`pattern`),
`security`, `servers`.

## Roadmap

See [#214](https://github.com/univeros/framework/issues/214) for the phased plan
(stop silent loss → parameters → validation fidelity → content & composition →
security & misc). This page is updated as each phase lands.
