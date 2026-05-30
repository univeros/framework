# univeros/agent-spec  ·  Altair\AgentSpec

**Purpose:** Generates AI-readable manifests describing every framework package and the host application.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `ManifestRendererInterface` | `render(PackageManifest)` | `string` |  |
| `PackageScannerInterface` | `scan(string, string, string\|null)` | `array` |  |
| `PhpFileFinderInterface` | `find(string)` | `iterable` |  |

## Concrete classes

- `ApplicationManifestGenerator`
- `AttributeConvention` _(final)_
- `AttributeScanner`
- `ClassEntry` _(final)_
- `ClassNameExtractor`
- `ConcreteClassScanner`
- `ConsoleReporter`
- `ContractEntry` _(final)_
- `ContractScanner`
- `IndexGenerator`
- `ManifestGenerateCommand` _(final)_
- `ManifestPipeline`
- `ManifestPipelineOptions` _(final)_
- `ManifestShowCommand` _(final)_
- `ManifestWriter`
- `MarkdownPackageRenderer` — implements `ManifestRendererInterface`
- `MethodSignature` _(final)_
- `PackageDescriptor` _(final)_
- `PackageManifest` _(final)_
- `PackageManifestGenerator`
- `PackageScanner` — implements `PackageScannerInterface`
- `PathResolver`
- `PhpFileFinder` — implements `PhpFileFinderInterface`
- `ResolvedPaths` _(final)_
- `SidecarReader`
- `TestFixtureScanner`
- `TestReference` _(final)_
- `TypeStringRenderer`

## Tests as documentation

- `tests/AgentSpec/ApplicationManifestGeneratorTest.php`
- `tests/AgentSpec/Determinism/ManifestDeterminismTest.php`
- `tests/AgentSpec/Fixtures/TestsRoot/SamplePackage/SampleGreeterTest.php`
- `tests/AgentSpec/IndexGeneratorTest.php`
- `tests/AgentSpec/ManifestPipelineTest.php`
- `tests/AgentSpec/MarkdownPackageRendererTest.php`
- `tests/AgentSpec/PackageManifestGeneratorTest.php`
- `tests/AgentSpec/PackageScannerTest.php`
- `tests/AgentSpec/Reflection/TypeStringRendererTest.php`

## Related packages

- `univeros/cli`
