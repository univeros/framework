# univeros/filesystem  ·  Altair\Filesystem

**Purpose:** The Altair Filesystem package.

## Public contracts

| Interface | Method | Returns | Notes |
|---|---|---|---|
| `FilesystemAdapterInterface` | `append(string, string, string)` | `void` | extends `FilesystemOperator`, `FilesystemReader`, `FilesystemWriter` |
|  | `exists(string)` | `bool` |  |
|  | `getDriver()` | `FilesystemOperator` |  |
|  | `listDirectories(string, bool)` | `array` |  |
|  | `prepend(string, string, string)` | `void` |  |

## Concrete classes

- `AwsS3AdapterConfiguration` — implements `ConfigurationInterface`
- `DropboxAdapterConfiguration` — implements `ConfigurationInterface`
- `Filesystem`
- `FilesystemAdapterConfiguration` — implements `ConfigurationInterface`
- `FlysystemAdapter` — implements `FilesystemAdapterInterface`, `FilesystemOperator`, `FilesystemReader`, `FilesystemWriter`
- `FtpAdapterConfiguration` — implements `ConfigurationInterface`
- `LocalAdapterConfiguration` — implements `ConfigurationInterface`
- `SftpAdapterConfiguration` — implements `ConfigurationInterface`

## Tests as documentation

- `tests/Filesystem/FilesystemTest.php`

## Related packages

- `league/flysystem`
- `univeros/configuration`
