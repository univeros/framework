# Migration Intelligence

> Takes a schema *diff* — a spec change, an entity-vs-database drift, or two versions of a spec — and proposes the migration to get from here to there: the ordered operations, a ready-to-apply Cycle migration class, per-dialect preview SQL, and a read-only safety report that counts the rows your change would break before you run it. Scannable text for you, deterministic JSON an agent or CI step can act on.

**Composer:** `univeros/migration-intelligence`
**Namespace:** `Altair\MigrationIntelligence`

## Introduction

Spec-driven scaffolding ([`univeros/scaffold`](./scaffold.md)) is great at greenfield: a `persistence:` block in, an entity + repository + create-table migration out. Real systems then *evolve* — rename a column, split a field, make something unique, drop a legacy column, tighten a nullable to `NOT NULL`. Each of those is a migration that can quietly fail (or, worse, take production down) when it meets existing data. `ALTER COLUMN ... SET NOT NULL` on a column that is 40% null does not warn you; it just errors mid-deploy.

Migration Intelligence is the layer that makes those changes routine. It computes the diff between a *current* and a *desired* table shape, turns it into typed **intents** (`add_column`, `rename_column`, `change_column`, `add_index`, `add_foreign_key`, `data_migration`, `drop_column`), and produces a `Cycle\Migrations\Migration` class you can review and apply with `bin/altair db:migrate`. Before it hands you that file, it runs a set of **safety checks** against the live database — read-only — and counts exactly how much data the change would break.

The design mirrors [`univeros/suggest`](./suggest.md) and [`univeros/doctor`](./doctor.md): a normalized model, a registry of small swappable rules/checks, an engine that aggregates, and human + JSON renderers. The pieces are pure functions wherever possible — the diff and the SQL preview are deterministic and need no database; only the safety checks and the schema reader touch a connection, and they degrade gracefully when one is absent.

The emitted migration is always a Cycle blueprint, never raw per-driver DDL. Cycle's dialect layer produces the real `ALTER`/`CREATE` at apply time, so one migration applies correctly on PostgreSQL, MySQL, and SQLite. The per-dialect SQL you see in the plan is a **preview** for review — what the change looks like on each driver — not the artifact that runs.

## Installation

Standalone:

```bash
composer require --dev univeros/migration-intelligence
```

You will usually want this as a dev dependency — it plans migrations during development, it is not part of your runtime. If you install the full framework, `composer require univeros/framework` already bundles it.

It depends on [`univeros/persistence`](./persistence.md) (the Cycle bridge + the `DB_*` connection settings it reads), [`univeros/scaffold`](./scaffold.md) (the `persistence:` spec AST it diffs), and `cycle/database`, plus `univeros/cli`, `univeros/configuration`, and `univeros/container`. The safety checks read the same `DB_*` environment the rest of the framework uses; no extra configuration is needed to enable them.

## Quick start

Diff a spec's desired shape against the live database and print the plan:

```bash
bin/altair db:migration-plan api/users.yaml
```

```
Proposed migration for table 'users':

database/migrations/20260528.143012_0_alter_users.php  [postgres]
  Operations:
    -> ALTER TABLE "users" ADD COLUMN "display_name" VARCHAR(255) NULL
    -> CREATE UNIQUE INDEX "users_email_unique" ON "users" ("email")
  Rollback:
    -> DROP INDEX "users_email_unique"
    -> ALTER TABLE "users" DROP COLUMN "display_name"

Safety:
  [error] Adding a UNIQUE index on 'email' will fail: 3 value(s) are duplicated. Dedup before applying.
```

That exits `1` — a safety error is a CI gate. Fix the data (or the spec) and re-run until it is clean, then write the migration file:

```bash
bin/altair db:migration-plan api/users.yaml --output=database/migrations
```

Diff two versions of a spec, no database required (safety is skipped — there is no live data to check):

```bash
bin/altair db:migration-plan --from-spec=api/users.v1.yaml --to-spec=api/users.v2.yaml
```

Diff a Cycle entity class against the database it maps to:

```bash
bin/altair db:migration-plan --from-entity="App\\User\\User"
```

Emit machine-readable JSON for an agent or CI step:

```bash
bin/altair db:migration-plan api/users.yaml --format=json
```

```json
{
    "table": "users",
    "two_phase": false,
    "migrations": [
        {
            "migration_name": "alter_users",
            "class_name": "M20260528143012AlterUsers",
            "filename": "database/migrations/20260528.143012_0_alter_users.php",
            "dialect": "postgres",
            "phase": "",
            "operations": [
                { "op": "add_column", "table": "users", "describe": "ADD COLUMN display_name string NULL" }
            ],
            "forward_sql": ["ALTER TABLE \"users\" ADD COLUMN \"display_name\" VARCHAR(255) NULL"],
            "rollback_sql": ["ALTER TABLE \"users\" DROP COLUMN \"display_name\""]
        }
    ],
    "safety": { "skipped": false, "has_errors": false, "has_warnings": false, "findings": [] },
    "exit_code": 0
}
```

The process exit code is `1` when any safety check raises an error, `2` on a usage error, otherwise `0`.

## Concepts

**One normalized shape, three readers.** Everything pivots through a dialect-agnostic `TableShape` (its `ColumnShape`s, `IndexShape`s, `ForeignKeyShape`s). Three readers all produce it, so the differ always compares like with like:

- `SpecSchemaReader` — a scaffolder `persistence:` block → shape (pure, no I/O);
- `EntitySchemaReader` — a Cycle-annotated entity's `#[Entity]`/`#[Column]` attributes → shape (reflection only);
- `DbSchemaReader` — a live Cycle connection's table introspection → shape.

Column types are normalized to one canonical vocabulary (`ColumnType`), so the differ never reports a spurious change just because a spec said `int` and the database reported `integer`. SQLite's loose type affinity collapses string columns to `text` on introspection, so `string` and `text` are treated as one family and an unknown declared type falls back to `string` — this avoids noise on SQLite without affecting the strict PostgreSQL/MySQL path.

**The differ is a pure function; renames are declared, not guessed.** `SchemaDiffer::diff($from, $to, $renames)` returns an ordered `list<IntentInterface>`. A structural diff cannot tell a rename from a drop-plus-add of an identically-typed column — so renames are declared with `--rename old:new`, and everything else (adds, drops, type/nullable/default changes, new indexes and foreign keys) is derived. A type change is classified *safe widening* (e.g. `integer → bigInteger`) or *incompatible* (e.g. `string → integer`); the latter drives both a safety check and two-phase planning.

**Renames become a safe two-phase plan.** Renaming in place breaks code that is mid-deploy. So a declared rename expands into two migrations: **phase 1** adds the new column (nullable) and copies the data; **phase 2** enforces the final `NOT NULL` and drops the old column. The two files share a timestamp and disambiguate by chunk index + a `Phase1`/`Phase2` class suffix, so Cycle applies them in order — and you deploy phase 2 only after phase 1 is verified in production.

**The emitted artifact is a Cycle migration, not raw DDL.** `CycleMigrationEmitter` renders a `Cycle\Migrations\Migration` subclass: contiguous schema operations batch into one `$this->table()->...->update()` chain, and a data migration flushes the chain and runs as raw `$this->database()->execute()` so a column add always lands before the copy that depends on it. Because it is a Cycle blueprint, the same file applies on every supported driver.

**Safety checks are read-only and degrade, never crash.** `SafetyRunner` runs each check over the intents against the live connection. No database (a spec-vs-spec diff) → the report is `skipped`. An unreachable database → `skipped` with the reason. A single failing query → an informational finding, not an abort. Identifiers in the raw count queries are validated against a strict pattern and quoted per driver before they ever touch SQL, so a hostile column name can never become an injection vector.

## Diff sources

| Invocation | Desired (`to`) | Current (`from`) | Safety |
|---|---|---|---|
| `db:migration-plan <spec.yaml>` | the spec's `persistence:` block | the live DB table | runs (needs `DB_*`) |
| `db:migration-plan --from-entity=FQCN` | the entity's Cycle attributes | the live DB table | runs (needs `DB_*`) |
| `db:migration-plan --from-spec=a --to-spec=b` | spec `b` | spec `a` | skipped (no live data) |

For the database-backed modes, a table that does not exist yet is reported as such (`use spec:scaffold to create it`) and the command exits `0` — Migration Intelligence evolves existing tables; creating new ones is the scaffolder's job.

## Safety checks

Each check is read-only, queries the dev database, and grades its finding `info` / `warn` / `error`. An `error` makes the plan exit `1`.

| Check | Triggers on | Queries | Severity |
|---|---|---|---|
| `not_null` | adding a `NOT NULL` column without a default to a populated table, or tightening a column that still holds NULLs | row count / null count | error |
| `unique` | a new single-column `UNIQUE` index | duplicate-group count | error (info for composite) |
| `foreign_key` | a new single-column foreign key | orphan-row count | error (info for composite) |
| `type_cast` | an *incompatible* type change | samples up to 100 values and checks they cast | error if any fail, else warn |
| `large_table` | any structural change to a table over the threshold (default 1,000,000 rows) | row count | warn |
| `drop_column` | dropping a column | non-null count | warn, or error when data remains and `--force` is absent |

Two checks need explicit acknowledgement: dropping a column with data, and the `type_cast` heuristic. `--force` lets a column drop proceed despite remaining data (it stays a warning). The `type_cast` check is a PHP-side heuristic over sampled values, not a database-side trial cast — it is honest about that in its message and grades a clean sample as a `warn` ("verify the full table"), not a false all-clear.

## MCP

The `framework__plan_migration` tool ([`univeros/mcp`](./mcp.md)) wraps `db:migration-plan --format=json`. It is read-only — planning never writes a file or mutates data — so it needs no `--allow-writes`. The agent calls it before committing to a refactor, reads the operations and the safety report, and decides whether to proceed:

```json
{
  "name": "framework__plan_migration",
  "input": { "spec": "api/users.yaml", "driver": "postgres" }
}
```

It returns `{ "ok": <exit 0>, "exit_code": <int>, "plan": { ...the JSON above... } }`. An unsafe plan comes back with `ok: false` and `exit_code: 1` but the full plan still attached, so the agent can read *why* it is unsafe.

## Configuration

`MigrationIntelligenceConfiguration` wires the differ, planner registry, readers, emitter, plan builder, and renderer registry as shared singletons in one `apply()` call:

```php
use Altair\MigrationIntelligence\Configuration\MigrationIntelligenceConfiguration;

(new MigrationIntelligenceConfiguration())->apply($container);
```

Every service is stateless. The database is not opened at boot: it is read on demand from `DB_*` env by `Db\DatabaseProbe`, which returns `null` (and the plan prints without safety checks) on any connection failure. `bin/altair` discovers `PlanCommand` through `CliConfiguration`'s command scan whether or not you apply this Configuration — applying it only matters for a host that resolves the services directly or binds its own renderer/planner set.

### Output formats

`RendererRegistry::default()` ships `human` and `json`; an unknown `--format` exits `2`. Bind a populated registry before bootstrapping to add your own renderer under a new `--format` key.

## Testing

The tests under `tests/MigrationIntelligence/` double as worked examples. The pure pieces (differ, planners, emitter, renderers) are tested with hand-built shapes and plans — no database. The schema reader and safety checks are tested against a real in-memory SQLite database built by `Support\SqliteDatabaseFactory`, so the Cycle introspection and the counting queries are exercised for real:

- [tests/MigrationIntelligence/Schema/](https://github.com/univeros/framework/blob/master/tests/MigrationIntelligence/Schema/) — the canonical type model and column equivalence.
- [tests/MigrationIntelligence/Reader/](https://github.com/univeros/framework/blob/master/tests/MigrationIntelligence/Reader/) — spec, entity-reflection, and live-SQLite readers.
- [tests/MigrationIntelligence/Diff/SchemaDifferTest.php](https://github.com/univeros/framework/blob/master/tests/MigrationIntelligence/Diff/SchemaDifferTest.php) — every intent kind, rename hints, safe vs. incompatible type changes.
- [tests/MigrationIntelligence/Planner/](https://github.com/univeros/framework/blob/master/tests/MigrationIntelligence/Planner/) — golden preview SQL per dialect, the SQLite ALTER-COLUMN note, driver-alias resolution.
- [tests/MigrationIntelligence/Safety/SafetyRunnerTest.php](https://github.com/univeros/framework/blob/master/tests/MigrationIntelligence/Safety/SafetyRunnerTest.php) — each check against seeded SQLite fixtures (duplicates, orphans, nulls, non-castable data, force).
- [tests/MigrationIntelligence/Plan/PlanBuilderTest.php](https://github.com/univeros/framework/blob/master/tests/MigrationIntelligence/Plan/PlanBuilderTest.php) — single-phase and two-phase rename expansion, deterministic naming.
- [tests/MigrationIntelligence/Emitter/CycleMigrationEmitterTest.php](https://github.com/univeros/framework/blob/master/tests/MigrationIntelligence/Emitter/CycleMigrationEmitterTest.php) — emitted migrations are asserted to be syntactically valid PHP (`php -l`).
- [tests/MigrationIntelligence/Cli/PlanCommandTest.php](https://github.com/univeros/framework/blob/master/tests/MigrationIntelligence/Cli/PlanCommandTest.php) — diff-source resolution, formats, file writing, exit codes.

## Extending

**A new safety check** implements `Safety\SafetyCheckInterface` (`check(IntentInterface $intent, RowCounter $rows): list<SafetyFinding>`) and is passed into a `SafetyRunner`. Use the `RowCounter` for any database access so identifier quoting and validation stay centralized; never build SQL from an unvalidated identifier.

**A new dialect** implements `Planner\DialectPlanner` (most easily by extending `AbstractDialectPlanner` and supplying `quote()`, `sqlType()`, and `alterColumn()`) and is registered in a `PlannerRegistry`. The emitted Cycle migration is dialect-agnostic, so a new planner only adds a preview surface — Cycle still owns apply-time DDL.

## Related packages

- [`univeros/persistence`](./persistence.md) — the Cycle bridge this builds on: it reads the same `DB_*` settings, introspects tables through Cycle, and the migrations it emits are run by `bin/altair db:migrate`.
- [`univeros/scaffold`](./scaffold.md) — owns the `persistence:` spec block that one diff source reads, and the *create*-table migration for brand-new tables. Migration Intelligence is the *evolve* counterpart.
- [`univeros/doctor`](./doctor.md) — the health sibling; its `database_reachable` check pairs naturally with the safety checks here (no reachable DB → safety is skipped).
- [`univeros/mcp`](./mcp.md) — exposes `framework__plan_migration`, the read-only agent entry point.
- [`univeros/cli`](./cli.md) — `PlanCommand` is a plain invokable registered through `#[Command(name: 'db:migration-plan')]`.

## Limitations

- **Renames must be declared.** A pure structural diff cannot distinguish a rename from a drop-plus-add; without `--rename old:new` the change is planned as a (destructive) drop and a (new) add.
- **Two-phase is rename-only in v1.** Incompatible type changes are planned single-phase with a strong safety finding; a two-phase add-cast-swap for type changes is a documented follow-up.
- **The `type_cast` check is a heuristic, not a database trial cast.** It samples up to 100 values and checks them in PHP. A clean sample is a `warn`, not a guarantee — verify the full table for very large or skewed data.
- **SQLite type fidelity is lossy.** SQLite's type affinity reports string columns as `text` and unrecognized declared types as `unknown`; the reader normalizes both to `string`, which is faithful enough for diffing but means a deliberate `string ↔ text` change is not detected against a SQLite database. PostgreSQL and MySQL report faithfully.
- **Preview SQL is for review, not execution.** The per-dialect statements (and the SQLite ALTER-COLUMN note) describe the change; the artifact that runs is the Cycle migration. On SQLite, an in-place `ALTER COLUMN` or `ADD FOREIGN KEY` is surfaced as a note because the real change is a table rebuild that Cycle performs at apply time.
- **Composite unique/foreign-key constraints are not row-checked.** They are flagged `info` with a reminder to verify manually; the row-counting checks cover the single-column case.
- **Column size/precision is not carried into the emitted migration.** Like the scaffolder, the emitter uses Cycle's abstract type names and defaults; the preview SQL shows `VARCHAR(255)` but the migration emits `string`.
```
