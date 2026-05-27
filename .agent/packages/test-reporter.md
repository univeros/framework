# univeros/test-reporter  ·  Altair\TestReporter

**Purpose:** AI-native PHPUnit reporter. Subscribes to PHPUnit 11's event API and emits a structured JSON report at the end of every run — failures mapped back to the production source under test, structured diffs for `assertSame` / `assertEquals`, one-word `result` field the agent can branch on without parsing totals.

Pairs naturally with `univeros/events` (#77) and `univeros/scaffold`'s journal (#72): an MCP-equipped agent can now run "did the change work? → what changed? → rewind if not" end-to-end without scraping any human-oriented output.

## Output shape

```json
{
  "version": "1.0",
  "started_at": "2026-05-27T10:42:13.000000+00:00",
  "duration_ms": 4287,
  "php_version": "8.3.31",
  "phpunit_version": "11.5.55",
  "totals": {
    "tests": 5103, "assertions": 10151,
    "passed": 5094, "failed": 1, "errored": 0,
    "skipped": 9, "warnings": 4, "risky": 0, "incomplete": 0
  },
  "result": "fail",
  "failures": [
    {
      "test": "Altair\\Tests\\Http\\Support\\HttpCacheTest::testIsCacheableReturnsTrueWithMaxAge",
      "test_file": "tests/Http/Support/HttpCacheTest.php",
      "test_line": 85,
      "type": "AssertionFailedError",
      "message": "Failed asserting that false is true.",
      "expected": "true",
      "actual": "false",
      "diff": {"kind": "scalar", "expected": true, "actual": false},
      "source_under_test": [
        {"file": "src/Altair/Http/Support/HttpCache.php", "method": "isCacheable", "lines": "64-71"}
      ],
      "stack_trace": [{"file": "tests/Http/Support/HttpCacheTest.php", "line": 85, "function": "..."}]
    }
  ],
  "errors": [], "skipped": [...], "risky": [], "incomplete": []
}
```

## Source-under-test resolution

Three signals, tried in order, first match wins:

1. **`#[CoversClass(X::class)]` / `#[CoversFunction('x')]`** attributes on the test class or method (authoritative).
2. **`@covers` annotation** in the docblock (legacy fallback).
3. **Namespace heuristic** — strip `\Tests\` from the test class's namespace and drop the `Test` suffix, then walk the resulting class's methods for one whose name is a prefix of the test method name (so `testIsCacheableReturnsTrueWithMaxAge` covers `isCacheable`). Falls back to the un-stripped form for fixtures that live inside the test tree itself.

When no signal yields a match, `source_under_test: []` and the agent knows it's on its own.

## Diff payload kinds

`diff` is keyed by `kind` so agents can branch:

- `{"kind":"scalar","expected":...,"actual":...}` — booleans, numbers
- `{"kind":"array","added":{},"removed":{},"changed":{}}` — keyed diff
- `{"kind":"string","expected_preview":"...","actual_preview":"...","expected_length":N,"actual_length":N}` — strings (previews truncated at 200 chars)
- `{"kind":"object","expected_class":"X","actual_class":"Y","expected_preview":"...","actual_preview":"..."}` — objects (uses `__toString` when available)
- `null` — non-comparison assertions

## Registration

### `phpunit.xml.dist`

```xml
<extensions>
    <bootstrap class="Altair\TestReporter\AltairExtension">
        <parameter name="output" value="json"/>
        <parameter name="file" value="build/test-results.json"/>
    </bootstrap>
</extensions>
```

Parameters:

| Parameter | Default | Purpose |
|---|---|---|
| `output` | `json` | `json` emits the report; `none` registers the extension but skips emission (silent observer mode for debugging). |
| `file` | _(unset = stdout)_ | Write the JSON to a file instead of stdout. |

### CLI override

```bash
vendor/bin/phpunit                                  # default human output
vendor/bin/phpunit --extension=Altair\\TestReporter\\AltairExtension
```

## Architecture

```
src/Altair/TestReporter/
├── AltairExtension.php             # PHPUnit Extension entrypoint
├── ResultCollector.php             # mutable per-run state (one owner)
├── Result/
│   ├── TestReport.php              # root container
│   ├── Totals.php                  # aggregate counts
│   ├── ReportStatus.php            # pass / fail / error
│   ├── FailureRecord.php           # one failed or errored test
│   ├── SkippedRecord.php           # skipped / risky / incomplete
│   ├── SourceLocation.php          # file/method/lines pointer
│   └── StackFrame.php              # one frame of the trace
├── Resolver/
│   └── SourceUnderTestResolver.php # 3-signal heuristic
├── Diff/
│   └── ValueDiffer.php             # kind-keyed structured diff
├── Output/
│   └── JsonWriter.php              # stdout or file, deterministic
└── Event/                          # 9 subscribers, one per relevant PHPUnit event
    ├── TestPreparedSubscriber.php
    ├── TestPassedSubscriber.php
    ├── TestFailedSubscriber.php
    ├── TestErroredSubscriber.php
    ├── TestSkippedSubscriber.php
    ├── TestMarkedIncompleteSubscriber.php
    ├── TestConsideredRiskySubscriber.php
    ├── TestFinishedSubscriber.php
    └── TestRunnerFinishedSubscriber.php   # emits the report at the end
```

## Tests as documentation

- `tests/TestReporter/Resolver/SourceUnderTestResolverTest.php` — one assertion per signal type + the no-match case
- `tests/TestReporter/Diff/ValueDifferTest.php` — golden diffs for scalar / array / string / object / truncation
- `tests/TestReporter/ResultCollectorTest.php` — totals + JSON shape + status transitions
- `tests/TestReporter/Output/JsonWriterTest.php` — stdout vs file emission + determinism
- `tests/TestReporter/Fixtures/` — production-source fixtures used by the resolver tests (excluded from auto-discovery in `phpunit.xml.dist`)

## Related packages

- `phpunit/phpunit` ^11.4 (Extension API)

## Issue references

- #70 — this package
- #84 — MCP tool wrapper for `bin/altair test --format=json` (deferred, depends on #69)
