# univeros/scaffold  ·  Altair\Scaffold

**Purpose:** Spec-to-API code generator: YAML spec in, Action/Input/Responder + OpenAPI + tests out.

## Concrete classes

- `ActionEmitter`
- `DomainSpec` _(final)_
- `DomainStubEmitter`
- `DriftDetector`
- `DriftFinding` _(final)_
- `DriftKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `DriftReport` _(final)_
- `EmissionPlan`
- `EmitOpenApiCommand` _(final)_
- `EmittedFile` _(final)_
- `EmittedFileKind` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `EndpointSpec` _(final)_
- `EntityEmitter` _(final)_
- `FileWriter`
- `HandlerEmitter`
- `HandlerTestEmitter`
- `InputEmitter`
- `InputFieldSpec` _(final)_
- `LintCommand` _(final)_
- `MessageEmitter`
- `MigrationEmitter` _(final)_
- `Naming` _(final)_
- `OpenApiEmitter`
- `OutputResponseSpec` _(final)_
- `Parser`
- `PathResolver`
- `PersistenceEntitySpec` _(final)_
- `PersistenceFieldSpec` _(final)_
- `PersistenceSpec` _(final)_
- `PhpHeader` _(final)_
- `QueueDispatchSpec` _(final)_
- `RepositoryEmitter` _(final)_
- `ResponderEmitter`
- `RouteEmitter`
- `ScaffoldCommand` _(final)_
- `Spec` _(final)_
- `SpecLoader`
- `TestEmitter`
- `TypeMapper` _(final)_
- `Validator`
- `WriteOutcome` _(final)_
- `WriteStatus` _(final)_ — implements `BackedEnum`, `UnitEnum`

## Tests as documentation

- `tests/Scaffold/Cli/ScaffoldCommandIntegrationTest.php`
- `tests/Scaffold/Emitter/ActionEmitterTest.php`
- `tests/Scaffold/Emitter/DomainStubEmitterTest.php`
- `tests/Scaffold/Emitter/EntityEmitterTest.php`
- `tests/Scaffold/Emitter/HandlerEmitterTest.php`
- `tests/Scaffold/Emitter/HandlerTestEmitterTest.php`
- `tests/Scaffold/Emitter/InputEmitterTest.php`
- `tests/Scaffold/Emitter/MessageEmitterTest.php`
- `tests/Scaffold/Emitter/MigrationEmitterTest.php`
- `tests/Scaffold/Emitter/OpenApiEmitterTest.php`
- `tests/Scaffold/Emitter/RepositoryEmitterTest.php`
- `tests/Scaffold/Emitter/ResponderEmitterTest.php`
- `tests/Scaffold/Emitter/RouteEmitterTest.php`
- `tests/Scaffold/Emitter/TestEmitterTest.php`
- `tests/Scaffold/Linter/DriftDetectorTest.php`
- `tests/Scaffold/Spec/ParserTest.php`
- `tests/Scaffold/Spec/PersistenceParserTest.php`
- `tests/Scaffold/Spec/PersistenceValidatorTest.php`
- `tests/Scaffold/Spec/ValidatorTest.php`

## Related packages

- `nikic/php-parser`
- `symfony/yaml`
- `univeros/cli`
