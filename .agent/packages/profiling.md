# univeros/profiling  ·  Altair\Profiling

**Purpose:** bin/altair profile — framework-native sampling profiler. Answers "where does my code spend time?" via excimer (or xdebug) sampling, builds a weighted call tree + hotspot table + flamegraph SVG, and diffs two profiles to flag regressions. Deterministic JSON for agents and CI.

## Concrete classes

- `BackendDetector` _(final)_
- `CallNode` _(final)_
- `ChangedFunction` _(final)_
- `CompareCommand` _(final)_
- `Differ` _(final)_
- `ExcimerSampler` _(final)_ — implements `SamplerInterface`
- `FilesystemProfileStorage` _(final)_
- `FlameCommand` _(final)_
- `FlamegraphRenderer` _(final)_
- `Hotspot` _(final)_
- `HotspotAnalyzer` _(final)_
- `HumanRenderer` _(final)_
- `Json` _(final)_
- `ListCommand` _(final)_
- `PrependBuilder` _(final)_
- `ProfileDiff` _(final)_
- `ProfileReport` _(final)_
- `ProfileSummary` _(final)_
- `Profiler` _(final)_
- `ProfilingConfiguration` _(final)_ — implements `ConfigurationInterface`
- `RunCommand` _(final)_
- `Sample` _(final)_
- `SampleLog` _(final)_
- `ShowCommand` _(final)_
- `SubprocessProfiler` _(final)_
- `TreeBuilder` _(final)_

## Tests as documentation

- `tests/Profiling/Diff/DifferTest.php`
- `tests/Profiling/Output/FlamegraphRendererTest.php`
- `tests/Profiling/Sampler/BackendDetectorTest.php`
- `tests/Profiling/Storage/FilesystemProfileStorageTest.php`
- `tests/Profiling/Tree/HotspotAnalyzerTest.php`
- `tests/Profiling/Tree/TreeBuilderTest.php`

## Related packages

- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
