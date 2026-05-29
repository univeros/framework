# univeros/index  ·  Altair\Index

**Purpose:** bin/altair index — a symbol-usage index built from the PHP AST plus spec awareness. Answers find-usages, implementers, callers-of, dead-code, and refactor-impact queries in milliseconds, as deterministic JSON for agents and CI. SQLite-backed.

## Concrete classes

- `BuildCommand` _(final)_
- `BuildResult` _(final)_
- `CallersOfCommand` _(final)_
- `Connection` _(final)_
- `ExtendsCommand` _(final)_
- `FileType` _(final)_
- `FindUsagesCommand` _(final)_
- `ImpactCommand` _(final)_
- `ImpactQuery` _(final)_
- `ImpactReport` _(final)_
- `ImplementsCommand` _(final)_
- `IndexBuilder` _(final)_
- `IndexConfig` _(final)_
- `IndexConfiguration` _(final)_ — implements `ConfigurationInterface`
- `Json` _(final)_
- `OrphanQuery` _(final)_
- `OrphansCommand` _(final)_
- `ParsedFile` _(final)_
- `PhpFileWalker` _(final)_
- `ProjectIndex` _(final)_
- `RowMapper` _(final)_
- `Schema` _(final)_
- `SourceScanner` _(final)_
- `SqliteStorage` _(final)_
- `Symbol` _(final)_
- `SymbolKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `SymbolUsageVisitor` _(final)_ — implements `NodeVisitor`
- `UnusedCommand` _(final)_
- `Usage` _(final)_
- `UsageKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `UsageQuery` _(final)_
- `View` _(final)_
- `YamlSpecWalker` _(final)_

## Tests as documentation

- `tests/Index/Builder/IndexBuilderTest.php`
- `tests/Index/Cli/CommandsTest.php`
- `tests/Index/Parser/PhpFileWalkerTest.php`
- `tests/Index/Parser/YamlSpecWalkerTest.php`
- `tests/Index/Query/QueryLayerTest.php`

## Related packages

- `nikic/php-parser`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/scaffold`
