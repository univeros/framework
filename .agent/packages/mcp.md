# univeros/mcp  ·  Altair\Mcp

**Purpose:** Model Context Protocol server: exposes the framework's capabilities as MCP tools so any MCP-capable agent can drive an Altair project natively.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `McpToolInterface` | `call(array)` | `array` |  |
| `ToolResolverInterface` | `resolve(string)` | `McpToolInterface` |  |
| `TransportInterface` | `close()` | `void` |  |
|  | `receive()` | `string\|null` |  |
|  | `send(string)` | `void` |  |

## Concrete classes

- `AttributeToolDiscoverer` _(final)_
- `BuiltinTools` _(final)_
- `CallersTool` _(final)_ — implements `McpToolInterface`
- `CheckDriftTool` _(final)_ — implements `McpToolInterface`
- `ConfigDumpTool` _(final)_ — implements `McpToolInterface`
- `ContainerInspectTool` _(final)_ — implements `McpToolInterface`
- `ContainerLookup` _(final)_
- `ContainerResolveTool` _(final)_ — implements `McpToolInterface`
- `ContainerToolResolver` _(final)_ — implements `ToolResolverInterface`
- `CycleDatabaseGateway` _(final)_ — implements `DatabaseGatewayInterface`
- `DbMigrateTool` _(final)_ — implements `McpToolInterface`
- `DbQueryTool` _(final)_ — implements `McpToolInterface`
- `DbSchemaTool` _(final)_ — implements `McpToolInterface`
- `DeadCodeTool` _(final)_ — implements `McpToolInterface`
- `DescribeEndpointTool` _(final)_ — implements `McpToolInterface`
- `DescribePackageTool` _(final)_ — implements `McpToolInterface`
- `DoctorTool` _(final)_ — implements `McpToolInterface`
- `EmitOpenApiTool` _(final)_ — implements `McpToolInterface`
- `EmitSdkTool` _(final)_ — implements `McpToolInterface`
- `ErrorCode` _(final)_ — implements `BackedEnum`, `UnitEnum`
- `ErrorResponse` _(final)_
- `EvalTool` _(final)_ — implements `McpToolInterface`
- `EventLog` _(final)_
- `FindUsagesTool` _(final)_ — implements `McpToolInterface`
- `HttpTransport` _(final)_
- `ImpactTool` _(final)_ — implements `McpToolInterface`
- `ImplementersTool` _(final)_ — implements `McpToolInterface`
- `InMemoryTransport` _(final)_ — implements `TransportInterface`
- `IndexTool` _(abstract)_ — implements `McpToolInterface`
- `ListCommandsTool` _(final)_ — implements `McpToolInterface`
- `ListEndpointsTool` _(final)_ — implements `McpToolInterface`
- `ListPackagesTool` _(final)_ — implements `McpToolInterface`
- `ListSpecsTool` _(final)_ — implements `McpToolInterface`
- `ListenerShowTool` _(final)_ — implements `McpToolInterface`
- `ListenersListTool` _(final)_ — implements `McpToolInterface`
- `ManifestDiffTool` _(final)_ — implements `McpToolInterface`
- `McpConfiguration` _(final)_ — implements `ConfigurationInterface`
- `McpTool` _(final)_
- `MiddlewareListTool` _(final)_ — implements `McpToolInterface`
- `NullDatabaseGateway` _(final)_ — implements `DatabaseGatewayInterface`
- `OpenApiFragments` _(final)_
- `Output` _(final)_
- `PathGuard` _(final)_
- `PhpClassScanner` _(final)_
- `PhpstanTool` _(final)_ — implements `McpToolInterface`
- `PlanMigrationTool` _(final)_ — implements `McpToolInterface`
- `ProjectContext` _(final)_
- `ReadSpecTool` _(final)_ — implements `McpToolInterface`
- `Request` _(final)_
- `Response` _(final)_
- `RewindSpecTool` _(final)_ — implements `McpToolInterface`
- `RouteShowTool` _(final)_ — implements `McpToolInterface`
- `RoutesListTool` _(final)_ — implements `McpToolInterface`
- `RunTestsTool` _(final)_ — implements `McpToolInterface`
- `ScaffoldTool` _(final)_ — implements `McpToolInterface`
- `SchemaValidationResult` _(final)_
- `SchemaValidator` _(final)_
- `ServeCommand` _(final)_
- `Server` _(final)_
- `ServerInfo` _(final)_
- `ServerMode` _(final)_
- `ServerRunner` _(final)_
- `SqlReadGuard` _(final)_
- `StdioTransport` _(final)_ — implements `TransportInterface`
- `ToolDescriptor` _(final)_
- `ToolRegistry` _(final)_
- `ToolsCommand` _(final)_
- `WriteSpecTool` _(final)_ — implements `McpToolInterface`

## Tests as documentation

- `tests/Mcp/Guard/PathGuardTest.php`
- `tests/Mcp/Guard/ServerModeTest.php`
- `tests/Mcp/McpServerIntegrationTest.php`
- `tests/Mcp/Schema/SchemaValidatorTest.php`
- `tests/Mcp/Server/ServerRunnerTest.php`
- `tests/Mcp/Server/ServerTest.php`
- `tests/Mcp/Support/PhpClassScannerTest.php`
- `tests/Mcp/Tool/AttributeToolDiscovererTest.php`
- `tests/Mcp/Tool/DatabaseToolsTest.php`
- `tests/Mcp/Tool/DiscoveryToolsTest.php`
- `tests/Mcp/Tool/EvalToolTest.php`
- `tests/Mcp/Tool/GenerationToolsTest.php`
- `tests/Mcp/Tool/IndexToolsTest.php`
- `tests/Mcp/Tool/IntrospectionToolsTest.php`
- `tests/Mcp/Tool/PlanMigrationToolTest.php`
- `tests/Mcp/Tool/ToolRegistryTest.php`
- `tests/Mcp/Tool/VerificationToolsTest.php`
- `tests/Mcp/Transport/HttpTransportTest.php`
- `tests/Mcp/Transport/InMemoryTransportTest.php`
- `tests/Mcp/Transport/StdioTransportTest.php`

## Related packages

- `opis/json-schema`
- `univeros/agent-spec`
- `univeros/cli`
- `univeros/configuration`
- `univeros/container`
- `univeros/doctor`
- `univeros/events`
- `univeros/introspection`
- `univeros/scaffold`
