# Profiling

> A framework-native sampling profiler. Answers "where does my code spend time?" by sampling the call stack with `ext-excimer`, folds the samples into a weighted call tree plus a top-N hotspot table, renders an inline-SVG flamegraph, and diffs two profiles to flag regressions. Deterministic JSON for agents and CI.

**Composer:** `univeros/profiling`
**Namespace:** `Altair\Profiling`

## Introduction

Performance work is the activity agents are weakest at: "this is slow" is a top debugging request, and without an in-loop profiler the agent's only honest move is to punt to a human. The existing PHP profilers (Xdebug, Tracy, Spiral) are human-UI tools whose output isn't easy for an agent to drive. Profiling is the first-party answer: a sampling profiler that produces a structured JSON shape an agent can navigate, a flamegraph SVG for the human, and — the killer feature — a compare-before-after diff that closes the optimisation loop.

The package owns the *data*: `Sampler` abstraction over the backend, `TreeBuilder` that folds samples into a weighted call tree, `HotspotAnalyzer` for the top-N table, `Differ` for regression detection, `FilesystemProfileStorage` for `.altair/profiles/`, and the three renderers (human, JSON, SVG flamegraph). The first profileable target is `profile:run <script.php>` — a subprocess profiler that spawns `php` with an `auto_prepend_file` that wires excimer at start-up. Always-on HTTP middleware capture and the per-package collectors (DB, cache, queue, HTTP egress) are deliberately deferred to a follow-up issue.

Why a separate package: profiling has its own runtime cost and a real extension dependency (`ext-excimer` or `ext-xdebug`); it should be opt-in. And the data shape — call tree, hotspots, diff — is a substantial domain on its own, with downstream consumers like the future Observatory `profiles` panel that wants to read it without re-deriving anything.

## Installation

Standalone:

```bash
composer require --dev univeros/profiling
```

Then install the sampling backend (the framework does not bundle one):

```bash
# Recommended: ext-excimer (low overhead, statistical sampling)
pecl install excimer
# … or (alternative, higher overhead): ext-xdebug
pecl install xdebug
```

Verify:

```bash
php -m | grep -iE 'excimer|xdebug'
```

Without a backend loaded, `profile:run` exits `2` with an install hint; the read-only commands (`profile:list`, `profile:show`, `profile:compare`, `profile:flame`) work fine on already-captured profiles.

## Quick start

Capture a profile:

```bash
bin/altair profile:run tools/bench-checkout.php
# ✓ Profile 20260529-130742-3f9c1a — tools/bench-checkout.php
#   samples=2418  duration=2412ms  period=1000us  backend=excimer
#
# Hotspots (top 10 by self-samples):
#    34.20%  self=826    total=1102   Cycle\ORM\Mapper\Mapper::queueCreate
#    19.04%  self=460    total=460    PDO::prepare
#    …
```

List and inspect:

```bash
bin/altair profile:list                # newest first, lightweight (no tree decode)
bin/altair profile:show 20260529-130742-3f9c1a
bin/altair profile:show 20260529-130742-3f9c1a --format=json
```

Compare a before/after:

```bash
bin/altair profile:run tools/bench-checkout.php --description=before
# ...edit code...
bin/altair profile:run tools/bench-checkout.php --description=after
bin/altair profile:compare 20260529-130742-3f9c1a 20260529-130810-7a2b3c
# Compare base=… head=…
#   base_samples=2418  head_samples=1604  total Δ=-33.7%
#
# Improvements:
#   ✓  -42.1%  base=826   head=478   Cycle\ORM\Mapper\Mapper::queueCreate
```

Exit code is `1` when any regression is found (CI gate); `0` otherwise.

Render a flamegraph:

```bash
bin/altair profile:flame 20260529-130742-3f9c1a > flame.svg
# Open flame.svg in any browser
```

## Concepts

**Sampling, not tracing.** The profiler walks the PHP call stack on every wall-clock tick (default 1 ms). One thousand samples per second is enough to surface anything that matters and cheap enough to run on a real workload. Tracing every function call (the Xdebug profile-mode approach) is overkill — and *changes* the runtime characteristics it's measuring.

**The samples become a tree.** `TreeBuilder` folds the list of root-first stacks into one weighted call tree. Each node carries `selfSamples` (samples where this frame is the leaf) and `totalSamples` (samples where this frame appears anywhere). Children sort by `totalSamples` descending so the hottest path reads first.

**Hotspots aggregate per FUNCTION, across all call sites.** The hotspot table answers "how much time is in `Mapper::queueCreate`?" — collapsing the same method called from three different parents into one row whose self-samples are summed. The tree expresses *paths*; the hotspot table expresses *functions*. Both are useful and Profiling gives you both.

**Diffs flag regressions, not noise.** `Differ` compares two reports function-by-function, reports any change above `SIGNIFICANCE_PERCENT` (5%) as a change, and flags only the subset that's a *regression* (>= `REGRESSION_THRESHOLD_PERCENT` 10% slower AND >= `REGRESSION_MIN_SAMPLES` 5 head-samples). That floor keeps the noisy tail of one-sample functions out of CI gates.

**Profiles are persisted, lightweight to list.** Each report saves as one JSON file under `.altair/profiles/`; `profile:list` reads only each file's header (id, target, timestamp, sample count) so listing a hundred profiles never deserialises a tree. Rotation keeps the newest 100 by default.

**Reading is independent of the backend.** The subprocess profiler JSON-serialises samples through a stable shape, so a developer machine without excimer can still `profile:show`/`profile:compare`/`profile:flame` profiles captured on a CI box that has excimer loaded.

## CLI surface

| Command | Effect | Exit |
|---|---|---|
| `profile:run <script.php>` | Profile a PHP script in a subprocess, save under `.altair/profiles/`. Options: `--description`, `--period-us`, `--timeout-ms`, `--format`. | `0` on success, `2` if no backend or script not found. |
| `profile:list` | Newest-first list of stored profiles (id, target, timestamp, samples). Options: `--limit`, `--format`. | `0` |
| `profile:show <id>` | Render one profile (hotspots + metadata, full tree in JSON). Options: `--format`. | `0` / `2` not found. |
| `profile:compare <base> <head>` | Diff two profiles, flag regressions. Options: `--format`. | `1` if any regressions found (CI gate). |
| `profile:flame <id>` | Render a stored profile as an inline SVG flamegraph. Options: `--out=path.svg`. | `0` |

## MCP tools

[`univeros/mcp`](./mcp.md) exposes five tools — the MCP server now serves **40 tools** total:

| Tool | Wraps | Returns |
|---|---|---|
| `framework__profile` | `profile:run` (script, period_us?, timeout_ms?, description?) | The full saved `ProfileReport` JSON. |
| `framework__profile_list` | `profile:list` (limit?) | `{count, profiles: [...]}` lightweight summaries. |
| `framework__profile_show` | `profile:show <id>` | The full saved `ProfileReport` JSON. |
| `framework__profile_compare` | `profile:compare <base> <head>` | `{base_id, head_id, ..., changes, regressions, has_regressions}`. |
| `framework__profile_flame` | `profile:flame <id>` | `{ok, svg}` — the inline SVG source. |

## Usage

### Programmatically (in-process)

The in-process `Profiler` is the library API — useful for bench harnesses and tests:

```php
use Altair\Profiling\Profiler;
use Altair\Profiling\Sampler\BackendDetector;

$sampler = (new BackendDetector())->detect(periodUs: 1_000);   // throws SamplerUnavailableException if no backend
$profiler = new Profiler($sampler);

$report = $profiler->profile(
    fn() => $checkout->run(),
    description: 'Checkout.run',
);

$report->totalSamples;       // 2418
$report->durationMs;         // 2412
$report->hotspots;           // list<Hotspot>
$report->tree;               // root CallNode
```

Save it:

```php
use Altair\Profiling\Storage\FilesystemProfileStorage;

(new FilesystemProfileStorage(getcwd() . '/.altair/profiles'))->save($report);
```

### Subprocess

For profiling an external script (or any `php`-invoked program), use the subprocess profiler — it spawns the target with excimer attached via `auto_prepend_file`, so the parent does not need the extension loaded:

```php
use Altair\Profiling\Runner\SubprocessProfiler;

$report = (new SubprocessProfiler(getcwd()))->run(
    command: ['tools/bench.php'],   // argv without the leading `php`
    description: 'bench.php',
    periodUs: 1_000,
    timeoutMs: 60_000,
);
```

### Compare two reports

```php
use Altair\Profiling\Diff\Differ;
use Altair\Profiling\Storage\FilesystemProfileStorage;

$storage = new FilesystemProfileStorage(getcwd() . '/.altair/profiles');
$diff = (new Differ())->diff($storage->load('id-before'), $storage->load('id-after'));

if ($diff->hasRegressions()) {
    foreach ($diff->regressions as $row) {
        echo $row->function, "  +", $row->deltaPercent, "%\n";
    }
}
```

## Configuration

The `profile:*` CLI commands build a default storage from the current working directory, so no Container wiring is required to use them. `ProfilingConfiguration` is for hosts (and the MCP server) that want an explicit profiles directory:

```php
use Altair\Profiling\Configuration\ProfilingConfiguration;

(new ProfilingConfiguration(
    profilesDirectory: '/var/log/altair/profiles',
    maxKept: 200,
))->apply($container);
```

## Testing

The published tests under `tests/Profiling/`:

- `Tree/TreeBuilderTest.php` — golden tests for the fold from samples → tree (identical stacks, diverging children, weighted samples, empty stack).
- `Tree/HotspotAnalyzerTest.php` — aggregation across call sites, percent calculation, top-N truncation.
- `Diff/DifferTest.php` — regression flagging, significance threshold, min-samples floor, brand-new functions, improvements.
- `Storage/FilesystemProfileStorageTest.php` — round-trip, newest-first listing, rotation past maxKept.
- `Output/FlamegraphRendererTest.php` — valid SVG output, XML escape of frame names, empty-tree placeholder.
- `Sampler/BackendDetectorTest.php` — install-hint exception when no backend, ExcimerSampler returned when loaded (skipped without `ext-excimer`).

All data-path tests run on a fresh checkout without any extension. The single sampler integration test is auto-skipped where excimer is missing.

## Related packages

- [`univeros/observatory`](./observatory.md) — the natural HUMAN consumer of profiling data. Observatory deliberately owns no data of its own and renders panels over what other packages produce; a future `profiles` panel reads from `.altair/profiles/` exactly the way the existing panels read from `events`/`queues`/`routes`.
- [`univeros/index`](./index.md) — Index answers "what does this change touch?" (impact); Profiling answers "what does this change cost?" (time). Together they are the refactor confidence loop.
- [`univeros/eval`](./eval.md) — Eval is the in-loop primitive for *correctness* checks; Profiling is the in-loop primitive for *performance* checks. Same shape (subprocess + JSON), different question.
- [`univeros/mcp`](./mcp.md) — exposes the five profiling tools.

## Limitations

- **Sampling backend is mandatory for capture.** `profile:run` exits `2` with an install hint if neither `ext-excimer` (preferred) nor `ext-xdebug` is loaded. `profile:list`/`show`/`compare`/`flame` work on any stored profile, regardless.
- **`ext-xdebug` adapter is a follow-up.** v1 ships excimer only; the `BackendDetector` is wired to add an XdebugSampler when the adapter lands. Use `ext-excimer` for now.
- **No always-on HTTP middleware capture in v1.** A `ProfilerMiddleware` that records per-request timing + DB queries + cache hits + queue dispatches into `.altair/profiles/` (the "always on, low overhead" mechanism in the design) is a documented follow-up; it needs deep integration with the Http package. Today, capture is on-demand via `profile:run`.
- **No per-package collectors (DB / cache / queue / HTTP egress) in v1.** Those need hooks into each package (Cycle's query log, the cache wrappers, Messenger's bus) and are a documented follow-up. Today, the sampler captures function-level time only.
- **Sampling is statistical.** One ms ticks miss functions whose self-time is less than a tick; the smallest "hot" function the default backend can resolve is around 1 ms total. Drop `--period-us=500` to halve that at twice the overhead.
- **Subprocess profiling spawns one process per `profile:run`.** No long-lived "profiler daemon" yet — each run is independent. Adequate for the bench/check-it-once workflow this v1 serves.
