# Benchmark methodology: "Tokens to Ship"

> Because a software company's reputation rests on its honesty, this methodology
> is designed to be **defensible first, flattering second.** A benchmark that
> doesn't survive scrutiny is worse than no benchmark.

---

## 1. The metric

**Tokens-to-ship**: the total agent tokens (input + output) consumed to take a
fixed, realistic feature from a cold prompt to **passing an external acceptance
suite**, on each of two arms.

We report tokens as the headline, plus four supporting metrics:

| Metric | Why it matters |
|---|---|
| **Total tokens** (input + output) | The cost/efficiency headline. |
| **Wallclock** | Human-felt speed. |
| **Agent turns** | Round-trips with the model (latency + cost proxy). |
| **Tool calls / file reads** | Direct measure of "exploration & re-review" cost. |
| **pass@1** | Did acceptance pass on the *first* completed attempt? Guards against "fast but wrong." |

The acceptance suite is the referee. **"Faster" only counts if the output is
correct, typed, tested, and documented to the same bar on both arms.**

## 2. The task (frozen)

A single realistic feature, specified once and never changed:
[`benchmarks/tokens-to-ship/task.md`](https://github.com/univeros/framework/blob/master/benchmarks/tokens-to-ship/task.md).

> Build a **Posts** REST resource: `POST /posts`, `GET /posts`, `GET /posts/{id}`,
> `PUT /posts/{id}`, `DELETE /posts/{id}`. Input validation, persistence
> (entity + migration + repository), an OpenAPI 3.1 description, a typed
> TypeScript client, and tests for the happy path + validation failures.

The task is deliberately a **CRUD-with-plumbing** feature, the bread-and-butter
of agency work, not a contrived codegen-friendly toy and not an
algorithmically hard problem. (See §7 for why this choice is fair and where it
isn't.)

## 3. The two arms

Both arms use the **same model, same agent runner, same allowed tools, same
acceptance suite, same cold-start context.** Only the framework differs.

- **Arm A: Altair.** The agent writes the YAML spec(s), runs `spec:scaffold`,
  reads the JSON receipt, runs the test reporter, and emits the SDK with
  `spec:emit-sdk`. It is *permitted* to re-read generated files but not
  *required* to; the receipt + verdict are designed to make re-reading
  unnecessary.
- **Arm B: Baseline.** The agent hand-builds the same artifacts on a
  conventional PHP setup (a minimal PSR-15 + a query-builder/ORM stack, *or*
  vanilla Laravel without Boost, pick one and document it). Same acceptance
  bar.

> **Baseline honesty:** document exactly which baseline was used and its version.
> Run a Laravel-without-Boost arm *and* (optionally) a Laravel-with-Boost arm.
> If you only beat an unrealistically bare baseline, the result is worthless.
> The strongest claim is the one made against a *good* baseline.

## 4. Fairness controls

1. **Same model + temperature.** Record exact model ID and settings.
2. **Same system/agent prompt scaffolding**, minus framework-specific tool docs
   each arm legitimately ships with (Altair's CLI/MCP docs vs. the baseline's
   framework docs). This asymmetry is *the thing being measured*, but it must
   be a fair, real-world setup for each, not a handicap.
3. **N repetitions** (recommend N ≥ 5 per arm). Report **median** and the
   full spread (min/max), never a single lucky run.
4. **Cold start.** Each run begins with no prior conversation about the task.
5. **Identical acceptance suite**, run by the harness, not the agent. The agent
   does not get to declare victory.
6. **Frozen task & frozen acceptance suite** across all runs and arms. Any change
   invalidates prior runs.
7. **Pre-registered expectation.** Write down the hypothesis and the stopping
   rule *before* running, so results aren't quietly cherry-picked.

## 5. Token accounting

Tokens are read from the agent runner's per-turn usage, summed across the
session:

- **Boundaries.** Start = the moment the task prompt is sent. End = the moment
  the acceptance suite first passes. Everything between counts; environment
  setup (composer install, DB up) is **excluded** and done identically for both
  arms beforehand.
- **Source of truth.** Capture `usage.input_tokens` + `usage.output_tokens`
  (and cache-read/-write tokens, reported separately) from each model response.
  With the Claude Agent SDK this is available per turn; with a recorded
  transcript, sum the usage blocks.
- **Cache handling.** Report raw input tokens *and* cache-adjusted tokens.
  Caching helps both arms; show both so neither side is flattered by it.
- **No human edits.** If a human touches the code mid-run, the run is void.

## 6. Running it

Two supported protocols, in increasing rigor:

- **Scripted-agent (preferred).** Drive each arm with the **Claude Agent SDK**:
  one system prompt, the task as the user turn, the arm's tools, a turn cap.
  The harness captures usage per turn automatically and stops when acceptance
  passes or the cap is hit. Fully reproducible.
- **Recorded-transcript (fallback).** Run the task interactively in Claude Code,
  export the transcript, and feed its usage blocks to the scorer. Less hands-off
  but still auditable.

Either way, per-run records land in a usage log and are aggregated by
[`benchmarks/tokens-to-ship/score.php`](https://github.com/univeros/framework/blob/master/benchmarks/tokens-to-ship/score.php)
into `results/results.json` plus a printed table.

## 7. Threats to validity (state these publicly)

Publishing the weaknesses is what makes the strength believable.

- **Task selection bias.** CRUD-with-plumbing structurally favors deterministic
  codegen. That is a *real* advantage for this class of work, which is most
  agency work, but do **not** generalize it to "Altair is N× faster at
  everything." Scope the claim to the task class. Consider a second,
  non-CRUD task to show where the gap shrinks; reporting that *increases* trust.
- **Baseline strength.** A weak baseline inflates the result. Use a credible,
  current baseline and say which.
- **Model variance.** Hence N runs + median + spread.
- **Maturity asymmetry.** Altair is new and narrow; the baseline is mature and
  general. Note it.
- **Author effect.** The people who built Altair run the benchmark. Mitigate by
  publishing the full harness, task, acceptance suite, and raw logs so anyone
  can rerun it. Reproducibility is the answer to "you're biased."

## 8. Reporting

- **Headline:** median total tokens, Altair vs. baseline, with the ratio.
- **Chart:** grouped bars (tokens, turns, wallclock), Altair vs. baseline(s),
  error bars = spread.
- **Table:** every metric, every arm, N, median, min, max, pass@1.
- **Receipts:** link the raw usage logs and the exact task + acceptance commit
  SHA.
- **Caveats:** §7, in plain language, on the same page as the chart.

The goal is a number a skeptical senior engineer reads and thinks *"that's
honest, and it's still impressive."* That reaction is the entire marketing win.
