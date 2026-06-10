# Index

> A symbol-usage index built from the PHP AST plus spec awareness. It answers the questions a refactor depends on (*what uses this? who implements this interface? who calls this method? what is dead? what does changing this touch?*) in milliseconds, as scannable text for you and deterministic JSON an agent or CI step can act on. SQLite-backed, with incremental rebuilds.

**Composer:** `univeros/index`
**Namespace:** `Altair\Index`

## Introduction

Refactoring is the activity that separates a real codebase from a demo, and it hinges on one question: *what depends on this?* Grep answers it badly: it matches comments, docblocks, and unrelated identifiers, and it cannot tell a method call from a same-named property. Reading every file by hand does not scale. Agents, lacking IDE indexes, either over-refactor (changing too much) or under-refactor (avoiding necessary changes) because they cannot answer the question with confidence.

Index is that answer. It walks every PHP file with [nikic/php-parser](https://github.com/nikic/PHP-Parser), resolves names to their fully-qualified form, and records two things: every **symbol** a file *declares* (class, interface, trait, enum, method, property, constant) and every **usage** a file *makes* (a `new`, an `extends`, a type hint, a call, a property access, an attribute, a class-constant fetch). It also understands the framework's higher-level constructs: an endpoint spec's `domain` handler and generated Action, and a `persistence:` block's entity and repository, become `spec_endpoint` / `spec_entity` usages, so `find-usages App\User\User` surfaces the YAML specs that drive it, not just the PHP that references it.

Everything lands in a single SQLite database at `.altair/index.db` (gitignored, as it is a derived artifact). SQLite was chosen for zero setup, millisecond queries over 100k+ rows, and human inspectability with the `sqlite3` CLI. The index stays correct on a fast-moving session through **incremental rebuilds**: each file's content hash is tracked, and a rebuild re-walks only files whose hash changed and drops files that disappeared. The query commands rebuild incrementally before answering by default, so results always reflect the current source.

## Installation

Standalone:

```bash
composer require --dev univeros/index
```

You will usually want this as a dev dependency: it indexes your *codebase*, not your runtime. If you install the full framework, `composer require univeros/framework` already bundles it.

It depends on [`univeros/scaffold`](./scaffold.md) (to parse endpoint specs for the framework-aware usages) and `nikic/php-parser`, plus `univeros/cli`, `univeros/configuration`, and `univeros/container`. The only PHP extension it needs is `ext-pdo` with SQLite, which ships with PHP; there is no SQLite server or daemon.

## Quick start

Build the index, then ask it questions:

```bash
bin/altair index:build
# Index built: 8663 symbols, 21131 usages across 1490 files (indexed 1490, skipped 0, removed 0) in 1794ms.
```

```bash
bin/altair index:find-usages "Altair\Container\Container"
bin/altair index:implements "Altair\Http\Contracts\MiddlewareInterface"
bin/altair index:extends "App\Base\Entity"
bin/altair index:callers-of "App\User\CreateUser::__invoke"
bin/altair index:unused
bin/altair index:orphans
bin/altair index:impact "App\User\User,App\User\Email"
```

The query commands rebuild the index incrementally before answering, so you rarely call `index:build` by hand; it is there for an explicit full rebuild (and for CI, where you build once and then query with `--no-build`). Add `--format=json` to any command for structured output:

```bash
bin/altair index:impact "App\User\User" --format=json
```

```json
{
    "symbols": ["App\\User\\User"],
    "impact": { "files": 23, "tests": 8, "specs": 3 },
    "by_file": [ { "file": "src/User/UserRepository.php", "usages": 6 }, "..." ],
    "tests_to_run": ["tests/Http/Actions/CreateUserActionTest.php", "..."],
    "specs_affected": ["api/users/create.yaml", "..."]
}
```

`tests_to_run` is the payload that closes the refactor loop: before declaring a change done, run only the tests that actually touch the symbols you changed.

## Concepts

**A symbol is a declaration; a usage is a reference.** Symbols are keyed by their fully-qualified name: a class is `App\User\User`, a method is `App\User\User::register`, a property is `App\User\User::$email`, a constant is `App\User\User::STATUS`. A usage records the *target* symbol's FQN, the file and line where the reference occurs, the `usage_kind`, and the enclosing scope as `context` (the calling method for a call, the declaring class for an `extends`/`implements`). Class-level usages therefore name their subject, which is how `implements` and `extends` queries return the implementing/extending class.

**Resolution is AST-only: it never infers runtime types.** This is the central honesty of the design. References whose target is a name in the source are resolved exactly: `new X`, `extends`/`implements`, type hints (params, returns, properties, unpacked from nullable/union/intersection types), attributes, `X::class`, `Class::CONST`, static calls `Class::method()`, and static property access `Class::$prop`. Calls and property access through `$this->`, `self::`, `parent::`, and `static::` are resolved against the enclosing class. But a call on an untyped variable (`$service->handle()` where `$service` is `object` or untyped) **is not linked**, because knowing its class needs type inference, which is PHPStan's job, not an indexer's. The package is precise about the subset it can resolve rather than guessing across the part it cannot.

**The framework layer adds spec awareness.** The `YamlSpecWalker` turns each endpoint spec into usages: a `spec_endpoint` of the `domain` class and of the generated Action FQCN, and (with a `persistence:` block) a `spec_entity` of the entity and its repository. This is what lets a refactor see that renaming an entity will break a spec, not just PHP.

**The index is content-hash incremental.** Each file's `xxh128` content hash is stored. A full build truncates and re-walks everything; an incremental build re-walks only changed files and removes vanished ones. Because the trigger is the content hash, a `touch` that does not change bytes is correctly skipped.

**Output is deterministic.** Files are scanned in sorted order, queries order their rows, and JSON uses a fixed shape with unescaped slashes (so paths read naturally). Two runs over the same source produce byte-identical output apart from `duration_ms`.

## CLI surface

| Command | Answers | Exit code |
|---|---|---|
| `index:build [--incremental]` | (re)build the index | `0` |
| `index:find-usages <symbol>` | every reference to a class/method/property/constant | `0` |
| `index:implements <interface>` | classes that `implements` it | `0` |
| `index:extends <class>` | classes/interfaces that `extends` it | `0` |
| `index:callers-of <method>` | resolved call sites of a method | `0` |
| `index:unused [--strict]` | symbols with zero references (dead-code candidates) | `1` with `--strict` if any |
| `index:orphans` | spec endpoints/entities naming an undeclared class | `1` if any |
| `index:impact <a,b,...>` | files/tests/specs a change touches | `0` |

Every command takes `--format=human|json` and `--no-build` (query the existing index without an incremental rebuild first; exits `2` if no index exists yet). Commands resolve the project from the current working directory and write to `.altair/index.db`.

## MCP tools

[`univeros/mcp`](./mcp.md) exposes five read-only tools that wrap the CLI, so a shell-less or remote agent gets the same refactor intelligence:

| Tool | Wraps | Returns |
|---|---|---|
| `framework__find_usages` | `index:find-usages` (optional `kind` filter) | `{symbol, count, usages: [{file, line, usage_kind, context}]}` |
| `framework__implementers` | `index:implements` | `{interface, count, implementers: [...]}` |
| `framework__callers` | `index:callers-of` | `{method, count, callers: [...]}` |
| `framework__dead_code` | `index:unused` | `{count, symbols: [...]}` |
| `framework__impact` | `index:impact` | `{symbols, impact, by_file, tests_to_run, specs_affected}` |

`framework__impact` is the key one for refactor confidence: given the symbols an agent plans to change, it returns the exact tests to run before declaring the change done.

## Usage

### Querying programmatically

`ProjectIndex` is the facade: it opens one shared SQLite connection and hands out the builder and the read queries:

```php
use Altair\Index\Support\ProjectIndex;

$index = ProjectIndex::fromCwd();        // or new ProjectIndex(IndexConfig::forRoot($root))
$index->ensureFresh();                   // incremental rebuild (full the first time)

$usages       = $index->usages()->usages('App\User\User');           // list<Usage>
$implementers = $index->usages()->implementers(MiddlewareInterface::class); // list<string>
$callers      = $index->usages()->callers('App\User\CreateUser::__invoke'); // list<Usage>
$dead         = $index->usages()->unused();                          // list<Symbol>
$report       = $index->impact()->impact(['App\User\User']);         // ImpactReport
```

### Building directly

```php
use Altair\Index\Builder\IndexBuilder;
use Altair\Index\Builder\IndexConfig;
use Altair\Index\Builder\SourceScanner;
use Altair\Index\Storage\Connection;
use Altair\Index\Storage\SqliteStorage;

$config  = IndexConfig::forRoot(getcwd());                 // scans src/, app/, tests/; specs in api/
$storage = new SqliteStorage(Connection::open($config->databasePath));
$result  = (new IndexBuilder($config, $storage, new SourceScanner($config)))->build(incremental: true);

$result->filesIndexed;   // re-walked
$result->filesSkipped;   // unchanged
$result->symbolCount;    // total in the index
```

### Walking a single file

The walkers are pure and need no database; useful for tests or one-off analysis:

```php
use Altair\Index\Parser\PhpFileWalker;

$parsed = (new PhpFileWalker())->walk('src/User.php', file_get_contents('src/User.php'));
$parsed->symbols;   // list<Symbol>
$parsed->usages;    // list<Usage>
```

## Configuration

The `index:*` commands build a `ProjectIndex` from the current working directory, so no Container wiring is required to use the CLI. `IndexConfiguration` is for hosts (and the MCP server) that want an explicit project root or to inject the queries elsewhere:

```php
use Altair\Index\Configuration\IndexConfiguration;

(new IndexConfiguration(
    projectRoot: '/path/to/app',   // defaults to getcwd()
    databasePath: null,            // defaults to <root>/.altair/index.db
))->apply($container);

$container->make(UsageQuery::class);   // shared, against the configured project
```

`IndexConfig` controls what is scanned: `sourcePaths` (default `src`, `app`, `tests`), `specPaths` (default `api`), and `excludeDirs` (default `vendor`, `node_modules`, `.git`, `.altair`, `build`, `runtime`). Excluded directories are pruned during traversal, so a large `vendor/` is never descended.

## Testing

The published tests under `tests/Index/` double as worked examples:

- [tests/Index/Parser/PhpFileWalkerTest.php](https://github.com/univeros/framework/blob/master/tests/Index/Parser/PhpFileWalkerTest.php): golden tests for every symbol kind and every usage kind, including the deliberate non-linking of untyped instance calls.
- [tests/Index/Parser/YamlSpecWalkerTest.php](https://github.com/univeros/framework/blob/master/tests/Index/Parser/YamlSpecWalkerTest.php): `spec_endpoint`/`spec_entity` extraction.
- [tests/Index/Query/QueryLayerTest.php](https://github.com/univeros/framework/blob/master/tests/Index/Query/QueryLayerTest.php): find-usages, implementers, extenders, callers, unused (with a true-positive dead-code fixture), impact, and orphans over a hand-seeded database.
- [tests/Index/Builder/IndexBuilderTest.php](https://github.com/univeros/framework/blob/master/tests/Index/Builder/IndexBuilderTest.php): full build, incremental skip-on-unchanged, and deletion handling over a real temp project.
- [tests/Index/Cli/CommandsTest.php](https://github.com/univeros/framework/blob/master/tests/Index/Cli/CommandsTest.php): every command end-to-end, including exit codes and the `--no-build` bail.

Walkers and queries are deterministic and need no Container, so tests hand-build source strings or seed the storage directly and assert on the result.

## Related packages

- [`univeros/suggest`](./suggest.md): the sibling adviser. Suggest reasons over the *runtime* introspection surface ("what is wired, and what looks wrong?"); Index reasons over the *source* AST ("where is this used, and what does changing it touch?"). They are complementary: Suggest's `dead_binding` is a runtime-graph heuristic, Index's `unused` is a source-reference fact.
- [`univeros/scaffold`](./scaffold.md): Index parses its endpoint specs to produce the `spec_endpoint`/`spec_entity` usages that connect a YAML spec to the PHP it generates.
- [`univeros/mcp`](./mcp.md): exposes the five read-only index tools so shell-less and remote agents share the refactor intelligence.
- [`univeros/cli`](./cli.md): the `index:*` commands are plain invokables registered through `#[Command]`; `--format`/`--no-build` are `#[Option]`s.

## Limitations

- **AST-only resolution: no type inference.** A method call or property access on an untyped variable (`$x->handle()`) is not linked to a declaring class; only `$this->`, `self::`, `parent::`, `static::`, and `Class::` forms resolve. Deep type-flow analysis is PHPStan's domain, deliberately out of scope.
- **`unused` lists *candidates*, not proven dead code.** Framework entry points reached by dispatch the AST cannot see (an Action's `__invoke`, a route handler, a public API method called only through an untyped variable) appear as unused. The count is large on a real codebase for exactly this reason; treat it as a starting list to review, gate CI with `--strict` only where you understand the false-positive surface.
- **Spec usages have no line number.** The endpoint-spec AST carries no YAML line information, so `spec_endpoint`/`spec_entity` usages are recorded at line 0.
- **`callers-of` is shallow.** It records direct call sites, not a transitive call graph; deep "who eventually reaches this?" analysis is intentionally excluded as too expensive for the value.
- **No watcher yet.** The index rebuilds on demand (incrementally, before each query). A long-lived `index:watch` process that rebuilds on file change is a possible follow-up, not part of this package today.
