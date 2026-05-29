# univeros/suggest  ·  Altair\Suggest

**Purpose:** bin/altair suggest — walks the introspection surface and proposes refactors: dead bindings, fat constructors, dead events, routes without specs, orphan middleware. Deterministic JSON for agents and CI.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `SuggestionRendererInterface` | `render(SuggestionReport)` | `string` |  |
| `SuggestionRuleInterface` | `analyse(Snapshot)` | `array` |  |
|  | `name()` | `string` |  |

## Concrete classes

- `BindingNode` _(final)_
- `DeadBindingRule` _(final)_ — implements `SuggestionRuleInterface`
- `DeadEventRule` _(final)_ — implements `SuggestionRuleInterface`
- `EventNode` _(final)_
- `FatConstructorRule` _(final)_ — implements `SuggestionRuleInterface`
- `HumanRenderer` _(final)_ — implements `SuggestionRendererInterface`
- `JsonRenderer` _(final)_ — implements `SuggestionRendererInterface`
- `OrphanMiddlewareRule` _(final)_ — implements `SuggestionRuleInterface`
- `RendererRegistry` _(final)_
- `RouteNode` _(final)_
- `RouteWithoutSpecRule` _(final)_ — implements `SuggestionRuleInterface`
- `RuleRegistry` _(final)_
- `Severity` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `Snapshot` _(final)_
- `SnapshotFactory` _(final)_
- `SpecNode` _(final)_
- `SuggestCommand` _(final)_
- `SuggestConfiguration` _(final)_ — implements `ConfigurationInterface`
- `Suggestion` _(final)_
- `SuggestionEngine` _(final)_
- `SuggestionReport` _(final)_

## Tests as documentation

- `tests/Suggest/Cli/SuggestCommandTest.php`
- `tests/Suggest/Configuration/SuggestConfigurationTest.php`
- `tests/Suggest/Output/RenderersTest.php`
- `tests/Suggest/Result/ResultObjectsTest.php`
- `tests/Suggest/Rule/DeadBindingRuleTest.php`
- `tests/Suggest/Rule/DeadEventRuleTest.php`
- `tests/Suggest/Rule/FatConstructorRuleTest.php`
- `tests/Suggest/Rule/OrphanMiddlewareRuleTest.php`
- `tests/Suggest/Rule/RouteWithoutSpecRuleTest.php`
- `tests/Suggest/Snapshot/SnapshotFactoryTest.php`
- `tests/Suggest/SuggestionEngineTest.php`

## Related packages

- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/introspection`
