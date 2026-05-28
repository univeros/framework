# Positioning — Univeros, by 2am.tech

> Working positioning for the flagship. The brand is **Univeros** (the Composer
> vendor *is* the name, Laravel-style). The PHP namespace is `Altair\*` — a
> code-level codename, not the public brand. Everything below is the narrative,
> not the API.

---

## 1. One line

**Univeros is the framework where agents ship software, not shop for it.**

You describe intent; the framework deterministically produces the typed,
tested, documented implementation — so an AI agent spends its tokens on *what
to build*, never on boilerplate, exploration, or re-reading its own work.

## 2. What this actually is (and the 2am.tech angle)

Univeros is **2am.tech's open proof-of-work**. It is not a product we sell and
not a Laravel competitor. It is the framework we built to answer one question
in public: *how fast and how cheaply can an AI agent deliver production
software when the framework is designed for the agent from the core out?*

The pitch to a prospective client is not "use our framework." It is: **"this is
how we think about AI-assisted delivery — here it is, running, measured, and
reproducible."** The framework is the evidence; the engagement is the product.

## 3. Distribution — the shape PHP developers already know

Univeros deliberately ships in the exact package shape Laravel made the norm, so
a PHP developer (or their agent) starts it the way they already start everything
else. The vendor name is the brand, the same way `laravel` is.

| Laravel | Univeros | Role |
|---|---|---|
| `laravel/laravel` | **`univeros/univeros`** | the application starter you `composer create-project` |
| `laravel/framework` | `univeros/framework` | the bundled library (the monorepo) |
| `illuminate/*` | `univeros/*` (read-only subtree splits) | individually installable components |

The on-ramp is one familiar command:

```bash
composer create-project univeros/univeros my-api && cd my-api
bin/altair new --preset=standard
```

`create-project univeros/univeros` lands a runnable skeleton; `bin/altair new`
picks the ORM/queue, wires the env, and verifies a proof-of-life endpoint. The
familiarity is the point: **zero new ceremony to learn before the agent-native
parts pay off.** Choosing the well-known convention lowers adoption friction and
quietly signals "this is a real framework you start the way you already know" —
not a science project.

## 4. The wedge

Laravel won the last cycle against a heavier Symfony on a few novel
developer-experience ideas. Laravel is now the heavy incumbent: too large to
make core architectural changes, so it adds **layers beside the framework** —
Laravel Boost is an MCP server and a guidelines/skills/docs layer *next to*
Laravel, not inside it.

That is the opening. An incumbent can bolt agent tooling onto the side. It
cannot retrofit agent-native decisions into the core without breaking everyone.

**Univeros's bet: agent-native at the core, not agent-tooling on the side.**

- Boost makes an agent a *better freehand writer* of Laravel code.
- Univeros makes the agent *stop writing most of the code at all* — it compiles
  intent to artifacts, returns a machine receipt, and makes mistakes one command
  to undo.

Different philosophy, not a feature race: **deterministic regeneration vs.
better autocomplete.** And because Univeros adopts Laravel's packaging
convention, the comparison lands on philosophy, not on "yet another way to start
a project."

## 5. The thesis to brand around

> **The deterministic part is the framework's job. The intent is yours.**

Every token an agent burns today on plumbing, discovery, or re-verification is a
token a framework *should* eliminate by design. Univeros is organized entirely
around removing those token sinks.

| Token / time sink today | The layer that kills it | Agent payoff |
|---|---|---|
| Writing & reading boilerplate | **Intent compiler** — one spec emits Action/Input/Responder/entity/migration/test/OpenAPI/SDK | 1 spec edit, not N file writes |
| Re-reviewing its own output | **Deterministic output + JSON receipt** (files, SHAs, drift, test verdict) | Reads a 5-line receipt, never re-reads generated code |
| Parsing human-prose errors | **Machine-first surfaces** — one canonical verdict (`result: pass\|fail\|drift`) | Branches on one line, not a stack trace |
| Defensive "check before I act" loops | **Reversibility** — journaled, rewindable mutations | Act-then-verify; a mistake costs one `rewind` |
| Re-deriving project state each session | **Load-once self-map** — manifest/codemap + typed MCP tools + event log | Whole project shape in a few hundred tokens |

## 6. The "watch this" demo (90 seconds)

The flagship lives or dies on one demonstration. The story:

1. `composer create-project univeros/univeros blog && cd blog` — a runnable
   project in one familiar command, before the agent does anything clever.
2. An agent is handed: *"Add a Posts API: create, list, get-by-id, update,
   delete, with validation and persistence, plus a typed TypeScript client."*
3. The agent writes a short YAML spec (intent only — ~20 lines).
4. `spec:scaffold` emits the full implementation; the agent reads a compact JSON
   receipt, not the generated files.
5. `spec:emit-sdk typescript` emits the typed client.
6. The test reporter returns `{"result":"pass"}`; the agent stops.
7. Split-screen counter: **tokens spent vs. the same task on a conventional
   stack.** The gap is the whole pitch.

The demo's emotional beat is the moment the agent *doesn't* re-read its work —
because it doesn't need to. The opening beat is that the start is boringly
familiar: same `create-project` muscle memory, radically different middle.

## 7. Proof, not claims

A software company's credibility is its product, so the marketing rests on a
**reproducible benchmark**, not adjectives. See
[`docs/benchmarks/tokens-to-ship.md`](../benchmarks/tokens-to-ship.md): the same
defined feature, built by the same agent/model, on Univeros vs. a conventional
baseline, measured in **agent tokens and wallclock**, gated by an external
acceptance suite so "fast" can never mean "wrong."

The headline asset is one honest chart: *tokens-to-ship, Univeros vs. baseline.*

## 8. Honest scope (say this out loud)

- **Not** competing for framework market share. No "Laravel killer" language —
  ever. It reads as insecure and invites the wrong comparison. Borrowing
  Laravel's packaging convention is a courtesy to developers, not a challenge.
- **Not** the app-building AI layer (LLM calls, RAG). That space is taken
  (`laravel/ai`, Prism, NeuronAI, LLPhant). Univeros is about *building software
  with agents*, not *building AI into apps*.
- **Is** a focused demonstration of a specific, defensible idea: a
  deterministic, spec-driven, fully reversible framework where agents are
  first-class by architecture — and the runnable evidence that 2am.tech operates
  at that frontier.

## 9. Messaging kit

**Candidate taglines**
- "Spec in. Software out. Minimal tokens between."
- "Agents ship, not shop."
- "The deterministic part is the framework's job."
- "Build production APIs at agent speed."
- "Agent-native at the core — not bolted on the side."
- "Start it the way you know. Ship it faster than you'd believe."

**Words to avoid:** "Laravel killer," "replaces," "the only," "the first."
Lead with what it *does*, backed by the number.

**Tone:** confident, measured, evidence-first. The benchmark does the bragging.

**Primary CTA:** not "install" — **"watch the 90-second build"** and **"read the
methodology."** The conversion goal is a conversation with 2am.tech, not a
`composer create-project`.

## 10. Distribution (where this gets seen)

- A single landing page: hero one-liner, the 90-second video, the benchmark
  chart, a "how we build" link to 2am.tech services. The single copy-paste
  command (`composer create-project univeros/univeros …`) sits under the hero.
- One launch artifact: a written deep-dive ("We made an AI agent ship a
  production API in N tokens — here's the framework and the method"). Aim it at
  HN / Laravel News / r/PHP / dev.to. The method + honesty is the hook.
- Conference talk / lightning demo: the split-screen token counter is a great
  live moment.

The bias to resist: shipping breadth (the long roadmap) reads as a changelog.
**One flawless loop + one honest benchmark beats a long feature list** for a
marketing flagship.

---

## Appendix — naming notes

- **Brand / vendor:** Univeros (`univeros/*`). The vendor name is the brand,
  Laravel-style.
- **Application starter:** `univeros/univeros` — the empty repo reserved in 2017
  is the natural home for the `composer create-project` skeleton (issue #73's
  `bin/altair new` runs on top of it).
- **Library:** `univeros/framework` — the monorepo that bundles every component.
- **Components:** `univeros/<package>` — read-only subtree splits of the monorepo
  (e.g. `univeros/http`, `univeros/scaffold`), installable on their own.
- **PHP namespace:** `Altair\*` — a code-level codename. It does not need to
  match the brand (plenty of frameworks differ here); revisit only if a rename
  earns its cost.
