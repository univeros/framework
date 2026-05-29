# univeros/test-reporter  ·  Altair\TestReporter

**Purpose:** AI-native PHPUnit reporter: structured JSON output mapped to the production source under test.

## Concrete classes

- `AltairExtension` _(final)_ — implements `Extension`
- `FailureRecord` _(final)_
- `JsonWriter` _(final)_
- `ReportStatus` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `ResultCollector` _(final)_
- `SkippedRecord` _(final)_
- `SourceLocation` _(final)_
- `SourceUnderTestResolver` _(final)_
- `StackFrame` _(final)_
- `TestConsideredRiskySubscriber` _(final)_ — implements `ConsideredRiskySubscriber`, `Subscriber`
- `TestErroredSubscriber` _(final)_ — implements `ErroredSubscriber`, `Subscriber`
- `TestFailedSubscriber` _(final)_ — implements `FailedSubscriber`, `Subscriber`
- `TestFinishedSubscriber` _(final)_ — implements `FinishedSubscriber`, `Subscriber`
- `TestMarkedIncompleteSubscriber` _(final)_ — implements `MarkedIncompleteSubscriber`, `Subscriber`
- `TestPassedSubscriber` _(final)_ — implements `PassedSubscriber`, `Subscriber`
- `TestPreparedSubscriber` _(final)_ — implements `PreparedSubscriber`, `Subscriber`
- `TestReport` _(final)_
- `TestRunnerFinishedSubscriber` _(final)_ — implements `ExecutionFinishedSubscriber`, `Subscriber`
- `TestSkippedSubscriber` _(final)_ — implements `SkippedSubscriber`, `Subscriber`
- `Totals` _(final)_
- `ValueDiffer` _(final)_

## Tests as documentation

- `tests/TestReporter/Diff/ValueDifferTest.php`
- `tests/TestReporter/Fixtures/ExampleHttpCacheTest.php`
- `tests/TestReporter/Fixtures/ExampleNoCoversTest.php`
- `tests/TestReporter/Fixtures/LegacyCoversAnnotationTest.php`
- `tests/TestReporter/Output/JsonWriterTest.php`
- `tests/TestReporter/Resolver/SourceUnderTestResolverTest.php`
- `tests/TestReporter/ResultCollectorTest.php`

## Related packages

- `phpunit/phpunit`
