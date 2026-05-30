# `openapi:roundtrip` — drift gate for OpenAPI ↔ Altair YAML

> CI gate that exercises the full `OpenAPI → Altair YAML → OpenAPI`
> chain in memory and reports semantic drift. Same contract style as
> `spec:emit-sdk --check`: human or JSON report, non-zero exit in
> `--check` mode so a build refuses to merge when an emitter or parser
> change silently degrades the round-trip.

**Command:** `bin/altair openapi:roundtrip`
**Source:** [src/Altair/Scaffold/Cli/OpenApiRoundtripCommand.php](../../src/Altair/Scaffold/Cli/OpenApiRoundtripCommand.php)
**Issue:** [#164](https://github.com/univeros/framework/issues/164) ·
epic [#160](https://github.com/univeros/framework/issues/160)

## Why a gate

Without one, the import path silently degrades. Someone refactors
`OperationMapper`; an `x-altair-*` block stops round-tripping; the
fragments are still individually valid; tests still pass. Then a
release ships and projects that adopted the import workflow start
losing data on every regenerate.

This gate flips that on its head. It exercises the *whole* chain
end-to-end on every commit, and fails if anything that was in the
source no longer makes it through. The CI signal is what makes the
import path safe to depend on.

## Usage

```bash
# Human report
bin/altair openapi:roundtrip openapi.yaml

# CI mode — exit 1 on drift
bin/altair openapi:roundtrip openapi.yaml --check

# Structured diff for agents
bin/altair openapi:roundtrip openapi.yaml --format=json
```

The `openapi.yaml` argument is the source document. The runner reads
it, parses it through [`OpenApiParser`](../../src/Altair/Scaffold/Sdk/Model/OpenApiParser.php),
emits Altair specs through [#161's emitter](../../src/Altair/Scaffold/Spec/Emitter/Emitter.php),
re-parses each spec through [`Parser`](../../src/Altair/Scaffold/Spec/Parser.php),
re-emits each as an OpenAPI fragment through
[`OpenApiEmitter`](../../src/Altair/Scaffold/Emitter/OpenApiEmitter.php),
merges the fragments back into one document, projects both sides into
the comparison view documented below, and diffs them.

Everything runs in memory. No temp directories, no I/O during the
round-trip itself.

## What the gate compares

For every `(method, path)` operation the gate compares:

- **`summary`** — exact string match (drift surfaces in plain text).
- **`x-altair-domain`** / **`x-altair-persistence`** / **`x-altair-queue`** —
  full deep equality of any block the source carried. (See
  [extensions.md](./extensions.md) for the keys themselves.)
- **Response status set** — limited to statuses that carry an
  `application/json` schema (see normalization below).

Operations missing from either side are flagged
(`missing_operation` / `extra_operation`).

## What the gate intentionally ignores

These are documented as part of the contract — when present, they do
*not* fail the gate:

- **Key order.** Output is alphabetical; source is whatever order the
  author chose.
- **Empty optional arrays.** `required: []`, `tags: []`,
  `parameters: []` may appear in source and be omitted in the
  re-emitted output; semantic equality is what matters.
- **`info` block.** Title and version are derived metadata; the
  re-emitter writes its own placeholders.
- **Doc-level `tags` array.** Per-operation tags are derived from the
  path segment, so the consolidated list at the document root is
  intentionally not authoritative.
- **`components/schemas`.** Today the importer resolves `$ref` to
  inlined types in the spec; re-emission cannot restore the
  components map. Drift in component definitions is a known
  limitation — the gate compares operation-level shapes only.
- **Description-only responses.** `204 No Content`, `404 Not found`,
  any 2xx/4xx/5xx without an `application/json` schema. The Altair
  `output:` block has no way to represent an empty body, so these
  cannot survive the round-trip and the gate does not penalise their
  absence on the round-tripped side.
- **Enriched extensions.** A source doc without `x-altair-domain`
  that gets a synthesised one back is the importer doing its job
  (it's the path-derived FQCN), not a regression. Drift only fires
  when the source *had* an extension and the round-trip changed or
  dropped it.

## JSON receipt

`--format=json`:

```json
{
  "clean": true,
  "input": "openapi.yaml",
  "operations_compared": 5,
  "differences": [],
  "error": null
}
```

On drift:

```json
{
  "clean": false,
  "input": "openapi.yaml",
  "operations_compared": 5,
  "differences": [
    {
      "kind": "extension_drift",
      "pointer": "#/paths/~1users/post/x-altair-persistence",
      "expected": {"entity": {"class": "App\\User\\User", "...": "..."}},
      "actual": null,
      "message": "'x-altair-persistence' present in source was lost or changed by the round-trip."
    }
  ],
  "error": null
}
```

`kind` is a small fixed enum agents can branch on without parsing
prose:

| Kind | Meaning |
|---|---|
| `missing_operation` | An operation in the source did not survive the round-trip. |
| `extra_operation` | The round-trip emitted an operation that wasn't in the source. |
| `summary_drift` | An operation's `summary` text changed. |
| `extension_drift` | An `x-altair-*` block changed or was lost. |
| `status_drift` | A schema-bearing response status was dropped. |

The receipt is byte-stable for the same input (no timestamps, no
IDs), so CI golden-file workflows are safe.

## CI integration

A typical CI step:

```yaml
- name: OpenAPI round-trip
  run: bin/altair openapi:roundtrip docs/openapi.yaml --check --format=json
```

Exit 1 means either an unrecoverable parse error (the doc itself is
broken) or drift was detected. Both should block a merge.

For framework CI, the gate runs against
[`benchmarks/tokens-to-ship/fixtures/posts.openapi.yaml`](../../benchmarks/tokens-to-ship/fixtures/posts.openapi.yaml)
as a representative real-world Petstore-class document; the
deliberately-broken-emitter test in
[`tests/Scaffold/Cli/OpenApiRoundtripRunnerTest.php`](../../tests/Scaffold/Cli/OpenApiRoundtripRunnerTest.php)
proves the gate fails on a regression.

## Known limitations (today)

- The gate is **operation-level**, not schema-level. Drift inside a
  request body / response body shape (e.g. an inlined object that
  should have been a `$ref`) is not caught. Schema-level comparison
  lands when `OpenApiParser` learns to preserve `parameters[]` and
  `components/schemas` on the reverse path; the gate gains a
  `--strict` flag at that point.
- `x-altair-input-location`, `x-altair-idempotency`, and
  `x-altair-webhook` are reserved keys — they ride along verbatim
  but the gate does not yet have a corresponding spec field to
  compare against. Drift would surface as a warning in the import
  receipt rather than in this gate's diff.
- Component schema names are not preserved through the round-trip
  even when the wire shape is identical, so a `$ref` to
  `components/schemas/User` becomes an inlined object on the
  re-emitted side. This is a known property of the importer; the
  gate's `--strict` mode (above) is where this will be reported once
  the round-trip can be made bidirectional.

## See also

- [docs/openapi/import.md](./import.md) — the importer the gate exercises
- [docs/openapi/extensions.md](./extensions.md) — the `x-altair-*` keys the gate watches
- [#161](https://github.com/univeros/framework/issues/161) — spec emitter
- [#162](https://github.com/univeros/framework/issues/162) — import CLI
- [#163](https://github.com/univeros/framework/issues/163) — extension family
