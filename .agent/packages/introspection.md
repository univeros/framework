# univeros/introspection  ·  Altair\Introspection

**Purpose:** What's wired into this project right now? CLI commands + inspectors for the Container, routes, listeners, middleware, manifests, specs, and config.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `RendererInterface` | `render(InspectionTable)` | `string` |  |

## Concrete classes

- `ConfigDumpCommand` _(final)_
- `ConfigInspector` _(final)_
- `ContainerInspectCommand` _(final)_
- `ContainerInspector` _(final)_
- `InspectionTable` _(final)_
- `IntrospectionConfiguration` _(final)_ — implements `ConfigurationInterface`
- `JsonRenderer` _(final)_ — implements `RendererInterface`
- `ListenerInspector` _(final)_
- `ListenersListCommand` _(final)_
- `ListenersShowCommand` _(final)_
- `ManifestDiffCommand` _(final)_
- `ManifestDiffInspector` _(final)_
- `MiddlewareListCommand` _(final)_
- `PipelineInspector` _(final)_
- `RendererRegistry` _(final)_
- `RouteInspector` _(final)_
- `RoutesListCommand` _(final)_
- `RoutesShowCommand` _(final)_
- `SpecInspector` _(final)_
- `SpecListCommand` _(final)_
- `SpecShowCommand` _(final)_
- `TableRenderer` _(final)_ — implements `RendererInterface`

## Tests as documentation

- `tests/Introspection/Cli/CommandsSmokeTest.php`
- `tests/Introspection/Configuration/IntrospectionConfigurationTest.php`
- `tests/Introspection/Inspector/ConfigInspectorTest.php`
- `tests/Introspection/Inspector/ContainerInspectorTest.php`
- `tests/Introspection/Inspector/LazyBindingSafetyTest.php`
- `tests/Introspection/Inspector/ListenerInspectorTest.php`
- `tests/Introspection/Inspector/ManifestDiffInspectorTest.php`
- `tests/Introspection/Inspector/PipelineInspectorTest.php`
- `tests/Introspection/Inspector/RouteInspectorTest.php`
- `tests/Introspection/Inspector/SpecInspectorTest.php`
- `tests/Introspection/Renderer/RenderersTest.php`

## Related packages

- `symfony/yaml`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/happen`
- `univeros/http`
