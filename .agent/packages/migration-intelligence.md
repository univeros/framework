# univeros/migration-intelligence  ·  Altair\MigrationIntelligence

**Purpose:** bin/altair db:migration-plan — proposes safe Cycle migrations from spec/entity diffs with read-only safety checks (NOT NULL backfill, unique dupes, FK orphans, type-cast, large tables) and two-phase rename/type-change plans. Deterministic JSON for agents and CI.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `PlanRendererInterface` | `render(PlanSet)` | `string` |  |

## Concrete classes

- `AbstractDialectPlanner` _(abstract)_ — implements `DialectPlanner`
- `AddColumnIntent` _(final)_ — implements `IntentInterface`
- `AddForeignKeyIntent` _(final)_ — implements `IntentInterface`
- `AddIndexIntent` _(final)_ — implements `IntentInterface`
- `ChangeColumnIntent` _(final)_ — implements `IntentInterface`
- `ColumnShape` _(final)_
- `ColumnType` _(final)_
- `CycleMigrationEmitter` _(final)_
- `DataMigrationIntent` _(final)_ — implements `IntentInterface`
- `DatabaseProbe` _(final)_
- `DbSchemaReader` _(final)_
- `DropColumnIntent` _(final)_ — implements `IntentInterface`
- `DropColumnSafetyCheck` _(final)_ — implements `SafetyCheckInterface`
- `DropIndexIntent` _(final)_ — implements `IntentInterface`
- `EntitySchemaReader` _(final)_
- `ForeignKeySafetyCheck` _(final)_ — implements `SafetyCheckInterface`
- `ForeignKeyShape` _(final)_
- `HumanRenderer` _(final)_ — implements `PlanRendererInterface`
- `IdentifierQuoter` _(final)_
- `IndexShape` _(final)_
- `IntentKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `JsonRenderer` _(final)_ — implements `PlanRendererInterface`
- `LargeTableSafetyCheck` _(final)_ — implements `SafetyCheckInterface`
- `MigrationIntelligenceConfiguration` _(final)_ — implements `ConfigurationInterface`
- `MigrationPlan` _(final)_
- `MySqlPlanner` _(final)_ — implements `DialectPlanner`
- `NotNullSafetyCheck` _(final)_ — implements `SafetyCheckInterface`
- `PlanBuilder` _(final)_
- `PlanCommand` _(final)_
- `PlanNaming` _(final)_
- `PlanRequest` _(final)_
- `PlanSet` _(final)_
- `PlannerRegistry` _(final)_
- `PostgresPlanner` _(final)_ — implements `DialectPlanner`
- `RenameColumnIntent` _(final)_ — implements `IntentInterface`
- `RendererRegistry` _(final)_
- `RowCounter` _(final)_
- `SafetyFinding` _(final)_
- `SafetyReport` _(final)_
- `SafetyRunner` _(final)_
- `SchemaDiffer` _(final)_
- `Severity` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `SpecSchemaReader` _(final)_
- `SqlitePlanner` _(final)_ — implements `DialectPlanner`
- `TableShape` _(final)_
- `TypeCastSafetyCheck` _(final)_ — implements `SafetyCheckInterface`
- `TypeCompatibility` _(final)_
- `UniqueSafetyCheck` _(final)_ — implements `SafetyCheckInterface`

## Tests as documentation

- `tests/MigrationIntelligence/Cli/PlanCommandTest.php`
- `tests/MigrationIntelligence/Configuration/MigrationIntelligenceConfigurationTest.php`
- `tests/MigrationIntelligence/Diff/SchemaDifferTest.php`
- `tests/MigrationIntelligence/Emitter/CycleMigrationEmitterTest.php`
- `tests/MigrationIntelligence/Output/RendererTest.php`
- `tests/MigrationIntelligence/Plan/PlanBuilderTest.php`
- `tests/MigrationIntelligence/Planner/DialectPlannerTest.php`
- `tests/MigrationIntelligence/Planner/PlannerRegistryTest.php`
- `tests/MigrationIntelligence/Reader/DbSchemaReaderTest.php`
- `tests/MigrationIntelligence/Reader/EntitySchemaReaderTest.php`
- `tests/MigrationIntelligence/Reader/SpecSchemaReaderTest.php`
- `tests/MigrationIntelligence/Safety/SafetyRunnerTest.php`
- `tests/MigrationIntelligence/Schema/ColumnShapeTest.php`

## Related packages

- `cycle/database`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/persistence`
- `univeros/scaffold`
