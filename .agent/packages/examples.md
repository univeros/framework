# univeros/examples  ·  Altair\Examples

**Purpose:** Curated, browsable library of idiomatic Univeros patterns plus CLI + MCP tools so agents can discover and read them.

## Concrete classes

- `Example` _(final)_
- `ExampleNotFoundException` _(final)_ — implements `Stringable`, `Throwable`
- `ExampleParser` _(final)_
- `ExampleRepository` _(final)_ — implements `ExampleRepositoryInterface`
- `ExamplesConfiguration` _(final)_ — implements `ConfigurationInterface`
- `ExamplesException` — implements `Stringable`, `Throwable`
- `ExamplesSettings` _(final)_
- `IndexBuilder` _(final)_
- `IndexCommand` _(final)_
- `InvalidFrontmatterException` _(final)_ — implements `Stringable`, `Throwable`
- `ListCommand` _(final)_
- `ListExamplesTool` _(final)_ — implements `McpToolInterface`
- `ReadExampleTool` _(final)_ — implements `McpToolInterface`
- `SearchCommand` _(final)_
- `SearchExamplesTool` _(final)_ — implements `McpToolInterface`
- `ShowCommand` _(final)_
- `TestCommand` _(final)_

## Tests as documentation

- `tests/Examples/Cli/CliCommandsTest.php`
- `tests/Examples/CommonStringAndArrayHelpersTest.php`
- `tests/Examples/Configuration/ExamplesConfigurationTest.php`
- `tests/Examples/ContainerDefineAndResolveTest.php`
- `tests/Examples/CookieBuildAnImmutableCookieTest.php`
- `tests/Examples/EventsRecorderRoundtripTest.php`
- `tests/Examples/ExamplesLibraryProgrammaticAccessTest.php`
- `tests/Examples/HappenDispatchADomainEventTest.php`
- `tests/Examples/Library/ExampleParserTest.php`
- `tests/Examples/Library/ExampleRepositoryTest.php`
- `tests/Examples/Library/IndexBuilderTest.php`
- `tests/Examples/Mcp/McpToolsTest.php`
- `tests/Examples/StructureTypedCollectionsTest.php`

## Related packages

- `symfony/yaml`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/mcp`
