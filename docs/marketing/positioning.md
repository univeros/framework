# Positioning — Altair, by 2am.tech

> Working positioning for the flagship. The framework's marketing name (`Altair`)
> is a placeholder you can swap; the namespace is `Altair\*`, the package is
> `univeros/framework`. Everything below is the narrative, not the API.

---

## 1. One line

**Altair is the framework where agents ship software, not shop for it.**

You describe intent; the framework deterministically produces the typed,
tested, documented implementation — so an AI agent spends its tokens on *what
to build*, never on boilerplate, exploration, or re-reading its own work.

## 2. What this actually is (and the 2am.tech angle)

Altair is **2am.tech's open proof-of-work**. It is not a product we sell and
not a Laravel competitor. It is the framework we built to answer one question
in public: *how fast and how cheaply can an AI agent deliver production
software when the framework is designed for the agent from the core out?*

The pitch to a prospective client is not "use our framework." It is: **"this is
how we think about AI-assisted delivery — here it is, running, measured, and
reproducible."** The framework is the evidence; the engagement is the product.

## 3. The wedge

Laravel won the last cycle against a heavier Symfony on a few novel
developer-experience ideas. Laravel is now the heavy incumbent: too large to
make core architectural changes, so it adds **layers beside the framework** —
Laravel Boost is an MCP server and a guidelines/skills/docs layer *next to*
Laravel, not inside it.

That is the opening. An incumbent can bolt agent tooling onto the side. It
cannot retrofit agent-native decisions into the core without breaking everyone.

**Altair's bet: agent-native at the core, not agent-tooling on the side.**

- Boost makes an agent a *better freehand writer* of Laravel code.
- Altair makes the agent *stop writing most of the code at all* — it compiles
  intent to artifacts, returns a machine receipt, and makes mistakes one command
  to undo.

Different philosophy, not a feature race: **deterministic regeneration vs.
better autocomplete.**

## 4. The thesis to brand around

> **The deterministic part is the framework's job. The intent is yours.**

Every token an agent burns today on plumbing, discovery, or re-verification is a
token a framework *should* eliminate by design. Altair is organized entirely
around removing those token sinks.

| Token / time sink today | The layer that kills it | Agent payoff |
|---|---|---|
| Writing & reading boilerplate | **Intent compiler** — one spec emits Action/Input/Responder/entity/migration/test/OpenAPI/SDK | 1 spec edit, not N file writes |
| Re-reviewing its own output | **Deterministic output + JSON receipt** (files, SHAs, drift, test verdict) | Reads a 5-line receipt, never re-reads generated code |
| Parsing human-prose errors | **Machine-first surfaces** — one canonical verdict (`result: pass\|fail\|drift`) | Branches on one line, not a stack trace |
| Defensive "check before I act" loops | **Reversibility** — journaled, rewindable mutations | Act-then-verify; a mistake costs one `rewind` |
| Re-deriving project state each session | **Load-once self-map** — manifest/codemap + typed MCP tools + event log | Whole project shape in a few hundred tokens |

## 5. The "watch this" demo (90 seconds)

The flagship lives or dies on one demonstration. The story:

1. An agent is handed: *"Add a Posts API: create, list, get-by-id, update,
   delete, with validation and persistence, plus a typed TypeScript client."*
2. The agent writes a short YAML spec (intent only — ~20 lines).
3. `spec:scaffold` emits the full implementation; the agent reads a compact JSON
   receipt, not the generated files.
4. `spec:emit-sdk typescript` emits the typed client.
5. The test reporter returns `{"result":"pass"}`; the agent stops.
6. Split-screen counter: **tokens spent vs. the same task on a conventional
   stack.** The gap is the whole pitch.

The demo's emotional beat is the moment the agent *doesn't* re-read its work —
because it doesn't need to.

## 6. Proof, not claims

A software company's credibility is its product, so the marketing rests on a
**reproducible benchmark**, not adjectives. See
[`docs/benchmarks/tokens-to-ship.md`](../benchmarks/tokens-to-ship.md): the same
defined feature, built by the same agent/model, on Altair vs. a conventional
baseline, measured in **agent tokens and wallclock**, gated by an external
acceptance suite so "fast" can never mean "wrong."

The headline asset is one honest chart: *tokens-to-ship, Altair vs. baseline.*

## 7. Honest scope (say this out loud)

- **Not** competing for framework market share. No "Laravel killer" language —
  ever. It reads as insecure and invites the wrong comparison.
- **Not** the app-building AI layer (LLM calls, RAG). That space is taken
  (`laravel/ai`, Prism, NeuronAI, LLPhant). Altair is about *building software
  with agents*, not *building AI into apps*.
- **Is** a focused demonstration of a specific, defensible idea: a
  deterministic, spec-driven, fully reversible framework where agents are
  first-class by architecture — and the runnable evidence that 2am.tech operates
  at that frontier.

## 8. Messaging kit

**Candidate taglines**
- "Spec in. Software out. Minimal tokens between."
- "Agents ship, not shop."
- "The deterministic part is the framework's job."
- "Build production APIs at agent speed."
- "Agent-native at the core — not bolted on the side."

**Words to avoid:** "Laravel killer," "replaces," "the only," "the first."
Lead with what it *does*, backed by the number.

**Tone:** confident, measured, evidence-first. The benchmark does the bragging.

**Primary CTA:** not "install" — **"watch the 90-second build"** and **"read the
methodology."** The conversion goal is a conversation with 2am.tech, not a
`composer require`.

## 9. Distribution (where this gets seen)

- A single landing page: hero one-liner, the 90-second video, the benchmark
  chart, a "how we build" link to 2am.tech services.
- One launch artifact: a written deep-dive ("We made an AI agent ship a
  production API in N tokens — here's the framework and the method"). Aim it at
  HN / Laravel News / r/PHP / dev.to. The method + honesty is the hook.
- Conference talk / lightning demo: the split-screen token counter is a great
  live moment.

The bias to resist: shipping breadth (19 roadmap features) reads as a changelog.
**One flawless loop + one honest benchmark beats a long feature list** for a
marketing flagship.
