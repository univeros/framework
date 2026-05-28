# TestReporter

> An AI-native PHPUnit 11 extension that emits a structured JSON report at the end of every run — every failure mapped back to the production source under test, with structured diffs and a one-word verdict you can branch on.

**Composer:** `univeros/test-reporter`
**Namespace:** `Altair\TestReporter`

## Introduction

When an agent runs your test suite, it does not want to read the suite's output the way you do. PHPUnit's default printer is built for a human scanning a terminal: dots, an `F` here and there, a textual dump of the first few failures, a `Tests: 312, Assertions: 980, Failures: 2` footer. To act on that, an agent has to scrape it — parse the dots, regex out the failure blocks, guess which file the assertion came from, and then go hunting through `src/` for the class that actually broke. Every one of those steps is a place to be wrong, and none of them survive a PHPUnit version bump that nudges the output format.

TestReporter removes the scraping. You register one extension, run `phpunit` exactly the way you already do, and at the end of the run you get a single JSON document. The top of that document has a `result` field whose value is one of `pass`, `fail`, or `error` — so an agent decides what to do next by reading one string, not by counting failures. Each entry in `failures[]` already carries the answer to "where do I look?": a `source_under_test` array pointing at the production file, method, and line range the failing test most likely targets. And `assertSame` / `assertEquals` mismatches come pre-diffed into a small typed payload, so the agent reasons about *what changed* without re-parsing PHPUnit's textual diff.

The whole report is deterministic for the same outcome — no timestamps wedged into the diff payloads, no random ordering — which means you can check a golden copy into your test suite and diff against it in CI. That determinism is the same property that makes the report safe to feed an agent: run the suite twice with the same code and you get the same bytes, so an agent that caches by content hash will not re-reason about an unchanged run.

What this package deliberately is *not*: it is not a replacement for PHPUnit's human output (leave that on; this runs alongside it), it is not a coverage tool, and it does not try to *fix* anything. It reports — richly, machine-first — and gets out of the way.

## Installation

Install it as a dev dependency:

```bash
composer require --dev univeros/test-reporter
```

It belongs in `require-dev` because it only ever runs under PHPUnit — it has no place in your production autoloader. The only runtime requirement beyond PHP 8.3 is `phpunit/phpunit: ^11.4`; the extension hooks into PHPUnit 11's event system, so it will not load under PHPUnit 10 or earlier. If you installed the whole framework via `composer require univeros/framework`, this package is already bundled.

## Quick start

Register the extension as a `<bootstrap>` inside an `<extensions>` block in your `phpunit.xml.dist`. This is the entire wiring — PHPUnit constructs the extension, calls `bootstrap()`, and the extension registers every subscriber it needs:

```xml
<extensions>
    <bootstrap class="Altair\TestReporter\AltairExtension">
        <parameter name="output" value="json"/>
        <parameter name="file" value="build/test-results.json"/>
    </bootstrap>
</extensions>
```

Now run the suite the way you always have — nothing about the command changes:

```bash
vendor/bin/phpunit
```

When the run finishes, `build/test-results.json` holds the report. A passing run looks like this (trimmed):

```json
{
    "version": "1.0",
    "started_at": "2026-05-28T09:14:22.481+00:00",
    "duration_ms": 1840,
    "php_version": "8.3.7",
    "phpunit_version": "11.5.0",
    "totals": {
        "tests": 312,
        "assertions": 980,
        "passed": 312,
        "failed": 0,
        "errored": 0,
        "skipped": 0,
        "warnings": 0,
        "risky": 0,
        "incomplete": 0
    },
    "result": "pass",
    "failures": [],
    "errors": [],
    "skipped": [],
    "risky": [],
    "incomplete": []
}
```

When something breaks, `result` flips and the matching array fills in. Here is a single `assertSame` failure, mapped back to its source:

```json
{
    "result": "fail",
    "totals": { "tests": 312, "failed": 1, "passed": 311, "...": "..." },
    "failures": [
        {
            "test": "Altair\\Tests\\Http\\Support\\HttpCacheTest::testIsCacheableReturnsTrueWithMaxAge",
            "test_file": "tests/Http/Support/HttpCacheTest.php",
            "test_line": 41,
            "type": "ExpectationFailedException",
            "message": "Failed asserting that false is true.",
            "expected": "true",
            "actual": "false",
            "diff": { "kind": "scalar", "expected": true, "actual": false },
            "source_under_test": [
                { "file": "src/Altair/Http/Support/HttpCache.php", "method": "isCacheable", "lines": "58-71" }
            ],
            "stack_trace": [
                { "file": "tests/Http/Support/HttpCacheTest.php", "line": 41, "function": "Altair\\Tests\\Http\\Support\\HttpCacheTest::testIsCacheableReturnsTrueWithMaxAge" }
            ]
        }
    ]
}
```

An agent reads `result: "fail"`, walks `failures[0].source_under_test[0]`, and opens `src/Altair/Http/Support/HttpCache.php` at `isCacheable` (lines 58–71) — without ever looking at the test runner's textual output.

## Concepts

### The report shape

The root document is built by `Altair\TestReporter\Result\TestReport` and carries a stable `version` constant (`"1.0"`) so consumers can detect format changes. Its top-level keys are:

- **`result`** — the one-word verdict, the value of the `Altair\TestReporter\Result\ReportStatus` enum (`pass` / `fail` / `error`). It is `error` when any test errored, `fail` when any test failed (but none errored), and `pass` otherwise. This is the field to branch on.
- **`totals`** — the aggregate counts from `Result\Totals`: `tests`, `assertions`, `passed`, `failed`, `errored`, `skipped`, `warnings`, `risky`, `incomplete`.
- **`failures[]` / `errors[]`** — lists of `Result\FailureRecord`, the actionable entries.
- **`skipped[]` / `risky[]` / `incomplete[]`** — lists of `Result\SkippedRecord` (just `test` + `reason`), kept separate from failures so an agent can tell "you have work to do" from "this was intentionally not run."
- **`started_at`, `duration_ms`, `php_version`, `phpunit_version`** — run metadata.

Each `FailureRecord` serialises to this shape:

```json
{
    "test": "Fully\\Qualified\\TestClass::testMethod",
    "test_file": "tests/...",
    "test_line": 41,
    "type": "ExpectationFailedException",
    "message": "Failed asserting that ...",
    "expected": "true",
    "actual": "false",
    "diff": { "kind": "scalar", "...": "..." },
    "source_under_test": [ { "file": "...", "method": "...", "lines": "58-71" } ],
    "stack_trace": [ { "file": "...", "line": 41, "function": "..." } ]
}
```

### The structured diff

`Altair\TestReporter\Diff\ValueDiffer` turns an `assertSame` / `assertEquals` comparison failure into a typed payload, keyed by `kind` so an agent branches on the shape rather than re-parsing the values:

- **`scalar`** — `{ "kind": "scalar", "expected": 42, "actual": 7 }`
- **`array`** — `{ "kind": "array", "added": {...}, "removed": {...}, "changed": { "key": { "expected": ..., "actual": ... } } }`
- **`string`** — `{ "kind": "string", "expected_preview": "...", "actual_preview": "...", "expected_length": 5, "actual_length": 5 }` (previews are truncated past `ValueDiffer::STRING_PREVIEW_LIMIT` = 200 chars with a `… (N more chars)` marker)
- **`object`** — `{ "kind": "object", "expected_class": "X", "actual_class": "Y", "expected_preview": "...", "actual_preview": "..." }`

When the failure carried no comparable pair — most non-comparison assertions, like `assertTrue` — `diff` is `null`. The array diff is the most useful of the four: an agent can see exactly which keys were added, removed, or changed instead of eyeballing two serialised arrays.

### The three-tier source resolver

The most valuable field is `source_under_test`, and it is produced by `Altair\TestReporter\Resolver\SourceUnderTestResolver` using three signals tried strictly in order:

1. **`#[CoversClass(X::class)]` / `#[CoversFunction('x')]` attributes** on the test class or method — authoritative. This is the signal you should give the resolver when you can.
2. **A legacy `@covers` annotation** in the doc comment — a fallback for older code that still uses annotations. Do not add these in new code.
3. **A namespace heuristic** — `Altair\Tests\Http\Support\HttpCacheTest` → `Altair\Http\Support\HttpCache`. The resolver strips a `\Tests\` segment and the trailing `Test` suffix, confirms the class exists, then walks its methods to find the one whose name the test method extends (`testIsCacheableReturnsTrueWithMaxAge` → `isCacheable`, picking the longest matching prefix when several would match).

The resolver's signature is:

```php
public function resolve(string $testClass, string $testMethod): array // list<SourceLocation>
```

When no signal matches — an unknown class, a test that covers nothing nameable — it returns an empty list, and `source_under_test` serialises to `[]`. That empty array is itself a signal: the agent knows the mapping is unavailable and that it is on its own for that failure, rather than chasing a wrong guess.

### Determinism

`Altair\TestReporter\Output\JsonWriter` is documented to be deterministic for the same `TestReport` instance: no random fields, ordering only where it carries meaning. That is what makes a checked-in golden copy a viable CI gate — see [Testing](#testing). The writer emits pretty-printed JSON with unescaped slashes and unicode, terminated by a newline.

## Usage

### Make the resolver find your source

The resolver is only as good as the signal you give it. Two conventions cover almost everything.

The first is naming. Put the test in a sibling `Tests\` namespace, name the class `<Class>Test`, and prefix each test method with the source method it exercises. With that layout the namespace heuristic resolves the source for free — no attributes required:

```php
namespace Altair\Tests\Http\Support;        // sibling Tests\ segment

final class HttpCacheTest extends TestCase  // <Class>Test
{
    public function testIsCacheableReturnsTrueWithMaxAge(): void // prefix: isCacheable
    {
        // resolves to Altair\Http\Support\HttpCache::isCacheable
    }
}
```

The second is the explicit override. When the naming convention is awkward — one test class covering several production classes, or a test whose name cannot mirror the method — annotate with `#[CoversClass]` and the resolver takes that as authoritative, ahead of the heuristic:

```php
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HttpCache::class)]
final class HttpCacheBehaviourTest extends TestCase
{
    // every failure here maps to HttpCache regardless of method names
}
```

Do **not** reach for the legacy `@covers` doc-comment annotation in new code. The resolver still honours it as a second-tier fallback so existing suites keep working, but `#[CoversClass]` is the supported, native form — PHPDoc covers are deprecated in PHPUnit and Rector will strip them anyway.

### Branch agent logic on `result`

The point of the one-word verdict is that downstream automation does not need to interpret the totals. The control flow is a single read:

```php
$report = json_decode(file_get_contents('build/test-results.json'), true);

match ($report['result']) {
    'pass'  => /* nothing broke — move on */,
    'fail'  => /* assertions failed — read failures[].source_under_test */,
    'error' => /* tests threw — read errors[]; likely a setup or fixture problem */,
};
```

`fail` versus `error` matters to an agent: a `fail` points at a logic bug the agent can usually fix from `source_under_test`, while an `error` often means the test could not even run (a missing dependency, a broken fixture, an uncaught exception) — a different remediation path.

### Run with the report disabled

When you want only PHPUnit's human output for a particular run, set the `output` parameter to `none`. The extension still loads, but the writer becomes a no-op — no JSON is produced:

```xml
<bootstrap class="Altair\TestReporter\AltairExtension">
    <parameter name="output" value="none"/>
</bootstrap>
```

## Configuration

There is no `Configuration` class and nothing to bind in the container — the extension is configured entirely through the two `<parameter>` elements on the `<bootstrap>` tag, read in `AltairExtension::bootstrap()`:

| Parameter | Default | Effect |
|---|---|---|
| `output` | `json` | `json` emits the report; `none` disables emission (extension loads, writer is a no-op). |
| `file` | _(unset)_ | Path the JSON is written to. The writer creates parent directories as needed. **Omit it and the report is written to stdout** instead of a file. |

The signature PHPUnit calls is:

```php
public function bootstrap(
    Configuration $configuration,
    Facade $facade,
    ParameterCollection $parameters,
): void
```

The extension resolves the project root from `getcwd()` to relativise the file paths it emits — for the framework itself, and for any project you run `phpunit` from at its root, that yields clean repository-relative paths like `src/Altair/Http/Support/HttpCache.php`.

Two practical notes. First, point `file` at a build directory you gitignore (`build/test-results.json` is the convention) — the report is a per-run artifact, not source. Second, if you omit `file` to write to stdout, be aware PHPUnit's own output goes to stdout too; pipe to a file or use `file` when you need the JSON cleanly.

## Testing

The package's own tests under `tests/TestReporter/` are the clearest description of each component's contract:

- `tests/TestReporter/Resolver/SourceUnderTestResolverTest.php` exercises all three resolver tiers in turn — the `#[CoversClass]` attribute, the legacy `@covers` annotation, and the namespace heuristic — plus the empty-list result for an unknown class.
- `tests/TestReporter/Diff/ValueDifferTest.php` pins each diff `kind`: scalar, array (added/removed/changed), string (previews + lengths + truncation past the limit), and object (class names + previews).
- `tests/TestReporter/Output/JsonWriterTest.php` checks stdout and file emission and asserts byte-for-byte determinism — the same report rendered twice must match exactly.
- `tests/TestReporter/ResultCollectorTest.php` covers the totals arithmetic and the report's JSON shape without needing a real `PHPUnit\Event\Code\Test` instance.

Note the resolver tests lean on **fixtures** under `tests/TestReporter/Fixtures/` — `ExampleHttpCacheTest` (the `#[CoversClass]` path), `LegacyCoversAnnotationTest` (the annotation path), and `ExampleNoCoversTest` (the heuristic path), each paired with a tiny production-shaped class. Those fixtures are deliberately **excluded** from the main suite in `phpunit.xml.dist` so they do not run as real tests — the resolver test instantiates them by reflection instead:

```xml
<exclude>./tests/TestReporter/Fixtures/ExampleHttpCacheTest.php</exclude>
<exclude>./tests/TestReporter/Fixtures/ExampleNoCoversTest.php</exclude>
<exclude>./tests/TestReporter/Fixtures/LegacyCoversAnnotationTest.php</exclude>
```

Because `JsonWriter` is deterministic, the natural way to defend the report format in your own CI is a **golden snapshot**: capture a known-good `build/test-results.json` for a fixed suite and diff every run against it. Any unintended change to the format surfaces as a diff in the PR rather than a silent shift in what your agents consume.

## Related packages

- [cli.md](./cli.md) — the `bin/altair` command substrate. Other framework commands shell out to PHPUnit and read this report rather than scraping text.
- [doctor.md](./doctor.md) — the health-check runner. Its `TestsPassingCheck` reads the `result` field of this report to decide whether the suite is green, exactly as described in [Branch agent logic on `result`](#branch-agent-logic-on-result).
- [mcp.md](./mcp.md) — the Model Context Protocol server. Its `framework__run_tests` tool runs the suite with this extension active and returns the structured JSON straight to the calling agent.
- [scaffold.md](./scaffold.md) — the spec scaffolder. The PHPUnit test it emits for every generated Action already follows the `<Class>Test` + method-prefix convention the resolver keys on, so failures in generated tests map back to the generated source out of the box.

## Limitations

- **PHPUnit 11 only.** The extension implements `PHPUnit\Runner\Extension\Extension` and subscribes to PHPUnit 11's event system. It will not load under PHPUnit 10 or earlier; the package requires `phpunit/phpunit: ^11.4`.
- **`build/test-results.json` is a build artifact.** Keep it gitignored — it is regenerated on every run and is not source to be committed.
- **The resolver can return `[]`.** When none of the three signals match — an unconventional test name with no `#[CoversClass]`, a test covering something the heuristic can't reach — `source_under_test` is an empty array. That is correct behaviour, not a bug: it tells the agent the mapping is unavailable so it does not chase a wrong guess. The fix is to add a `#[CoversClass]` attribute.
- **Path relativisation uses `getcwd()`.** Paths are relative to the directory you invoke `phpunit` from. Run from your project root (as you normally would) and they come out clean; run from a subdirectory and the emitted paths reflect that working directory.
