# AI-Driven Strategy Spec — Altair, by 2am.tech

> Companion to [positioning](../marketing/positioning.md) and the
> [tokens-to-ship benchmark](../benchmarks/tokens-to-ship.md). This is the
> *strategy*, not an implementation plan — decisions here gate what gets built.
> Living document; revise as reality teaches us.

---

## 0. The reframe that drives everything

Two kinds of "lazy" point at two different markets:

- **Yii laziness (developers):** "One command and voilà — done." Wants maximum
  output per unit of effort. Will `composer require`.
- **The bigger laziness (everyone else):** *won't even learn a framework.* They
  don't want Laravel, or PHP, or "a framework" at all. **They want an app that
  works and does X, Y, Z.** They will never open a terminal — they'll talk to an
  agent.

The second group is larger, growing faster, and far less served. AI is what
makes serving it possible. **We design for that group as the destination, and
earn credibility + revenue through developers and 2am.tech engagements on the
way there.**

## 1. The insight: AI changes *who the user is*

Before AI, a framework's user is a developer, so frameworks compete on
developer experience ("easier to learn than the other one").

After AI, for the outcome-seeker, **the framework's operator is an agent** and
the human only states intent. That inverts the design goals:

- "Easy for a human to learn" becomes irrelevant.
- "Reliable for an agent to operate" becomes everything.
- The human surface is *natural language → intent*; the agent turns intent into
  specs; the framework deterministically turns specs into working software.

This is why determinism, reversibility, and machine-first surfaces are not
nice-to-haves. They are the **enabling conditions** for trustworthy
agent-generated apps. A framework an agent can operate without breaking things
beats a framework a human finds pleasant.

## 2. Landscape (honest)

| Player | Serves | What they do | The gap they leave |
|---|---|---|---|
| **Laravel** (+ Cashier, Boost, laravel/ai) | Developers | Mature framework; agent tooling **bolted on the side** (Boost MCP) | Not aimed at "no framework knowledge → working app"; agent-nativeness is external |
| **AI app builders** (v0, Lovable, Bolt, Replit Agent) | Outcome-seekers | Generate full-stack JS fast | Output is often **fragile, throwaway, weak on real backend** (payments, data integrity, webhooks, migrations); optimized for the demo, not the maintainable app |
| **No-code** (Bubble, Retool) | Outcome-seekers | Visual app building | Lock-in, ceilings, you don't own real code |

**The unclaimed seam:** *a reliable backend/app substrate an agent operates to
produce real, owned, maintainable apps — with the hard backend features done
correctly.* Nobody owns "AI-built apps that actually keep working, with code you
own." That is the wedge, and our deterministic/reversible core is exactly what
the JS app-builders cannot easily retrofit.

## 3. Product thesis

> **Describe the app you want. An agent builds it on a substrate engineered so
> the result actually works, keeps working, and is yours to own.**

Three pillars:

1. **Intent → spec → working software, deterministically.** The Yii "voilà,"
   now spoken in plain English.
2. **Reliability by construction.** Features come from proven, tested,
   reversible spec modules — not freehand generation. Mistakes cost one
   `rewind`.
3. **You own the code.** Real PHP, real database, exportable, no lock-in. This
   is the answer to both no-code (lock-in) and JS builders (throwaway).

## 4. The "X, Y, Z features" product: a capability catalog

The user's own phrasing — *"an app that works with xyz features"* — **is** the
product. Operationalize it as a **catalog of composable, agent-installable
capability modules.** Each module is a deterministic spec module that emits
entity + migration + endpoints + tests + OpenAPI + typed client, **with the
painful edge-cases already handled**:

- auth (sessions, tokens, social login)
- payments / billing (Stripe-first; idempotent webhooks; reconciliation)
- notifications (email / SMS / push; retries; outbox)
- file uploads (storage, validation, scan hook)
- search, CRUD resources, admin surface, audit log, rate limiting, multi-tenancy

> *"I want an app for X with payments and email notifications"* → the agent
> selects catalog modules → composes them → working, owned app.

Each module is **simultaneously** a cheap stand-alone tool for developers (Tier
A) **and** a building block for the "describe → app" experience (Tier B). The
catalog is the moat: a growing library of *correct, reversible,
agent-installable* capabilities is something neither Laravel (human-first
packages) nor the JS builders (freehand generation) have.

This reframes the roadmap: **stop building "framework features"; start building
"capability modules" with demonstrable outcomes.**

## 5. Gap map → sequenced shortlist

| # | Module | Gap it fills | Tier | Adoption cost | Dev + agent payoff | Demo hook |
|---|---|---|---|---|---|---|
| 1 | **Webhook + idempotency + outbox** | Universal "react to an untrusted callback exactly once" — unowned, hand-rolled, buggy | A + B | `composer require` | Exactly-once reliability for any provider | "GitHub/Stripe webhook handled correctly from one spec" |
| 2 | **Intent compiler (Gii-for-AI)**, framework-agnostic | Boilerplate elimination; the Yii lever | A → B substrate | CLI, no lock-in | One spec → CRUD + persistence + OpenAPI + SDK + tests | the 90-second build |
| 3 | **Stripe payments kit** (builds on #1) | Correct billing plumbing nobody owns generically | A + B | module | Idempotent webhooks, state machine, reconciliation, sandbox | "subscriptions that don't double-charge" |
| 4 | **MCP "ship a feature" server** (generative + reversible) | Agent operability in *any* project | B | point your agent at it | Agent scaffolds / migrates / rewinds anywhere | agent live-builds an endpoint on camera |
| 5 | **Capability catalog + composer** ("describe app → working app") | The Tier-B destination | B | hosted / agent-driven | Natural-language app assembly from proven modules | the flagship demo |

**Sequence:** #1 → #2 → (#3 ∥ #4) → #5. Each ships real value and a marketing
moment on its own; #5 is the synthesis everything builds toward.

**First move:** #1 (webhook+idempotency+outbox). It's cheap to adopt, broadly
useful beyond payments, demonstrable in 60 seconds, fills a *real* gap, and is
the foundation #3 sits on. #2 (the intent compiler) is the close second and the
purest showcase.

## 6. How each step feeds 2am.tech marketing

- Every module is a launchable artifact (Laravel News / HN / r/PHP / dev.to) and
  a lead-gen demo — adoption *is* the marketing.
- The [tokens-to-ship benchmark](../benchmarks/tokens-to-ship.md) is the
  credibility spine under all of it.
- "Describe app → working app" is the showpiece that says *"2am.tech builds at
  the frontier"* louder than any written case study.

## 7. Discipline — what NOT to do

- **Don't** make a learn-it framework the adoption unit (that's the switching
  cost that protects Laravel; we route around it).
- **Don't** start with a multi-provider payment abstraction — Stripe first, with
  escape hatches.
- **Don't** chase Laravel feature-parity — chase the property-gaps
  (agent-operability, determinism, reversibility) and the unowned edge-cases.
- **Don't** fall into the v0/Lovable trap of demo-only output. Maintainability +
  ownership is our differentiator; if a generated app isn't genuinely
  maintainable, we've lost our only edge.
- **Don't** ship breadth as a changelog; ship depth as demos.

## 8. Risks / threats to validity (name them)

- **Tier B is hard and crowded.** Our bet is *backend reliability + ownership*,
  which must be **visibly, demonstrably true** — not asserted. The reversibility
  and maintainability have to show up in the demo.
- **PHP perception.** Tier-B founders assume JS/Python. Counter: they don't care
  about the language if the app works and they own it. Lead with outcomes, never
  with "PHP."
- **Catalog quality is the moat — and the liability.** One fragile module
  poisons trust. Every module ships behind an acceptance suite (same philosophy
  as the benchmark). Quality gate is non-negotiable.
- **Capacity (solo + agent).** Sequence ruthlessly; resist parallel half-builds.
  #1 and #2 before anything else.

## 9. Open questions to resolve before building #5

1. **Hosted vs. self-hosted** for the "describe → app" experience? Hosted =
   monetization + control of the experience; self-hosted = ownership purity.
   (Likely: hosted experience that *exports* an owned, self-hostable codebase —
   best of both.)
2. **Which five catalog modules ship first?** Decide by "most-requested real app
   features," not by what's easiest to build.
3. **Brand** for the Tier-B experience — probably distinct from "Altair the
   framework," since Tier B never needs to know the framework's name.

---

### TL;DR

Two markets: developers who want less effort, and a bigger group who won't learn
a framework at all and just want a working app. AI lets us serve the second by
making the framework's *operator* an agent. The unclaimed seam is **reliable,
owned, maintainable AI-built apps** — which our deterministic, reversible,
spec-driven core is uniquely suited to. Build it as a **catalog of
agent-installable capability modules**; each module is a cheap developer tool
*and* a building block for "describe → app." Start with webhook+idempotency, then
the intent compiler. Honest benchmarks and live demos are the marketing.
